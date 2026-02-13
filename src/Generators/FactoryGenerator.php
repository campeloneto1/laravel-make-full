<?php

namespace Campelo\MakeFull\Generators;

class FactoryGenerator extends BaseGenerator
{
    public function generate(): string
    {
        $modelNamespace = config('make-full.namespaces.model', 'App\\Models');
        $fields = $this->buildFactoryFields();

        $content = <<<PHP
<?php

namespace Database\\Factories;

use {$modelNamespace}\\{$this->modelName};
use Illuminate\\Database\\Eloquent\\Factories\\Factory;

/**
 * @extends \\Illuminate\\Database\\Eloquent\\Factories\\Factory<\\{$modelNamespace}\\{$this->modelName}>
 */
class {$this->modelName}Factory extends Factory
{
    protected \$model = {$this->modelName}::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
{$fields}
        ];
    }
}
PHP;

        return $content;
    }

    protected function buildFactoryFields(): string
    {
        if (empty($this->fields)) {
            return "            // Add your factory fields";
        }

        $lines = [];

        foreach ($this->fields as $field) {
            $faker = $this->getFakerMethodForField($field);
            $lines[] = "            '{$field['name']}' => {$faker},";
        }

        return implode("\n", $lines);
    }

    public function getPath(): string
    {
        $path = config('make-full.paths.factory', 'database/factories');
        return "{$path}/{$this->modelName}Factory.php";
    }
}
