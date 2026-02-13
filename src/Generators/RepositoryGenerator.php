<?php

namespace Campelo\MakeFull\Generators;

class RepositoryGenerator extends BaseGenerator
{
    protected int $defaultPagination;

    public function __construct(string $modelName, array $fields = [], int $defaultPagination = 15)
    {
        parent::__construct($modelName, $fields);
        $this->defaultPagination = $defaultPagination;
    }

    public function generate(): string
    {
        $namespace = config('make-full.namespaces.repository', 'App\\Repositories');
        $modelNamespace = config('make-full.namespaces.model', 'App\\Models');
        $searchConditions = $this->buildSearchConditions();
        $searchableFields = $this->getSearchableFields();

        $content = <<<PHP
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

    /**
     * Search and paginate {$this->modelNamePlural}.
     *
     * @param array \$params Search parameters
     *   - search: string - Search term for {$searchableFields}
     *   - limit: int - Items per page (default: {$this->defaultPagination})
     *   - sort: string - Sort field
     *   - order: string - Sort order (asc/desc)
     */
    public function search(array \$params = []): LengthAwarePaginator
    {
        \$query = \$this->model->newQuery();

        // Search filter
        if (!empty(\$params['search'])) {
            \$search = \$params['search'];
            \$query->where(function (\$q) use (\$search) {
{$searchConditions}
            });
        }

        // Individual field filters
{$this->buildFieldFilters()}

        // Sorting
        \$sortField = \$params['sort'] ?? 'created_at';
        \$sortOrder = \$params['order'] ?? 'desc';
        \$query->orderBy(\$sortField, \$sortOrder);

        // Pagination
        \$limit = \$params['limit'] ?? {$this->defaultPagination};

        return \$query->paginate(\$limit);
    }

    /**
     * Get all {$this->modelNamePlural}.
     */
    public function all(): Collection
    {
        return \$this->model->all();
    }

    /**
     * Find a {$this->modelName} by ID.
     */
    public function find(int|string \$id): ?{$this->modelName}
    {
        return \$this->model->find(\$id);
    }

    /**
     * Find a {$this->modelName} by ID or fail.
     */
    public function findOrFail(int|string \$id): {$this->modelName}
    {
        return \$this->model->findOrFail(\$id);
    }

    /**
     * Create a new {$this->modelName}.
     */
    public function create(array \$data): {$this->modelName}
    {
        return \$this->model->create(\$data);
    }

    /**
     * Update an existing {$this->modelName}.
     */
    public function update({$this->modelName} \${$this->modelNameCamel}, array \$data): {$this->modelName}
    {
        \${$this->modelNameCamel}->update(\$data);

        return \${$this->modelNameCamel}->fresh();
    }

    /**
     * Delete a {$this->modelName}.
     */
    public function delete({$this->modelName} \${$this->modelNameCamel}): bool
    {
        return \${$this->modelNameCamel}->delete();
    }

    /**
     * Find by a specific field.
     */
    public function findBy(string \$field, mixed \$value): ?{$this->modelName}
    {
        return \$this->model->where(\$field, \$value)->first();
    }

    /**
     * Get records where field matches value.
     */
    public function getBy(string \$field, mixed \$value): Collection
    {
        return \$this->model->where(\$field, \$value)->get();
    }
}
PHP;

        return $content;
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
            return "        // Add individual field filters";
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

        if (empty($fields)) {
            return 'searchable fields';
        }

        return implode(', ', $fields);
    }

    public function getPath(): string
    {
        $path = config('make-full.paths.repository', 'app/Repositories');
        return "{$path}/{$this->modelName}Repository.php";
    }
}
