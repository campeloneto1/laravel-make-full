<?php

namespace Campelo\MakeFull\Console\Commands;

use Campelo\MakeFull\Generators\ControllerGenerator;
use Campelo\MakeFull\Generators\FactoryGenerator;
use Campelo\MakeFull\Generators\MigrationGenerator;
use Campelo\MakeFull\Generators\ModelGenerator;
use Campelo\MakeFull\Generators\PolicyGenerator;
use Campelo\MakeFull\Generators\RepositoryGenerator;
use Campelo\MakeFull\Generators\RequestGenerator;
use Campelo\MakeFull\Generators\ResourceGenerator;
use Campelo\MakeFull\Generators\RouteGenerator;
use Campelo\MakeFull\Generators\SeederGenerator;
use Campelo\MakeFull\Generators\ServiceGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeFullCommand extends Command
{
    protected $signature = 'make:full
        {name : The name of the model (e.g., User, BlogPost)}
        {--fields= : Fields definition (e.g., "name:string,email:string:unique")}
        {--no-model}
        {--no-controller}
        {--no-service}
        {--no-repository}
        {--no-resource}
        {--no-requests}
        {--no-policy}
        {--no-migration}
        {--no-factory}
        {--no-seeder}
        {--no-routes}
        {--soft-deletes}
        {--uuid}
        {--web}
        {--force}';

    protected $description = 'Generate complete CRUD structure';

    protected array $fields = [];
    protected string $modelName;
    protected string $modelNamePlural;
    protected string $modelNameSnake;
    protected string $modelNameSnakePlural;
    protected string $tableName;

    public function handle(): int
    {
        // 🔥 Reset total para evitar vazamento de estado
        $this->fields = [];

        $this->modelName = Str::studly($this->argument('name'));
        $this->modelNamePlural = Str::plural($this->modelName);
        $this->modelNameSnake = Str::snake($this->modelName);
        $this->modelNameSnakePlural = Str::snake($this->modelNamePlural);
        $this->tableName = $this->modelNameSnakePlural;

        $this->parseFields();

        $this->info("Generating full CRUD structure for: {$this->modelName}");
        $this->newLine();

        $this->runGenerators();

        $this->newLine();
        $this->info('All files generated successfully!');

        $this->showSummary();

        return self::SUCCESS;
    }

    protected function runGenerators(): void
    {
        foreach ([
            'generateModel',
            'generateMigration',
            'generateController',
            'generateService',
            'generateRepository',
            'generateResource',
            'generateRequests',
            'generatePolicy',
            'generateFactory',
            'generateSeeder',
            'generateRoutes',
        ] as $method) {
            $this->{$method}();
        }
    }

    protected function parseFields(): void
    {
        $this->fields = []; // 🔥 blindagem

        $fieldsString = $this->option('fields');

        if (empty($fieldsString)) {
            return;
        }

        foreach (explode(',', $fieldsString) as $field) {

            $parts = explode(':', trim($field));
            $name = $parts[0] ?? null;

            if (!$name) {
                continue;
            }

            // 🔥 Evitar campos duplicados
            if (collect($this->fields)->contains('name', $name)) {
                continue;
            }

            $type = $parts[1] ?? 'string';
            $modifiers = array_slice($parts, 2);

            $this->fields[] = [
                'name' => $name,
                'type' => $type,
                'nullable' => in_array('nullable', $modifiers, true),
                'unique' => in_array('unique', $modifiers, true),
                'index' => in_array('index', $modifiers, true),
                'length' => $this->extractNumericModifier($modifiers, 'length'),
                'precision' => $this->extractNumericModifier($modifiers, 'precision'),
                'default' => $this->extractDefault($modifiers),
                'foreign' => $this->extractForeign($name, $type),
            ];
        }
    }

    protected function extractNumericModifier(array $modifiers, string $key): ?int
    {
        foreach ($modifiers as $mod) {
            if (preg_match("/{$key}\((\d+)\)/", $mod, $matches)) {
                return (int) $matches[1];
            }
        }
        return null;
    }

    protected function extractDefault(array $modifiers): ?string
    {
        foreach ($modifiers as $mod) {
            if (preg_match('/default\((.*?)\)/', $mod, $matches)) {
                return $matches[1];
            }
        }
        return null;
    }

    protected function extractForeign(string $name, string $type): ?array
    {
        if ($type === 'foreignId' || (str_ends_with($name, '_id') && $name !== 'id')) {

            $relatedModel = Str::studly(str_replace('_id', '', $name));

            return [
                'model' => $relatedModel,
                'table' => Str::snake(Str::plural($relatedModel)),
            ];
        }

        return null;
    }

    protected function generateModel(): void
    {
        if ($this->option('no-model')) return;

        $this->generateFile(
            new ModelGenerator(
                $this->modelName,
                $this->fields,
                $this->option('soft-deletes') || config('make-full.soft_deletes'),
                $this->option('uuid') || config('make-full.uuid')
            ),
            'Model'
        );
    }

    protected function generateMigration(): void
    {
        if ($this->option('no-migration')) return;

        $this->generateFile(
            new MigrationGenerator(
                $this->modelName,
                $this->tableName,
                $this->fields,
                $this->option('soft-deletes') || config('make-full.soft_deletes'),
                $this->option('uuid') || config('make-full.uuid')
            ),
            'Migration'
        );
    }

    protected function generateController(): void
    {
        if ($this->option('no-controller')) return;

        $this->generateFile(
            new ControllerGenerator(
                $this->modelName,
                $this->fields,
                !$this->option('web'),
                config('make-full.use_repository', true)
            ),
            'Controller'
        );
    }

    protected function generateService(): void
    {
        if ($this->option('no-service')) return;

        $this->generateFile(
            new ServiceGenerator(
                $this->modelName,
                $this->fields,
                config('make-full.use_repository', true),
                config('make-full.default_pagination', 15)
            ),
            'Service'
        );
    }

    protected function generateRepository(): void
    {
        if ($this->option('no-repository') || !config('make-full.use_repository', true)) return;

        $this->generateFile(
            new RepositoryGenerator(
                $this->modelName,
                $this->fields,
                config('make-full.default_pagination', 15)
            ),
            'Repository'
        );
    }

    protected function generateResource(): void
    {
        if ($this->option('no-resource')) return;

        $this->generateFile(
            new ResourceGenerator($this->modelName, $this->fields),
            'Resource'
        );
    }

    protected function generateRequests(): void
    {
        if ($this->option('no-requests')) return;

        $this->generateFile(
            new RequestGenerator($this->modelName, 'Store', $this->fields),
            'StoreRequest'
        );

        $this->generateFile(
            new RequestGenerator($this->modelName, 'Update', $this->fields),
            'UpdateRequest'
        );
    }

    protected function generatePolicy(): void
    {
        if ($this->option('no-policy')) return;

        $this->generateFile(
            new PolicyGenerator($this->modelName),
            'Policy'
        );
    }

    protected function generateFactory(): void
    {
        if ($this->option('no-factory')) return;

        $this->generateFile(
            new FactoryGenerator($this->modelName, $this->fields),
            'Factory'
        );
    }

    protected function generateSeeder(): void
    {
        if ($this->option('no-seeder')) return;

        $this->generateFile(
            new SeederGenerator($this->modelName),
            'Seeder'
        );
    }

    protected function generateRoutes(): void
    {
        if ($this->option('no-routes') || !config('make-full.add_routes', true)) return;

        $generator = new RouteGenerator(
            $this->modelName,
            $this->option('web') ? 'web' : 'api'
        );

        $result = $generator->appendToFile($this->option('force'));

        $result
            ? $this->components->info("Routes added to routes/{$generator->getRoutesFile()}.php")
            : $this->components->warn("Routes already exist or file not found");
    }

    protected function generateFile($generator, string $type): void
    {
        $path = $generator->getPath();
        $fullPath = base_path($path);

        if (File::exists($fullPath) && !$this->option('force')) {
            $this->components->warn("{$type} already exists: {$path}");
            return;
        }

        File::ensureDirectoryExists(dirname($fullPath));
        File::put($fullPath, $generator->generate());

        $this->components->info("{$type} created: {$path}");
    }

    protected function showSummary(): void
    {
        $this->newLine();
        $this->components->info('Summary:');

        $this->line("  Model:      App\\Models\\{$this->modelName}");
        $this->line("  Controller: App\\Http\\Controllers\\{$this->modelName}Controller");
        $this->line("  Service:    App\\Services\\{$this->modelName}Service");

        if (config('make-full.use_repository', true) && !$this->option('no-repository')) {
            $this->line("  Repository: App\\Repositories\\{$this->modelName}Repository");
        }

        $this->line("  Resource:   App\\Http\\Resources\\{$this->modelName}Resource");
        $this->line("  Requests:   App\\Http\\Requests\\Store{$this->modelName}Request");
        $this->line("              App\\Http\\Requests\\Update{$this->modelName}Request");
        $this->line("  Policy:     App\\Policies\\{$this->modelName}Policy");

        $this->newLine();
        $this->line("  Don't forget to:");
        $this->line("  - Run: php artisan migrate");
        $this->line("  - Register policy in AuthServiceProvider (if needed)");
    }
}
