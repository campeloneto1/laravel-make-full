<?php

namespace Campelo\MakeFull\Generators;

class ModelGenerator extends BaseGenerator
{
    protected bool $softDeletes;
    protected bool $uuid;

    public function __construct(
        string $modelName,
        array $fields = [],
        bool $softDeletes = false,
        bool $uuid = false
    ) {
        parent::__construct($modelName, $fields);

        $this->softDeletes = $softDeletes;
        $this->uuid = $uuid;
    }

    public function generate(): string
    {
        $namespace = config('make-full.namespaces.model', 'App\\Models');

        $uses = [
            "use Illuminate\\Database\\Eloquent\\Model;",
            "use Illuminate\\Database\\Eloquent\\Factories\\HasFactory;",
        ];

        $traits = ['HasFactory'];

        if ($this->softDeletes) {
            $uses[] = "use Illuminate\\Database\\Eloquent\\SoftDeletes;";
            $traits[] = 'SoftDeletes';
        }

        if ($this->uuid) {
            $uses[] = "use Illuminate\\Database\\Eloquent\\Concerns\\HasUuids;";
            $traits[] = 'HasUuids';
        }

        $fillable = $this->buildFillable();
        $casts = $this->buildCasts();
        $relations = $this->buildRelations();

        $uuidConfig = $this->uuid ? $this->buildUuidConfig() : '';

        return <<<PHP
<?php

namespace {$namespace};

{$this->implodeUses($uses)}

class {$this->modelName} extends Model
{
    use {$this->implodeTraits($traits)};

{$uuidConfig}
{$fillable}
{$casts}
{$relations}
}
PHP;
    }

    public function getPath(): string
    {
        $path = config('make-full.paths.model', 'app/Models');

        return "{$path}/{$this->modelName}.php";
    }

    protected function buildUuidConfig(): string
    {
        return <<<PHP
    public \$incrementing = false;

    protected \$keyType = 'string';

PHP;
    }

    protected function implodeUses(array $uses): string
    {
        return implode("\n", array_unique($uses));
    }

    protected function implodeTraits(array $traits): string
    {
        return implode(', ', array_unique($traits));
    }

    protected function buildFillable(): string
    {
        if (empty($this->fields)) {
            return "    protected \$fillable = [];\n";
        }

        $fields = array_map(
            fn($f) => "        '{$f['name']}'",
            $this->fields
        );

        $fieldsList = implode(",\n", $fields);

        return <<<PHP
    protected \$fillable = [
{$fieldsList},
    ];

PHP;
    }

    protected function buildCasts(): string
    {
        $casts = $this->getFieldsForCasts();

        if (empty($casts)) {
            return '';
        }

        $castLines = [];
        foreach ($casts as $field => $cast) {
            $castLines[] = "        '{$field}' => '{$cast}'";
        }

        $castsStr = implode(",\n", $castLines);

        return <<<PHP
    protected \$casts = [
{$castsStr},
    ];

PHP;
    }

    protected function buildRelations(): string
    {
        $relations = [];
        $added = [];

        foreach ($this->fields as $field) {
            if (!empty($field['foreign'])) {
                $relatedModel = $field['foreign']['model'];
                $methodName = lcfirst($relatedModel);

                if (in_array($methodName, $added)) {
                    continue;
                }

                $relations[] = <<<PHP
    public function {$methodName}(): \\Illuminate\\Database\\Eloquent\\Relations\\BelongsTo
    {
        return \$this->belongsTo({$relatedModel}::class);
    }

PHP;

                $added[] = $methodName;
            }
        }

        return implode("\n", $relations);
    }
}
