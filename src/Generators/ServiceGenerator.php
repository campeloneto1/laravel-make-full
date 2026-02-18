<?php

namespace Campelo\MakeFull\Generators;

class ServiceGenerator extends BaseGenerator
{
    protected bool $useRepository;
    protected int $defaultPagination;
    protected string $modelNameCamel;

    public function __construct(
        string $modelName,
        array $fields = [],
        bool $useRepository = true,
        int $defaultPagination = 15
    ) {
        parent::__construct($modelName, $fields);

        $this->useRepository = $useRepository;
        $this->defaultPagination = $defaultPagination;
        $this->modelNameCamel = lcfirst($modelName);
    }

    public function generate(): string
    {
        $namespace = config('make-full.namespaces.service', 'App\\Services');
        $modelNamespace = config('make-full.namespaces.model', 'App\\Models');

        return $this->useRepository
            ? $this->generateWithRepository($namespace, $modelNamespace)
            : $this->generateWithModel($namespace, $modelNamespace);
    }

    protected function generateWithRepository(string $namespace, string $modelNamespace): string
    {
        $repositoryNamespace = config('make-full.namespaces.repository', 'App\\Repositories');
        $searchableFields = $this->getSearchableFields();

        return <<<PHP
<?php

namespace {$namespace};

use {$modelNamespace}\\{$this->modelName};
use {$repositoryNamespace}\\{$this->modelName}Repository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class {$this->modelName}Service
{
    public function __construct(
        protected {$this->modelName}Repository \$repository
    ) {}

    public function search(array \$params = []): LengthAwarePaginator
    {
        \$params = \$this->normalizeParams(\$params);

        return \$this->repository->search(\$params);
    }

    public function all(): Collection
    {
        return \$this->repository->all();
    }

    public function find(int|string \$id): ?{$this->modelName}
    {
        return \$this->repository->find(\$id);
    }

    public function findOrFail(int|string \$id): {$this->modelName}
    {
        return \$this->repository->findOrFail(\$id);
    }

    public function create(array \$data): {$this->modelName}
    {
        return \$this->repository->create(\$data);
    }

    public function update({$this->modelName} \${$this->modelNameCamel}, array \$data): {$this->modelName}
    {
        return \$this->repository->update(\${$this->modelNameCamel}, \$data);
    }

    public function delete({$this->modelName} \${$this->modelNameCamel}): bool
    {
        return \$this->repository->delete(\${$this->modelNameCamel});
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
}
PHP;
    }

    protected function generateWithModel(string $namespace, string $modelNamespace): string
    {
        $searchConditions = $this->buildSearchConditions();
        $searchableFields = $this->getSearchableFields();

        return <<<PHP
<?php

namespace {$namespace};

use {$modelNamespace}\\{$this->modelName};
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class {$this->modelName}Service
{
    public function search(array \$params = []): LengthAwarePaginator
    {
        \$params = \$this->normalizeParams(\$params);

        \$query = {$this->modelName}::query();

        if (!empty(\$params['search'])) {
            \$search = \$params['search'];

            \$query->where(function (\$q) use (\$search) {
{$searchConditions}
            });
        }

        \$query->orderBy(\$params['sort'], \$params['order']);

        return \$query->paginate(\$params['limit']);
    }

    public function all(): Collection
    {
        return {$this->modelName}::all();
    }

    public function find(int|string \$id): ?{$this->modelName}
    {
        return {$this->modelName}::find(\$id);
    }

    public function findOrFail(int|string \$id): {$this->modelName}
    {
        return {$this->modelName}::findOrFail(\$id);
    }

    public function create(array \$data): {$this->modelName}
    {
        return {$this->modelName}::create(\$data);
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

    protected function normalizeParams(array \$params): array
    {
        \$params['limit'] = \$params['limit'] ?? {$this->defaultPagination};
        \$params['sort'] = \$params['sort'] ?? 'created_at';
        \$params['order'] = in_array(strtolower(\$params['order'] ?? 'desc'), ['asc', 'desc'])
            ? strtolower(\$params['order'])
            : 'desc';

        return \$params;
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
        $path = config('make-full.paths.service', 'app/Services');

        return "{$path}/{$this->modelName}Service.php";
    }
}
