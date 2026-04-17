<?php

namespace Spark\View;

class Compiler
{
    public function compile(string $template): string
    {
        // Order matters
        $template = $this->compileComments($template);
        $template = $this->compileExtends($template);
        $template = $this->compileIncludes($template);
        $template = $this->compileSections($template);
        $template = $this->compileYields($template);
        $template = $this->compileControlStructures($template);
        $template = $this->compileNonces($template);
        $template = $this->compileEchos($template);
        return $template;
    }

    protected function compileComments(string $t): string
    {
        return preg_replace('/\{\{--(.*?)--\}\}/s', '', $t);
    }

    protected function compileExtends(string $t): string
    {
        if (preg_match('/@extends\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $t, $m)) {
            $t = preg_replace('/@extends\s*\([^)]+\)\s*/', '', $t, 1);
            $t .= "\n<?php \$__spark_view->extend('" . addslashes($m[1]) . "'); ?>";
        }
        return $t;
    }

    protected function compileIncludes(string $t): string
    {
        return preg_replace_callback('/@include\s*\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,\s*(.+?))?\s*\)/', function ($m) {
            $name = addslashes($m[1]);
            $data = isset($m[2]) ? $m[2] : '[]';
            return "<?php echo \$__spark_view->include('$name', $data); ?>";
        }, $t);
    }

    protected function compileSections(string $t): string
    {
        $t = preg_replace_callback('/@section\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*(.+?)\)/', function ($m) {
            return "<?php \$__spark_view->section('" . addslashes($m[1]) . "', " . $m[2] . "); ?>";
        }, $t);

        $t = preg_replace('/@section\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', "<?php \$__spark_view->startSection('$1'); ?>", $t);
        $t = preg_replace('/@endsection/', "<?php \$__spark_view->endSection(); ?>", $t);
        return $t;
    }

    protected function compileYields(string $t): string
    {
        return preg_replace_callback('/@yield\s*\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,\s*(.+?))?\s*\)/', function ($m) {
            $default = isset($m[2]) ? $m[2] : "''";
            return "<?php echo \$__spark_view->yield('" . addslashes($m[1]) . "', $default); ?>";
        }, $t);
    }

    protected function compileControlStructures(string $t): string
    {
        // Matches expressions with up to 2 levels of nested parentheses (e.g. isset(), count())
        $e = '((?:[^()]+|\((?:[^()]+|\([^()]*\))*\))*)';

        $t = preg_replace('/@if\s*\('      . $e . '\)/', '<?php if ($1): ?>',        $t);
        $t = preg_replace('/@elseif\s*\('  . $e . '\)/', '<?php elseif ($1): ?>',    $t);
        $t = preg_replace('/@else\b/',                   '<?php else: ?>',           $t);
        $t = preg_replace('/@endif\b/',                  '<?php endif; ?>',          $t);
        $t = preg_replace('/@unless\s*\('  . $e . '\)/', '<?php if (!($1)): ?>',     $t);
        $t = preg_replace('/@endunless\b/',               '<?php endif; ?>',         $t);
        $t = preg_replace('/@foreach\s*\(' . $e . '\)/', '<?php foreach ($1): ?>',   $t);
        $t = preg_replace('/@endforeach\b/',              '<?php endforeach; ?>',    $t);
        $t = preg_replace('/@for\s*\('     . $e . '\)/', '<?php for ($1): ?>',       $t);
        $t = preg_replace('/@endfor\b/',                  '<?php endfor; ?>',        $t);
        $t = preg_replace('/@while\s*\('   . $e . '\)/', '<?php while ($1): ?>',     $t);
        $t = preg_replace('/@endwhile\b/',                '<?php endwhile; ?>',      $t);
        $t = preg_replace('/@isset\s*\('   . $e . '\)/', '<?php if (isset($1)): ?>', $t);
        $t = preg_replace('/@endisset\b/',                '<?php endif; ?>',         $t);
        $t = preg_replace('/@empty\s*\('   . $e . '\)/', '<?php if (empty($1)): ?>', $t);
        $t = preg_replace('/@endempty\b/',                '<?php endif; ?>',         $t);
        $t = preg_replace('/@php\b/',                    '<?php ',                   $t);
        $t = preg_replace('/@endphp\b/',                  ' ?>',                    $t);
        $t = preg_replace('/@csrf\b/',
            '<?php echo \'<input type="hidden" name="_token" value="\' . htmlspecialchars(\\Spark\\Http\\Session::csrfToken(), ENT_QUOTES | ENT_SUBSTITUTE, \'UTF-8\') . \'">\'; ?>',
            $t);
        $t = preg_replace('/@method\s*\(\s*[\'"]([A-Z]+)[\'"]\s*\)/',
            '<?php echo \'<input type="hidden" name="_method" value="$1">\'; ?>',
            $t);
        return $t;
    }

    protected function compileNonces(string $t): string
    {
        $nonce = '<?php echo htmlspecialchars(function_exists(\'csp_nonce\') ? csp_nonce() : \'\', ENT_QUOTES|ENT_SUBSTITUTE, \'UTF-8\'); ?>';

        $t = preg_replace(
            '/<style(?![^>]*\bnonce\b)([^>]*)>/i',
            '<style$1 nonce="' . $nonce . '">',
            $t
        );
        $t = preg_replace(
            '/<script(?![^>]*\bnonce\b)([^>]*)>/i',
            '<script$1 nonce="' . $nonce . '">',
            $t
        );
        return $t;
    }

    protected function compileEchos(string $t): string
    {
        // Raw: {!! $var !!}
        $t = preg_replace('/\{!!\s*(.+?)\s*!!\}/s', '<?php echo $1; ?>', $t);
        // Escaped: {{ $var }}
        $t = preg_replace('/\{\{\s*(.+?)\s*\}\}/s', '<?php echo htmlspecialchars((string) ($1 ?? \'\'), ENT_QUOTES | ENT_SUBSTITUTE, \'UTF-8\'); ?>', $t);
        return $t;
    }
}
