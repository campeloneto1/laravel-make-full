<?php

namespace Campelo\MakeFull\Generators;

class RequestGenerator extends BaseGenerator
{
    protected string $type; // 'Store' or 'Update'

    public function __construct(string $modelName, string $type, array $fields = [])
    {
        parent::__construct($modelName, $fields);
        $this->type = $type;
    }

    public function generate(): string
    {
        $namespace = config('make-full.namespaces.request', 'App\\Http\\Requests');
        $className = "{$this->type}{$this->modelName}Request";
        $rules = $this->buildRules();
        $isUpdate = $this->type === 'Update';

        $modelProperty = '';
        $resolveMethod = '';

        if ($isUpdate) {
            $modelProperty = <<<PHP

    protected ?\\{$this->getModelNamespace()}\\{$this->modelName} \${$this->modelNameCamel} = null;

    protected function prepareForValidation(): void
    {
        \$this->{$this->modelNameCamel} = \$this->route('{$this->modelNameSnake}');
    }
PHP;
        }

        $content = <<<PHP
<?php

namespace {$namespace};

use Illuminate\Foundation\Http\FormRequest;

class {$className} extends FormRequest
{{$modelProperty}

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \\Illuminate\\Contracts\\Validation\\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
{$rules}
        ];
    }
}
PHP;

        return $content;
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

        // Required / Nullable / Sometimes
        if ($field['nullable']) {
            $rules[] = 'nullable';
        } elseif ($isUpdate) {
            $rules[] = 'sometimes';
            $rules[] = 'required';
        } else {
            $rules[] = 'required';
        }

        // Type rule
        $typeRule = $this->getTypeRule($field);
        if ($typeRule) {
            $rules[] = $typeRule;
        }

        // Unique
        if ($field['unique']) {
            $table = $this->modelNameSnakePlural;
            if ($isUpdate) {
                return "[\n                '" . implode("',\n                '", $rules) . "',\n                \\Illuminate\\Validation\\Rule::unique('{$table}', '{$field['name']}')->ignore(\$this->{$this->modelNameCamel}?->id),\n            ]";
            } else {
                $rules[] = "unique:{$table},{$field['name']}";
            }
        }

        // Foreign key
        if ($field['foreign']) {
            $table = $field['foreign']['table'];
            $rules[] = "exists:{$table},id";
        }

        return "'" . implode('|', $rules) . "'";
    }

    protected function getTypeRule(array $field): ?string
    {
        $type = $field['type'];
        $name = $field['name'];

        // Check by name first
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
