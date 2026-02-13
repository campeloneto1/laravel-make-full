<?php

namespace Campelo\MakeFull\Generators;

use Illuminate\Support\Str;

abstract class BaseGenerator
{
    protected string $modelName;
    protected string $modelNamePlural;
    protected string $modelNameSnake;
    protected string $modelNameSnakePlural;
    protected string $modelNameCamel;
    protected string $modelNameCamelPlural;
    protected array $fields;

    public function __construct(string $modelName, array $fields = [])
    {
        $this->modelName = Str::studly($modelName);
        $this->modelNamePlural = Str::plural($this->modelName);
        $this->modelNameSnake = Str::snake($this->modelName);
        $this->modelNameSnakePlural = Str::snake($this->modelNamePlural);
        $this->modelNameCamel = Str::camel($this->modelName);
        $this->modelNameCamelPlural = Str::camel($this->modelNamePlural);
        $this->fields = $fields;
    }

    abstract public function generate(): string;

    abstract public function getPath(): string;

    protected function getStub(string $name): string
    {
        // Check for published stub first
        $publishedPath = base_path("stubs/make-full/{$name}.stub");
        if (file_exists($publishedPath)) {
            return file_get_contents($publishedPath);
        }

        // Fall back to package stub
        $packagePath = __DIR__ . "/../Stubs/{$name}.stub";
        if (file_exists($packagePath)) {
            return file_get_contents($packagePath);
        }

        return '';
    }

    protected function replaceCommonPlaceholders(string $content): string
    {
        return str_replace([
            '{{ modelName }}',
            '{{ modelNamePlural }}',
            '{{ modelNameSnake }}',
            '{{ modelNameSnakePlural }}',
            '{{ modelNameCamel }}',
            '{{ modelNameCamelPlural }}',
            '{{ namespace }}',
        ], [
            $this->modelName,
            $this->modelNamePlural,
            $this->modelNameSnake,
            $this->modelNameSnakePlural,
            $this->modelNameCamel,
            $this->modelNameCamelPlural,
            $this->getNamespace(),
        ], $content);
    }

    protected function getNamespace(): string
    {
        return '';
    }

    protected function getFieldsForFillable(): string
    {
        if (empty($this->fields)) {
            return '';
        }

        $fieldNames = array_map(fn($f) => "'{$f['name']}'", $this->fields);
        return implode(",\n        ", $fieldNames);
    }

    protected function getFieldsForCasts(): array
    {
        $casts = [];

        foreach ($this->fields as $field) {
            $cast = $this->getCastForType($field['type']);
            if ($cast) {
                $casts[$field['name']] = $cast;
            }
        }

        return $casts;
    }

    protected function getCastForType(string $type): ?string
    {
        return match ($type) {
            'boolean', 'bool' => 'boolean',
            'integer', 'int', 'bigInteger', 'smallInteger', 'tinyInteger' => 'integer',
            'decimal', 'double', 'float' => 'float',
            'date' => 'date',
            'datetime', 'dateTime', 'timestamp' => 'datetime',
            'json', 'array' => 'array',
            default => null,
        };
    }

    protected function getValidationRuleForField(array $field, bool $isUpdate = false): string
    {
        $rules = [];

        // Required or nullable
        if ($field['nullable']) {
            $rules[] = 'nullable';
        } elseif (!$isUpdate) {
            $rules[] = 'required';
        } else {
            $rules[] = 'sometimes';
        }

        // Type rules
        $rules[] = $this->getValidationTypeRule($field['type']);

        // Unique
        if ($field['unique']) {
            $table = $this->modelNameSnakePlural;
            if ($isUpdate) {
                $rules[] = "unique:{$table},{$field['name']},{\$this->{$this->modelNameCamel}->id}";
            } else {
                $rules[] = "unique:{$table},{$field['name']}";
            }
        }

        return "'" . implode('|', array_filter($rules)) . "'";
    }

    protected function getValidationTypeRule(string $type): string
    {
        return match ($type) {
            'string', 'text', 'longText', 'mediumText' => 'string|max:255',
            'integer', 'int', 'bigInteger', 'smallInteger', 'tinyInteger' => 'integer',
            'decimal', 'double', 'float' => 'numeric',
            'boolean', 'bool' => 'boolean',
            'date' => 'date',
            'datetime', 'dateTime', 'timestamp' => 'date',
            'email' => 'email',
            'json', 'array' => 'array',
            'foreignId' => 'integer|exists:' . $this->modelNameSnakePlural . ',id',
            default => 'string',
        };
    }

    protected function getFakerMethodForField(array $field): string
    {
        $name = $field['name'];
        $type = $field['type'];

        // Check by name first
        if (str_contains($name, 'email')) return 'fake()->unique()->safeEmail()';
        if (str_contains($name, 'name') && str_contains($name, 'first')) return 'fake()->firstName()';
        if (str_contains($name, 'name') && str_contains($name, 'last')) return 'fake()->lastName()';
        if (str_contains($name, 'name')) return 'fake()->name()';
        if (str_contains($name, 'phone')) return 'fake()->phoneNumber()';
        if (str_contains($name, 'address')) return 'fake()->address()';
        if (str_contains($name, 'city')) return 'fake()->city()';
        if (str_contains($name, 'country')) return 'fake()->country()';
        if (str_contains($name, 'zip') || str_contains($name, 'postal')) return 'fake()->postcode()';
        if (str_contains($name, 'url') || str_contains($name, 'website')) return 'fake()->url()';
        if (str_contains($name, 'title')) return 'fake()->sentence(3)';
        if (str_contains($name, 'description') || str_contains($name, 'content') || str_contains($name, 'body')) return 'fake()->paragraph()';
        if (str_contains($name, 'price') || str_contains($name, 'amount') || str_contains($name, 'cost')) return 'fake()->randomFloat(2, 10, 1000)';
        if (str_contains($name, 'quantity') || str_contains($name, 'qty')) return 'fake()->numberBetween(1, 100)';
        if (str_ends_with($name, '_id')) return 'fake()->numberBetween(1, 10)';
        if (str_contains($name, 'image') || str_contains($name, 'avatar') || str_contains($name, 'photo')) return 'fake()->imageUrl()';
        if (str_contains($name, 'slug')) return 'fake()->slug()';

        // Check by type
        return match ($type) {
            'string' => 'fake()->word()',
            'text', 'longText', 'mediumText' => 'fake()->paragraph()',
            'integer', 'int', 'bigInteger', 'smallInteger', 'tinyInteger' => 'fake()->numberBetween(1, 100)',
            'decimal', 'double', 'float' => 'fake()->randomFloat(2, 1, 100)',
            'boolean', 'bool' => 'fake()->boolean()',
            'date' => 'fake()->date()',
            'datetime', 'dateTime', 'timestamp' => 'fake()->dateTime()',
            'json', 'array' => '[]',
            'foreignId' => 'fake()->numberBetween(1, 10)',
            default => 'fake()->word()',
        };
    }
}
