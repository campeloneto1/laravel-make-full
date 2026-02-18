<?php

namespace Campelo\MakeFull\Generators;

class ResourceGenerator extends BaseGenerator
{
    public function generate(): string
    {
        $namespace = config('make-full.namespaces.resource', 'App\\Http\\Resources');
        $fields = $this->buildResourceFields();
        $relationUses = $this->buildRelationUses();

        return <<<PHP
<?php

namespace {$namespace};

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
{$relationUses}

class {$this->modelName}Resource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request \$request): array
    {
        return [
            'id' => \$this->getKey(),
{$fields}
            'created_at' => \$this->created_at?->toISOString(),
            'updated_at' => \$this->updated_at?->toISOString(),
        ];
    }
}
PHP;
    }

    protected function buildResourceFields(): string
    {
        if (empty($this->fields)) {
            return '';
        }

        $lines = [];
        $addedRelations = [];

        foreach ($this->fields as $field) {
            $name = $field['name'];

            if (!empty($field['foreign'])) {
                $relatedModel = $field['foreign']['model'];
                $relationName = lcfirst($relatedModel);

                // Campo FK
                $lines[] = "            '{$name}' => \$this->{$name},";

                // Relação
                if (!in_array($relationName, $addedRelations)) {
                    $lines[] = "            '{$relationName}' => {$relatedModel}Resource::make(\$this->whenLoaded('{$relationName}')),";
                    $addedRelations[] = $relationName;
                }

                continue;
            }

            $lines[] = "            '{$name}' => \$this->{$name},";
        }

        // Relações customizadas via config
        $customRelations = config('make-full._relations', []);
        foreach ($customRelations as $rel) {
            $relationName = lcfirst($rel['related']);

            if (!in_array($relationName, $addedRelations)) {
                $lines[] = "            '{$relationName}' => {$rel['related']}Resource::make(\$this->whenLoaded('{$relationName}')),";
                $addedRelations[] = $relationName;
            }
        }

        return implode("\n", $lines) . "\n";
    }

    protected function buildRelationUses(): string
    {
        $relations = [];
        $added = [];

        foreach ($this->fields as $field) {
            if (!empty($field['foreign'])) {
                $model = $field['foreign']['model'];

                if (!in_array($model, $added)) {
                    $relations[] = "use App\\Http\\Resources\\{$model}Resource;";
                    $added[] = $model;
                }
            }
        }

        $customRelations = config('make-full._relations', []);
        foreach ($customRelations as $rel) {
            if (!in_array($rel['related'], $added)) {
                $relations[] = "use App\\Http\\Resources\\{$rel['related']}Resource;";
                $added[] = $rel['related'];
            }
        }

        return implode("\n", $relations);
    }

    public function getPath(): string
    {
        $path = config('make-full.paths.resource', 'app/Http/Resources');

        return "{$path}/{$this->modelName}Resource.php";
    }
}
