<?php

namespace Campelo\MakeFull\Generators;

class MigrationGenerator extends BaseGenerator
{
    protected string $tableName;
    protected bool $softDeletes;
    protected bool $uuid;

    public function __construct(string $modelName, string $tableName, array $fields = [], bool $softDeletes = false, bool $uuid = false)
    {
        parent::__construct($modelName, $fields);
        $this->tableName = $tableName;
        $this->softDeletes = $softDeletes;
        $this->uuid = $uuid;
    }

    public function generate(): string
    {
        $columns = $this->buildColumns();
        $foreignKeys = $this->buildForeignKeys();
        $softDeletesLine = $this->softDeletes ? "\n            \$table->softDeletes();" : '';
        $timestamps = config('make-full.timestamps', true) ? "\n            \$table->timestamps();" : '';
        $primaryKey = $this->uuid ? "\$table->uuid('id')->primary();" : "\$table->id();";

        $content = <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{$this->tableName}', function (Blueprint \$table) {
            {$primaryKey}
{$columns}{$foreignKeys}{$softDeletesLine}{$timestamps}
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$this->tableName}');
    }
};
PHP;

        return $content;
    }

    public function getPath(): string
    {
        $timestamp = date('Y_m_d_His');
        $path = config('make-full.paths.migration', 'database/migrations');
        return "{$path}/{$timestamp}_create_{$this->tableName}_table.php";
    }

    protected function buildColumns(): string
    {
        if (empty($this->fields)) {
            return '';
        }

        $lines = [];

        foreach ($this->fields as $field) {
            $line = $this->buildColumnLine($field);
            $lines[] = "            {$line}";
        }

        return implode("\n", $lines);
    }

    protected function buildColumnLine(array $field): string
    {
        $name = $field['name'];
        $type = $field['type'];

        // Handle foreign keys
        if ($type === 'foreignId' || str_ends_with($name, '_id')) {
            $line = "\$table->foreignId('{$name}')";
        } else {
            $method = $this->getSchemaMethod($type);
            $line = "\$table->{$method}('{$name}')";
        }

        // Add modifiers
        if ($field['nullable']) {
            $line .= '->nullable()';
        }

        if ($field['unique']) {
            $line .= '->unique()';
        }

        if ($field['index']) {
            $line .= '->index()';
        }

        if ($field['default'] !== null) {
            $default = $this->formatDefault($field['default'], $type);
            $line .= "->default({$default})";
        }

        return $line . ';';
    }

    protected function buildForeignKeys(): string
    {
        $lines = [];

        foreach ($this->fields as $field) {
            if ($field['foreign']) {
                $table = $field['foreign']['table'];
                $lines[] = "\n            \$table->foreign('{$field['name']}')->references('id')->on('{$table}')->onDelete('cascade');";
            }
        }

        return implode('', $lines);
    }

    protected function getSchemaMethod(string $type): string
    {
        return match ($type) {
            'int' => 'integer',
            'bool' => 'boolean',
            'datetime' => 'dateTime',
            default => $type,
        };
    }

    protected function formatDefault($value, string $type): string
    {
        if ($value === 'null') {
            return 'null';
        }

        if (in_array($type, ['boolean', 'bool'])) {
            return $value === 'true' ? 'true' : 'false';
        }

        if (in_array($type, ['integer', 'int', 'bigInteger', 'smallInteger', 'tinyInteger', 'decimal', 'double', 'float'])) {
            return $value;
        }

        return "'{$value}'";
    }
}
