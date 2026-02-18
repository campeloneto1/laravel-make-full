<?php

namespace Campelo\MakeFull\Generators;

class RepositoryGenerator extends BaseGenerator
{
    protected int $defaultPagination;
    protected string $modelNameCamel;

    public function __construct(
        string $modelName,
        array $fields = [],
        int $defaultPagination = 15
    ) {
        parent::__construct($modelName, $fields);

        $this->defaultPagination = $defaultPagination;
        $this->modelNameCamel = lcfirst($modelName);
    }

    public function generate(): string
    {
        $namespace = config('make-full.namespaces.repository', 'App\\Repositories');
        $modelNamespace = config('make-full.namespaces.model', 'App\\Models');
        $searchConditions = $this->buildSearchConditions();
        $fieldFilters = $this->buildFieldFilters();
        $searchableFields = $this->getSearchableFields();

        return <<<PHP
<?php

namespace {$namespace};

use {$modelNamespace}\\{$this->modelName};
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class {$this->modelName}Repository
{
    public function __construct(
        protected {$this->modelName} \$model
    ) {}

    public function search(array \$params = []): LengthAwarePaginator
    {
        \$params = \$this->normalizeParams(\$params);

        \$query = \$this->model->newQuery();

        // Search
        if (!empty(\$params['search'])) {
            \$search = \$params['search'];

            \$query->where(function (\$q) use (\$search) {
{$searchConditions}
            });
        }

        // Field Filters
{$fieldFilters}

        // Sorting
        if (in_array(\$params['sort'], \$this->allowedSorts())) {
            \$query->orderBy(\$params['sort'], \$params['order']);
        }

        return \$query->paginate(\$params['limit']);
    }

    public function all(): Collection
    {
        return \$this->model->all();
    }

    public function find(int|string \$id): ?{$this->modelName}
    {
        return \$this->model->find(\$id);
    }

    public function findOrFail(int|string \$id): {$this->modelName}
    {
        return \$this->model->findOrFail(\$id);
    }

    public function create(array \$data): {$this->modelName}
    {
        return \$this->model->create(\$data);
    }

    public function update({$this->modelName} \${$this->modelNameCamel}, array \$data): {$this->modelName}
    {
        \${$this->modelNameCamel}->update(\$data);

        return \${$this->modelNameCamel}->fresh();
    }

    public function delete({$this->modelName} \${$this->modelNameCamel}): bool
    {
        return \${$this->modelNameCamel}->delete();
    }

    public function findBy(string \$field, mixed \$value): ?{$this->modelName}
    {
        return \$this->model->where(\$field, \$value)->first();
    }

    public function getBy(string \$field, mixed \$value): Collection
    {
        return \$this->model->where(\$field, \$value)->get();
    }

    protected function normalizeParams(array \$params): array
    {
        \$params['limit'] = \$params['limit'] ?? {$this->defaultPagination};
        \$params['sort'] = \$params['sort'] ?? 'created_at';
        \$params['order'] = in_array(strtolower(\$params['order'] ?? 'desc'), ['asc', 'desc'])
            ? strtolower(\$params['order'])
            : 'desc';

        return \$params;
    }

    protected function allowedSorts(): array
    {
        return [
            'id',
            'created_at',
            'updated_at',
        ];
    }
}
PHP;
    }

    protected function buildSearchConditions(): string
    {
        $searchableTypes = ['string', 'text', 'longText', 'mediumText'];
        $conditions = [];
        $first = true;

        foreach ($this->fields as $field) {
            if (in_array($field['type'], $searchableTypes)) {
                $method = $first ? 'where' : 'orWhere';
                $conditions[] = "                \$q->{$method}('{$field['name']}', 'like', \"%{\$search}%\");";
                $first = false;
            }
        }

        if (empty($conditions)) {
            return "                // Add search conditions for your fields";
        }

        return implode("\n", $conditions);
    }

    protected function buildFieldFilters(): string
    {
        $filters = [];

        foreach ($this->fields as $field) {
            $name = $field['name'];

            $filters[] = <<<PHP
        if (isset(\$params['{$name}'])) {
            \$query->where('{$name}', \$params['{$name}']);
        }
PHP;
        }

        if (empty($filters)) {
            return "        // Add field filters here";
        }

        return implode("\n\n", $filters);
    }

    protected function getSearchableFields(): string
    {
        $searchableTypes = ['string', 'text', 'longText', 'mediumText'];
        $fields = [];

        foreach ($this->fields as $field) {
            if (in_array($field['type'], $searchableTypes)) {
                $fields[] = $field['name'];
            }
        }

        return empty($fields)
            ? 'searchable fields'
            : implode(', ', $fields);
    }

    public function getPath(): string
    {
        $path = config('make-full.paths.repository', 'app/Repositories');

        return "{$path}/{$this->modelName}Repository.php";
    }
}
