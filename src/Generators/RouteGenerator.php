<?php

namespace Campelo\MakeFull\Generators;

class RouteGenerator extends BaseGenerator
{
    protected string $routeType;

    public function __construct(string $modelName, string $routeType = 'api')
    {
        parent::__construct($modelName);
        $this->routeType = $routeType;
    }

    public function generate(): string
    {
        $controllerNamespace = config('make-full.namespaces.controller', 'App\\Http\\Controllers');

        return <<<PHP

// {$this->modelName} routes
Route::apiResource('{$this->modelNameSnakePlural}', \\{$controllerNamespace}\\{$this->modelName}Controller::class);
PHP;
    }

    public function getPath(): string
    {
        return "routes/{$this->routeType}.php";
    }

    public function getRoutesFile(): string
    {
        return $this->routeType;
    }

    public function appendToFile(bool $force = false): bool
    {
        $filePath = base_path($this->getPath());

        if (!file_exists($filePath)) {
            return false;
        }

        $content = file_get_contents($filePath);
        $routeContent = $this->generate();

        // Check if routes already exist
        if (str_contains($content, "'{$this->modelNameSnakePlural}'") && !$force) {
            return false;
        }

        // Append routes
        file_put_contents($filePath, $content . "\n" . $routeContent);

        return true;
    }
}
