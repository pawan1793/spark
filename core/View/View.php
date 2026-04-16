<?php

namespace Spark\View;

class View
{
    protected Compiler $compiler;
    protected array $sections = [];
    protected array $sectionStack = [];
    protected ?string $parent = null;
    protected array $shared = [];

    public function __construct(
        protected string $viewPath,
        protected string $cachePath
    ) {
        if (!is_dir($this->cachePath)) {
            @mkdir($this->cachePath, 0775, true);
        }
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
        $compiled = $this->getCompiledPath($name);
        $data = array_merge($this->shared, $data, ['__spark_view' => $this]);

        extract($data, EXTR_SKIP);
        ob_start();
        try {
            include $compiled;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return ob_get_clean();
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
        $this->sections[$name] = $content;
    }

    public function yield(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    protected function getCompiledPath(string $name): string
    {
        $source = $this->resolveSource($name);
        $hash = md5($source);
        $compiled = $this->cachePath . '/' . $hash . '.php';

        if (!is_file($compiled) || filemtime($compiled) < filemtime($source)) {
            $contents = file_get_contents($source);
            $compiledContents = $this->compiler->compile($contents);
            file_put_contents($compiled, $compiledContents);
        }
        return $compiled;
    }

    protected function resolveSource(string $name): string
    {
        $file = $this->viewPath . '/' . str_replace('.', '/', $name) . '.spark.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View [$name] not found at $file");
        }
        return $file;
    }
}
