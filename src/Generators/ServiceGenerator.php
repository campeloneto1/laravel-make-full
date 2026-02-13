<?php

namespace Campelo\MakeFull\Generators;

class ServiceGenerator extends BaseGenerator
{
    protected bool $useRepository;
    protected int $defaultPagination;

    public function __construct(string $modelName, array $fields = [], bool $useRepository = true, int $defaultPagination = 15)
    {
        parent::__construct($modelName, $fields);
        $this->useRepository = $useRepository;
        $this->defaultPagination = $defaultPagination;
    }

    public function generate(): string
    {
        $namespace = config('make-full.namespaces.service', 'App\\Services');
        $modelNamespace = config('make-full.namespaces.model', 'App\\Models');

        if ($this->useRepository) {
            return $this->generateWithRepository($namespace, $modelNamespace);
        }

        return $this->generateWithModel($namespace, $modelNamespace);
    }

    protected function generateWithRepository(string $namespace, string $modelNamespace): string
    {
        $repositoryNamespace = config('make-full.namespaces.repository', 'App\\Repositories');
        $searchableFields = $this->getSearchableFields();

        $content = <<<PHP
<?php

namespace {$namespace};

use {$modelNamespace}\\{$this->modelName};
use {$repositoryNamespace}\\{$this->modelName}Repository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class {$this->modelName}Service
{
    public function __construct(
        protected {$this->modelName}Repository \$repository
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
        return \$this->repository->search(\$params);
    }

    /**
     * Get all {$this->modelNamePlural}.
     */
    public function all(): \\Illuminate\\Database\\Eloquent\\Collection
    {
        return \$this->repository->all();
    }

    /**
     * Find a {$this->modelName} by ID.
     */
    public function find(int|string \$id): ?{$this->modelName}
    {
        return \$this->repository->find(\$id);
    }

    /**
     * Find a {$this->modelName} by ID or fail.
     */
    public function findOrFail(int|string \$id): {$this->modelName}
    {
        return \$this->repository->findOrFail(\$id);
    }

    /**
     * Create a new {$this->modelName}.
     */
    public function create(array \$data): {$this->modelName}
    {
        return \$this->repository->create(\$data);
    }

    /**
     * Update an existing {$this->modelName}.
     */
    public function update({$this->modelName} \${$this->modelNameCamel}, array \$data): {$this->modelName}
    {
        return \$this->repository->update(\${$this->modelNameCamel}, \$data);
    }

    /**
     * Delete a {$this->modelName}.
     */
    public function delete({$this->modelName} \${$this->modelNameCamel}): bool
    {
        return \$this->repository->delete(\${$this->modelNameCamel});
    }
}
PHP;

        return $content;
    }

    protected function generateWithModel(string $namespace, string $modelNamespace): string
    {
        $searchConditions = $this->buildSearchConditions();
        $searchableFields = $this->getSearchableFields();

        $content = <<<PHP
<?php

namespace {$namespace};

use {$modelNamespace}\\{$this->modelName};
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class {$this->modelName}Service
{
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
        \$query = {$this->modelName}::query();

        // Search filter
        if (!empty(\$params['search'])) {
            \$search = \$params['search'];
            \$query->where(function (\$q) use (\$search) {
{$searchConditions}
            });
        }

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
    public function all(): \\Illuminate\\Database\\Eloquent\\Collection
    {
        return {$this->modelName}::all();
    }

    /**
     * Find a {$this->modelName} by ID.
     */
    public function find(int|string \$id): ?{$this->modelName}
    {
        return {$this->modelName}::find(\$id);
    }

    /**
     * Find a {$this->modelName} by ID or fail.
     */
    public function findOrFail(int|string \$id): {$this->modelName}
    {
        return {$this->modelName}::findOrFail(\$id);
    }

    /**
     * Create a new {$this->modelName}.
     */
    public function create(array \$data): {$this->modelName}
    {
        return {$this->modelName}::create(\$data);
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
        $path = config('make-full.paths.service', 'app/Services');
        return "{$path}/{$this->modelName}Service.php";
    }
}
