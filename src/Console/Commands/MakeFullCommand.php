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
use Illuminate\Support\Str;


class MakeFullCommand extends Command
{
    protected $signature = 'make:full
                            {name : The name of the model (e.g., User, BlogPost)}
                            {--fields= : Fields definition (e.g., "name:string,email:string:unique,age:integer:nullable")}
                            {--no-model : Skip model generation}
                            {--no-controller : Skip controller generation}
                            {--no-service : Skip service generation}
                            {--no-repository : Skip repository generation}
                            {--no-resource : Skip resource generation}
                            {--no-requests : Skip request generation}
                            {--no-policy : Skip policy generation}
                            {--no-migration : Skip migration generation}
                            {--no-factory : Skip factory generation}
                            {--no-seeder : Skip seeder generation}
                            {--no-routes : Skip routes generation}
                            {--soft-deletes : Add soft deletes to model}
                            {--uuid : Use UUID as primary key}
                            {--api : Generate API controller (default)}
                            {--web : Generate web controller instead of API}
                            {--force : Overwrite existing files}';

    protected $description = 'Generate complete CRUD structure: Model, Controller, Service, Repository, Resource, Requests, Policy, Migration, Factory, Seeder';

    protected array $fields = [];
    protected string $modelName;
    protected string $modelNamePlural;
    protected string $modelNameSnake;
    protected string $modelNameSnakePlural;
    protected string $tableName;

    public function handle(): int
    {
        $this->modelName = Str::studly($this->argument('name'));
        $this->modelNamePlural = Str::plural($this->modelName);
        $this->modelNameSnake = Str::snake($this->modelName);
        $this->modelNameSnakePlural = Str::snake($this->modelNamePlural);
        $this->tableName = $this->modelNameSnakePlural;

        // Parse fields
        $this->parseFields();

        $this->info("Generating full CRUD structure for: {$this->modelName}");
        $this->newLine();

        // Generate each file
        $this->generateModel();
        $this->generateMigration();
        $this->generateController();
        $this->generateService();
        $this->generateRepository();
        $this->generateResource();
        $this->generateRequests();
        $this->generatePolicy();
        $this->generateFactory();
        $this->generateSeeder();
        $this->generateRoutes();

        $this->newLine();
        $this->info('All files generated successfully!');

        // Show summary
        $this->showSummary();

        return Command::SUCCESS;
    }

    protected function parseFields(): void
{
    $fieldsString = $this->option('fields');

    if (empty($fieldsString)) {
        return;
    }

    $fields = explode(',', $fieldsString);

    foreach ($fields as $field) {
        $parts = explode(':', trim($field));

        $name = $parts[0];
        $type = $parts[1] ?? 'string';
        $modifiers = array_slice($parts, 2);

        $this->fields[] = [
            'name' => $name,
            'type' => $type,
            'nullable' => in_array('nullable', $modifiers),
            'unique' => in_array('unique', $modifiers),
            'index' => in_array('index', $modifiers),
            'length' => $this->extractNumericModifier($modifiers, 'length'),
            'precision' => $this->extractNumericModifier($modifiers, 'precision'),
            'default' => $this->extractDefault($modifiers),
            'foreign' => $this->extractForeign($name, $type),
        ];
    }
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
        if ($type === 'foreignId' || str_ends_with($name, '_id')) {
            $relatedModel = Str::studly(str_replace('_id', '', $name));
            $relatedTable = Str::snake(Str::plural($relatedModel));
            return [
                'model' => $relatedModel,
                'table' => $relatedTable,
            ];
        }
        return null;
    }

    protected function generateModel(): void
    {
        if ($this->option('no-model')) {
            return;
        }

        $generator = new ModelGenerator(
            $this->modelName,
            $this->fields,
            $this->option('soft-deletes') || config('make-full.soft_deletes'),
            $this->option('uuid') || config('make-full.uuid')
        );

        $this->generateFile($generator, 'Model');
    }

    protected function generateMigration(): void
    {
        if ($this->option('no-migration')) {
            return;
        }

        $generator = new MigrationGenerator(
            $this->modelName,
            $this->tableName,
            $this->fields,
            $this->option('soft-deletes') || config('make-full.soft_deletes'),
            $this->option('uuid') || config('make-full.uuid')
        );

        $this->generateFile($generator, 'Migration');
    }

    protected function generateController(): void
    {
        if ($this->option('no-controller')) {
            return;
        }

        $isApi = !$this->option('web');

        $generator = new ControllerGenerator(
            $this->modelName,
            $this->fields,
            $isApi,
            config('make-full.use_repository', true)
        );

        $this->generateFile($generator, 'Controller');
    }

    protected function generateService(): void
    {
        if ($this->option('no-service')) {
            return;
        }

        $generator = new ServiceGenerator(
            $this->modelName,
            $this->fields,
            config('make-full.use_repository', true),
            config('make-full.default_pagination', 15)
        );

        $this->generateFile($generator, 'Service');
    }

    protected function generateRepository(): void
    {
        if ($this->option('no-repository') || !config('make-full.use_repository', true)) {
            return;
        }

        $generator = new RepositoryGenerator(
            $this->modelName,
            $this->fields,
            config('make-full.default_pagination', 15)
        );

        $this->generateFile($generator, 'Repository');
    }

    protected function generateResource(): void
    {
        if ($this->option('no-resource')) {
            return;
        }

        $generator = new ResourceGenerator(
            $this->modelName,
            $this->fields
        );

        $this->generateFile($generator, 'Resource');
    }

    protected function generateRequests(): void
    {
        if ($this->option('no-requests')) {
            return;
        }

        // Store Request
        $storeGenerator = new RequestGenerator(
            $this->modelName,
            'Store',
            $this->fields
        );
        $this->generateFile($storeGenerator, 'StoreRequest');

        // Update Request
        $updateGenerator = new RequestGenerator(
            $this->modelName,
            'Update',
            $this->fields
        );
        $this->generateFile($updateGenerator, 'UpdateRequest');
    }

    protected function generatePolicy(): void
    {
        if ($this->option('no-policy')) {
            return;
        }

        $generator = new PolicyGenerator($this->modelName);
        $this->generateFile($generator, 'Policy');
    }

    protected function generateFactory(): void
    {
        if ($this->option('no-factory')) {
            return;
        }

        $generator = new FactoryGenerator(
            $this->modelName,
            $this->fields
        );

        $this->generateFile($generator, 'Factory');
    }

    protected function generateSeeder(): void
    {
        if ($this->option('no-seeder')) {
            return;
        }

        $generator = new SeederGenerator($this->modelName);
        $this->generateFile($generator, 'Seeder');
    }

    protected function generateRoutes(): void
    {
        if ($this->option('no-routes') || !config('make-full.add_routes', true)) {
            return;
        }

        $generator = new RouteGenerator(
            $this->modelName,
            $this->option('web') ? 'web' : 'api'
        );

        $result = $generator->appendToFile($this->option('force'));

        if ($result) {
            $this->components->info("Routes added to routes/{$generator->getRoutesFile()}.php");
        } else {
            $this->components->warn("Routes already exist or file not found");
        }
    }

    protected function generateFile($generator, string $type): void
    {
        $path = $generator->getPath();
        $fullPath = base_path($path);

        // Check if file exists
        if (file_exists($fullPath) && !$this->option('force')) {
            $this->components->warn("{$type} already exists: {$path}");
            return;
        }

        // Ensure directory exists
        $directory = dirname($fullPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // Generate and write file
        $content = $generator->generate();
        file_put_contents($fullPath, $content);

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
        $this->line("  - Register policy in AuthServiceProvider (if not using auto-discovery)");
    }

    protected function extractNumericModifier(array $modifiers, string $key): ?int
{
    foreach ($modifiers as $mod) {
        if (str_starts_with($mod, "{$key}(")) {
            return (int) trim($mod, "{$key}()");
        }
    }
    return null;
}
}
