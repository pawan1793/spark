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
        $patterns = [
            '/@if\s*\((.+?)\)/' => '<?php if ($1): ?>',
            '/@elseif\s*\((.+?)\)/' => '<?php elseif ($1): ?>',
            '/@else\b/' => '<?php else: ?>',
            '/@endif\b/' => '<?php endif; ?>',
            '/@unless\s*\((.+?)\)/' => '<?php if (!($1)): ?>',
            '/@endunless\b/' => '<?php endif; ?>',
            '/@foreach\s*\((.+?)\)/' => '<?php foreach ($1): ?>',
            '/@endforeach\b/' => '<?php endforeach; ?>',
            '/@for\s*\((.+?)\)/' => '<?php for ($1): ?>',
            '/@endfor\b/' => '<?php endfor; ?>',
            '/@while\s*\((.+?)\)/' => '<?php while ($1): ?>',
            '/@endwhile\b/' => '<?php endwhile; ?>',
            '/@isset\s*\((.+?)\)/' => '<?php if (isset($1)): ?>',
            '/@endisset\b/' => '<?php endif; ?>',
            '/@empty\s*\((.+?)\)/' => '<?php if (empty($1)): ?>',
            '/@endempty\b/' => '<?php endif; ?>',
            '/@php\b/' => '<?php ',
            '/@endphp\b/' => ' ?>',
            '/@csrf\b/' => '<?php echo \'<input type="hidden" name="_token" value="\' . ($_SESSION[\'_csrf\'] ?? \'\') . \'">\'; ?>',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $t = preg_replace($pattern, $replacement, $t);
        }
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
