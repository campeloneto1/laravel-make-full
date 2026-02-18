<?php

namespace Campelo\MakeFull\Generators;

class RequestGenerator extends BaseGenerator
{
    protected string $type;
    protected string $modelNameCamel;
    protected string $modelNameSnake;

    public function __construct(string $modelName, string $type, array $fields = [])
    {
        parent::__construct($modelName, $fields);

        $this->type = $type;
        $this->modelNameCamel = lcfirst($modelName);
        $this->modelNameSnake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $modelName));
    }

    public function generate(): string
    {
        $namespace = config('make-full.namespaces.request', 'App\\Http\\Requests');
        $className = "{$this->type}{$this->modelName}Request";

        $isUpdate = $this->type === 'Update';
        $rules = $this->buildRules();
        $uses = [];

        if ($this->hasUniqueField()) {
            $uses[] = "use Illuminate\\Validation\\Rule;";
        }

        $modelProperty = '';
        if ($isUpdate) {
            $modelNamespace = $this->getModelNamespace();

            $modelProperty = <<<PHP

    protected ?\\{$modelNamespace}\\{$this->modelName} \${$this->modelNameCamel};

    protected function prepareForValidation(): void
    {
        \$this->{$this->modelNameCamel} = \$this->route('{$this->modelNameSnake}');
    }
PHP;
        }

        $usesStr = implode("\n", $uses);

        return <<<PHP
<?php

namespace {$namespace};

use Illuminate\Foundation\Http\FormRequest;
{$usesStr}

class {$className} extends FormRequest
{{$modelProperty}

    public function authorize(): bool
    {
        return true;
        // Exemplo:
        // return \$this->user()->can('{$this->type}{$this->modelName}', {$this->modelName}::class);
    }

    public function rules(): array
    {
        return [
{$rules}
        ];
    }
}
PHP;
    }

    protected function buildRules(): string
    {
        if (empty($this->fields)) {
            return "            // Add your validation rules";
        }

        $isUpdate = $this->type === 'Update';
        $lines = [];

        foreach ($this->fields as $field) {
            $rule = $this->buildFieldRule($field, $isUpdate);
            $lines[] = "            '{$field['name']}' => {$rule},";
        }

        return implode("\n", $lines);
    }

    protected function buildFieldRule(array $field, bool $isUpdate): string
    {
        $rules = [];

        // Required logic
        if ($isUpdate) {
            $rules[] = 'sometimes';
        }

        if (!$field['nullable'] && !$isUpdate) {
            $rules[] = 'required';
        }

        if ($field['nullable']) {
            $rules[] = 'nullable';
        }

        // Type rule
        if ($typeRule = $this->getTypeRule($field)) {
            $rules[] = $typeRule;
        }

        $table = $this->modelNameSnakePlural;

        // Unique
        if (!empty($field['unique'])) {
            if ($isUpdate) {
                return "[\n                '" . implode("',\n                '", $rules) . "',\n                Rule::unique('{$table}', '{$field['name']}')->ignore(\$this->{$this->modelNameCamel}?->getKey()),\n            ]";
            }

            $rules[] = "unique:{$table},{$field['name']}";
        }

        // Foreign
        if (!empty($field['foreign'])) {
            $rules[] = "exists:{$field['foreign']['table']},id";
        }

        return "'" . implode('|', $rules) . "'";
    }

    protected function getTypeRule(array $field): ?string
    {
        $type = $field['type'];
        $name = $field['name'];

        if (str_contains($name, 'email')) {
            return 'email';
        }

        if (str_contains($name, 'url') || str_contains($name, 'website')) {
            return 'url';
        }

        return match ($type) {
            'string' => 'string|max:255',
            'text', 'longText', 'mediumText' => 'string',
            'integer', 'int', 'bigInteger', 'smallInteger', 'tinyInteger' => 'integer',
            'decimal', 'double', 'float' => 'numeric',
            'boolean', 'bool' => 'boolean',
            'date' => 'date',
            'datetime', 'dateTime', 'timestamp' => 'date',
            'json', 'array' => 'array',
            'foreignId' => 'integer',
            default => 'string',
        };
    }

    protected function hasUniqueField(): bool
    {
        foreach ($this->fields as $field) {
            if (!empty($field['unique'])) {
                return true;
            }
        }

        return false;
    }

    protected function getModelNamespace(): string
    {
        return config('make-full.namespaces.model', 'App\\Models');
    }

    public function getPath(): string
    {
        $path = config('make-full.paths.request', 'app/Http/Requests');

        return "{$path}/{$this->type}{$this->modelName}Request.php";
    }
}
