<?php

namespace Campelo\MakeFull\Generators;

class PolicyGenerator extends BaseGenerator
{
    public function generate(): string
    {
        $namespace = config('make-full.namespaces.policy', 'App\\Policies');
        $modelNamespace = config('make-full.namespaces.model', 'App\\Models');

        $content = <<<PHP
<?php

namespace {$namespace};

use {$modelNamespace}\\{$this->modelName};
use App\\Models\\User;
use Illuminate\\Auth\\Access\\HandlesAuthorization;

class {$this->modelName}Policy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User \$user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User \$user, {$this->modelName} \${$this->modelNameCamel}): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User \$user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User \$user, {$this->modelName} \${$this->modelNameCamel}): bool
    {
        return true;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User \$user, {$this->modelName} \${$this->modelNameCamel}): bool
    {
        return true;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User \$user, {$this->modelName} \${$this->modelNameCamel}): bool
    {
        return true;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User \$user, {$this->modelName} \${$this->modelNameCamel}): bool
    {
        return true;
    }
}
PHP;

        return $content;
    }

    public function getPath(): string
    {
        $path = config('make-full.paths.policy', 'app/Policies');
        return "{$path}/{$this->modelName}Policy.php";
    }
}
