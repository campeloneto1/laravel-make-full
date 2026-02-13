<?php

namespace Campelo\MakeFull\Generators;

class SeederGenerator extends BaseGenerator
{
    public function generate(): string
    {
        $modelNamespace = config('make-full.namespaces.model', 'App\\Models');

        $content = <<<PHP
<?php

namespace Database\\Seeders;

use {$modelNamespace}\\{$this->modelName};
use Illuminate\\Database\\Seeder;

class {$this->modelName}Seeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        {$this->modelName}::factory()->count(10)->create();
    }
}
PHP;

        return $content;
    }

    public function getPath(): string
    {
        $path = config('make-full.paths.seeder', 'database/seeders');
        return "{$path}/{$this->modelName}Seeder.php";
    }
}
