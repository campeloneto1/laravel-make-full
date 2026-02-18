<?php

namespace Campelo\MakeFull\Generators;

class ControllerGenerator extends BaseGenerator
{
    protected bool $isApi;
    protected bool $useRepository;
    protected string $modelNameCamel;

    public function __construct(
        string $modelName,
        array $fields = [],
        bool $isApi = true,
        bool $useRepository = true
    ) {
        parent::__construct($modelName, $fields);

        $this->isApi = $isApi;
        $this->useRepository = $useRepository;
        $this->modelNameCamel = lcfirst($modelName);
    }

    public function generate(): string
    {
        $namespace = config('make-full.namespaces.controller', 'App\\Http\\Controllers');
        $serviceNamespace = config('make-full.namespaces.service', 'App\\Services');
        $resourceNamespace = config('make-full.namespaces.resource', 'App\\Http\\Resources');
        $requestNamespace = config('make-full.namespaces.request', 'App\\Http\\Requests');
        $modelNamespace = config('make-full.namespaces.model', 'App\\Models');

        return <<<PHP
<?php

namespace {$namespace};

use {$modelNamespace}\\{$this->modelName};
use {$serviceNamespace}\\{$this->modelName}Service;
use {$resourceNamespace}\\{$this->modelName}Resource;
use {$requestNamespace}\\Store{$this->modelName}Request;
use {$requestNamespace}\\Update{$this->modelName}Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class {$this->modelName}Controller extends Controller
{
    public function __construct(
        protected {$this->modelName}Service \$service
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request \$request): AnonymousResourceCollection
    {
        \$result = \$this->service->search(\$request->all());

        return {$this->modelName}Resource::collection(\$result);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Store{$this->modelName}Request \$request): {$this->modelName}Resource
    {
        \${$this->modelNameCamel} = \$this->service->create(\$request->validated());

        return new {$this->modelName}Resource(\${$this->modelNameCamel});
    }

    /**
     * Display the specified resource.
     */
    public function show({$this->modelName} \${$this->modelNameCamel}): {$this->modelName}Resource
    {
        return new {$this->modelName}Resource(\${$this->modelNameCamel});
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        Update{$this->modelName}Request \$request,
        {$this->modelName} \${$this->modelNameCamel}
    ): {$this->modelName}Resource {
        \${$this->modelNameCamel} = \$this->service->update(
            \${$this->modelNameCamel},
            \$request->validated()
        );

        return new {$this->modelName}Resource(\${$this->modelNameCamel});
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy({$this->modelName} \${$this->modelNameCamel}): JsonResponse
    {
        \$this->service->delete(\${$this->modelNameCamel});

        return response()->json(null, 204);
    }
}
PHP;
    }

    public function getPath(): string
    {
        $path = config('make-full.paths.controller', 'app/Http/Controllers');

        return "{$path}/{$this->modelName}Controller.php";
    }
}
