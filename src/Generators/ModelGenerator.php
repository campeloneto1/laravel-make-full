<?php

namespace Campelo\MakeFull\Generators;

class ModelGenerator extends BaseGenerator
{
    protected bool $softDeletes;
    protected bool $uuid;

    public function __construct(string $modelName, array $fields = [], bool $softDeletes = false, bool $uuid = false)
    {
        parent::__construct($modelName, $fields);
        $this->softDeletes = $softDeletes;
        $this->uuid = $uuid;
    }

    public function generate(): string
    {
        $namespace = config('make-full.namespaces.model', 'App\\Models');
        $uses = ["use Illuminate\\Database\\Eloquent\\Factories\\HasFactory;"];
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

        // Adiciona belongsTo para relações detectadas
        $customRelations = config('make-full._relations', []);
        $relationsCode = $relations;
        if (!empty($customRelations)) {
            foreach ($customRelations as $rel) {
                $relatedModel = $rel['related'];
                $methodName = lcfirst($relatedModel);
                $relationsCode .= <<<PHP

    public function {$methodName}(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return \$this->belongsTo({$relatedModel}::class);
    }
PHP;
            }
        }

        $content = <<<PHP
<?php

namespace {$namespace};

use Illuminate\Database\Eloquent\Model;
{$this->implodeUses($uses)}

class {$this->modelName} extends Model
{
    use {$this->implodeTraits($traits)};

{$fillable}
{$casts}
{$relationsCode}
}
PHP;

        return $content;
    }

    public function getPath(): string
    {
        $path = config('make-full.paths.model', 'app/Models');
        return "{$path}/{$this->modelName}.php";
    }

    protected function implodeUses(array $uses): string
    {
        return implode("\n", $uses);
    }

    protected function implodeTraits(array $traits): string
    {
        return implode(', ', $traits);
    }

    protected function buildFillable(): string
    {
        if (empty($this->fields)) {
            return "    protected \$fillable = [];";
        }

        $fields = array_map(fn($f) => "        '{$f['name']}'", $this->fields);
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

        foreach ($this->fields as $field) {
            if ($field['foreign']) {
                $relatedModel = $field['foreign']['model'];
                $methodName = lcfirst($relatedModel);

                $relations[] = <<<PHP

    public function {$methodName}(): \\Illuminate\\Database\\Eloquent\\Relations\\BelongsTo
    {
        return \$this->belongsTo({$relatedModel}::class);
    }
PHP;
            }
        }

        return implode("\n", $relations);
    }
}
