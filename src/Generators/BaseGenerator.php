<?php

namespace Campelo\MakeFull\Generators;

use Illuminate\Support\Str;
use InvalidArgumentException;

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

        $this->fields = array_map(function ($field) {
            $this->validateFieldStructure($field);

            return array_merge([
                'nullable' => false,
                'unique' => false,
                'length' => null,
                'precision' => 2,
                'foreign' => null,
            ], $field);
        }, $fields);
    }

    abstract public function generate(): string;

    abstract public function getPath(): string;

    protected function getStub(string $name): string
    {
        $publishedPath = base_path("stubs/make-full/{$name}.stub");
        if (file_exists($publishedPath)) {
            return file_get_contents($publishedPath);
        }

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

        $fieldNames = array_filter($this->fields, fn($f) =>
            !in_array($f['name'], ['id', 'created_at', 'updated_at', 'deleted_at'])
        );

        $fieldNames = array_map(fn($f) => "'{$f['name']}'", $fieldNames);

        return implode(",\n        ", $fieldNames);
    }

    protected function getFieldsForCasts(): array
    {
        $casts = [];

        foreach ($this->fields as $field) {
            $cast = $this->getCastForType($field);
            if ($cast) {
                $casts[$field['name']] = $cast;
            }
        }

        return $casts;
    }

    protected function getCastForType(array $field): ?string
    {
        return match ($field['type']) {
            'boolean', 'bool' => 'boolean',
            'integer', 'int', 'bigInteger', 'smallInteger', 'tinyInteger' => 'integer',
            'decimal', 'double', 'float' => 'decimal:' . ($field['precision'] ?? 2),
            'date' => 'date:Y-m-d',
            'datetime', 'dateTime', 'timestamp' => 'datetime:Y-m-d H:i:s',
            'json', 'array' => 'array',
            default => null,
        };
    }

    protected function getValidationRuleForField(array $field, bool $isUpdate = false): string
    {
        $rules = [];

        if ($isUpdate) {
            $rules[] = 'sometimes';
        } elseif (!$field['nullable']) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }

        $typeRule = $this->getValidationTypeRule($field);
        if ($typeRule) {
            $rules[] = $typeRule;
        }

        if ($field['unique']) {
            $table = $this->modelNameSnakePlural;

            if ($isUpdate) {
                $rules[] = "unique:{$table},{$field['name']},\$this->route('{$this->modelNameCamel}'),id";
            } else {
                $rules[] = "unique:{$table},{$field['name']}";
            }
        }

        return "'" . implode('|', array_filter($rules)) . "'";
    }

    protected function getValidationTypeRule(array $field): string
    {
        if ($field['type'] === 'foreignId' && !empty($field['foreign']['model'])) {
            $table = Str::snake(Str::plural($field['foreign']['model']));
            return "integer|exists:{$table},id";
        }

        return match ($field['type']) {
            'string' => 'string|max:' . ($field['length'] ?? 255),
            'text', 'longText', 'mediumText' => 'string',
            'integer', 'int', 'bigInteger', 'smallInteger', 'tinyInteger' => 'integer',
            'decimal', 'double', 'float' => 'numeric',
            'boolean', 'bool' => 'boolean',
            'date' => 'date',
            'datetime', 'dateTime', 'timestamp' => 'date',
            'email' => 'email|max:' . ($field['length'] ?? 255),
            'json', 'array' => 'array',
            default => 'string',
        };
    }

    protected function getFakerMethodForField(array $field): string
    {
        $name = $field['name'];
        $type = $field['type'];

        if (Str::contains($name, 'email')) return 'fake()->unique()->safeEmail()';
        if (Str::contains($name, 'name') && Str::contains($name, 'first')) return 'fake()->firstName()';
        if (Str::contains($name, 'name') && Str::contains($name, 'last')) return 'fake()->lastName()';
        if (Str::contains($name, 'name')) return 'fake()->name()';
        if (Str::contains($name, 'phone')) return 'fake()->phoneNumber()';
        if (Str::contains($name, 'address')) return 'fake()->address()';
        if (Str::contains($name, 'city')) return 'fake()->city()';
        if (Str::contains($name, 'country')) return 'fake()->country()';
        if (Str::contains($name, ['zip', 'postal'])) return 'fake()->postcode()';
        if (Str::contains($name, ['url', 'website'])) return 'fake()->url()';
        if (Str::contains($name, 'title')) return 'fake()->sentence(3)';
        if (Str::contains($name, ['description', 'content', 'body'])) return 'fake()->paragraph()';
        if (Str::contains($name, ['price', 'amount', 'cost'])) return 'fake()->randomFloat(2, 10, 1000)';
        if (Str::contains($name, ['quantity', 'qty'])) return 'fake()->numberBetween(1, 100)';
        if (Str::endsWith($name, '_id') && !empty($field['foreign']['model'])) {
            return $field['foreign']['model'] . '::factory()';
        }
        if (Str::contains($name, ['image', 'avatar', 'photo'])) return 'fake()->imageUrl()';
        if (Str::contains($name, 'slug')) return 'fake()->slug()';

        return match ($type) {
            'string' => 'fake()->word()',
            'text', 'longText', 'mediumText' => 'fake()->paragraph()',
            'integer', 'int', 'bigInteger', 'smallInteger', 'tinyInteger' => 'fake()->numberBetween(1, 100)',
            'decimal', 'double', 'float' => 'fake()->randomFloat(2, 1, 100)',
            'boolean', 'bool' => 'fake()->boolean()',
            'date' => 'fake()->date()',
            'datetime', 'dateTime', 'timestamp' => 'fake()->dateTime()',
            'json', 'array' => "['key' => fake()->word()]",
            default => 'fake()->word()',
        };
    }

    protected function validateFieldStructure(array $field): void
    {
        if (!isset($field['name'], $field['type'])) {
            throw new InvalidArgumentException("Field must contain 'name' and 'type'.");
        }
    }
}
