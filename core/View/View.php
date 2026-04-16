<?php

namespace Spark\View;

class View
{
    protected Compiler $compiler;
    protected array $sections = [];
    protected array $sectionStack = [];
    protected ?string $parent = null;
    protected array $shared = [];
    protected string $realViewPath;

    /**
     * Variables that must never leak from view data into the include scope.
     */
    protected const PROTECTED_KEYS = [
        'GLOBALS', '_ENV', '_SERVER', '_SESSION', '_COOKIE',
        '_FILES', '_GET', '_POST', '_REQUEST',
        'this', '__spark_view', '__spark_compiled',
    ];

    public function __construct(
        protected string $viewPath,
        protected string $cachePath
    ) {
        if (!is_dir($this->cachePath)) {
            @mkdir($this->cachePath, 0770, true);
        }
        // Lock down the cache dir permissions so other OS users can't inject
        // compiled PHP that would be included verbatim.
        @chmod($this->cachePath, 0770);

        $real = realpath($this->viewPath);
        if (!$real) {
            throw new \RuntimeException("View path does not exist: {$this->viewPath}");
        }
        $this->realViewPath = $real;
        $this->compiler = new Compiler();
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    public function render(string $name, array $data = []): string
    {
        $this->sections = [];
        $this->sectionStack = [];
        $this->parent = null;

        $output = $this->renderFile($name, $data);

        // Resolve @extends chain
        while ($this->parent) {
            $parent = $this->parent;
            $this->parent = null;
            $output = $this->renderFile($parent, $data);
        }

        return $output;
    }

    public function renderFile(string $name, array $data): string
    {
        $__spark_compiled = $this->getCompiledPath($name);
        $data = $this->filterData(array_merge($this->shared, $data));

        // Run the include in an isolated closure bound to $this so the compiled
        // template can call $__spark_view->... but cannot access View internals.
        $render = function (string $__spark_compiled, array $__spark_data) {
            // Scrub any protected keys defensively.
            foreach (View::PROTECTED_KEYS as $__k) {
                unset($__spark_data[$__k]);
            }
            $__spark_view = $this;
            extract($__spark_data, EXTR_SKIP);
            unset($__spark_data, $__k);
            ob_start();
            try {
                include $__spark_compiled;
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }
            return ob_get_clean();
        };

        return $render->call($this, $__spark_compiled, $data);
    }

    public function include(string $name, array $data = []): string
    {
        return $this->renderFile($name, $data);
    }

    public function extend(string $name): void
    {
        $this->parent = $name;
    }

    public function startSection(string $name): void
    {
        $this->sectionStack[] = $name;
        ob_start();
    }

    public function endSection(): void
    {
        $name = array_pop($this->sectionStack);
        $this->sections[$name] = ob_get_clean();
    }

    public function section(string $name, string $content): void
    {
        // Content passed via @section('name', $value) should be treated as
        // untrusted text, not HTML — match the escaping rules of {{ $var }}.
        $this->sections[$name] = htmlspecialchars(
            (string) $content,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }

    /**
     * Output the contents of a section. Sections captured with
     * @section/@endsection blocks are already HTML produced by the compiled
     * view; the short-form @section('name', $value) path auto-escapes before
     * storing. The default string is escaped here as well since callers may
     * pass user data as the default.
     */
    public function yield(string $name, string $default = ''): string
    {
        if (array_key_exists($name, $this->sections)) {
            return $this->sections[$name];
        }
        return htmlspecialchars($default, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    protected function getCompiledPath(string $name): string
    {
        $source = $this->resolveSource($name);
        $hash = hash('sha256', $source);
        $compiled = $this->cachePath . '/' . $hash . '.php';

        if (!is_file($compiled) || filemtime($compiled) < filemtime($source)) {
            $contents = file_get_contents($source);
            $compiledContents = $this->compiler->compile($contents);
            // Atomic write so a partially written cache file is never included.
            $tmp = $compiled . '.tmp.' . bin2hex(random_bytes(6));
            file_put_contents($tmp, $compiledContents);
            @chmod($tmp, 0660);
            rename($tmp, $compiled);
        }
        return $compiled;
    }

    /**
     * Resolve a view name like "home" or "admin.users.index" to its source
     * file, rejecting any attempt at path traversal or absolute paths.
     */
    protected function resolveSource(string $name): string
    {
        if ($name === '' || !preg_match('/^[A-Za-z0-9_][A-Za-z0-9_\-]*(\.[A-Za-z0-9_][A-Za-z0-9_\-]*)*$/', $name)) {
            throw new \RuntimeException("Invalid view name [$name].");
        }

        $relative = str_replace('.', '/', $name) . '.spark.php';
        $file = $this->realViewPath . '/' . $relative;
        $real = realpath($file);

        if ($real === false || !is_file($real)) {
            throw new \RuntimeException("View [$name] not found at $file");
        }
        // Guard against symlinks escaping the views directory.
        if (!str_starts_with($real, $this->realViewPath . DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException("View [$name] resolves outside the views directory.");
        }
        return $real;
    }

    /**
     * Strip superglobal-shadowing and reserved keys from the template variable
     * bag before it is extract()ed into the include scope.
     */
    protected function filterData(array $data): array
    {
        foreach (self::PROTECTED_KEYS as $key) {
            unset($data[$key]);
        }
        return $data;
    }
}
