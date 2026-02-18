<?php

namespace Campelo\MakeFull\Generators;

class ResourceGenerator extends BaseGenerator
{
    public function generate(): string
    {
        $namespace = config('make-full.namespaces.resource', 'App\\Http\\Resources');
        $fields = $this->buildResourceFields();

        $content = <<<PHP
<?php

namespace {$namespace};

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'id' => \$this->id,
{$fields}
            'created_at' => \$this->created_at?->toIso8601String(),
            'updated_at' => \$this->updated_at?->toIso8601String(),
        ];
    }
}
PHP;

        return $content;
    }

    protected function buildResourceFields(): string
    {
        if (empty($this->fields)) {
            return '';
        }

        $lines = [];

        $customRelations = config('make-full._relations', []);
        $relationNames = [];
        foreach ($this->fields as $field) {
            $name = $field['name'];
            if ($field['foreign']) {
                $relatedModel = $field['foreign']['model'];
                $relationName = lcfirst($relatedModel);
                $lines[] = "            '{$name}' => \$this->{$name},";
                $lines[] = "            '{$relationName}' => new {$relatedModel}Resource(\$this->whenLoaded('{$relationName}')),";
                $relationNames[] = $relationName;
            } else {
                $lines[] = "            '{$name}' => \$this->{$name},";
            }
        }
        // Adiciona relações detectadas via config (caso não estejam nos fields)
        if (!empty($customRelations)) {
            foreach ($customRelations as $rel) {
                $relationName = lcfirst($rel['related']);
                if (!in_array($relationName, $relationNames)) {
                    $lines[] = "            '{$relationName}' => new {$rel['related']}Resource(\$this->whenLoaded('{$relationName}')),";
                }
            }
        }
        return implode("\n", $lines) . "\n";
    }

    public function getPath(): string
    {
        $path = config('make-full.paths.resource', 'app/Http/Resources');
        return "{$path}/{$this->modelName}Resource.php";
    }
}
