<?php

namespace Campelo\MakeFull\Console\Commands;

use Illuminate\Console\Command;

class RegisterPoliciesCommand extends Command
{
    protected $signature = 'make-full:register-policies';
    protected $description = 'Verifica ou cria AuthServiceProvider e registra automaticamente as policies dos models existentes';

    public function handle(): int
    {
        $providerPath = app_path('Providers/AuthServiceProvider.php');
        $modelsPath = app_path('Models');
        $policiesPath = app_path('Policies');

        if (!is_dir($modelsPath) || !is_dir($policiesPath)) {
            $this->warn('Diretório Models ou Policies não encontrado.');
            return self::SUCCESS;
        }

        $models = collect(glob($modelsPath . '/*.php'))
            ->map(fn ($file) => pathinfo($file, PATHINFO_FILENAME))
            ->filter();

        $policyMappings = $models
            ->filter(fn ($model) => file_exists($policiesPath . "/{$model}Policy.php"))
            ->map(fn ($model) => "        \\App\\Models\\{$model}::class => \\App\\Policies\\{$model}Policy::class,")
            ->sort()
            ->values()
            ->toArray();

        if (empty($policyMappings)) {
            $this->warn('Nenhuma policy correspondente encontrada.');
            return self::SUCCESS;
        }

        if (!file_exists($providerPath)) {
            $this->createProvider($providerPath, $policyMappings);
            $this->info('AuthServiceProvider criado com sucesso.');
            return self::SUCCESS;
        }

        $this->updateProvider($providerPath, $policyMappings);
        $this->info('AuthServiceProvider atualizado com sucesso.');

        return self::SUCCESS;
    }

    protected function createProvider(string $path, array $policyMappings): void
    {
        $content = <<<PHP
<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected \$policies = [
{$this->implodePolicies($policyMappings)}
    ];

    public function boot(): void
    {
        \$this->registerPolicies();
    }
}

PHP;

        file_put_contents($path, $content);
    }

    protected function updateProvider(string $path, array $policyMappings): void
    {
        $content = file_get_contents($path);

        $newPoliciesBlock = "protected \$policies = [\n" .
            $this->implodePolicies($policyMappings) .
            "\n    ];";

        if (preg_match('/protected \$policies = \[.*?\];/s', $content)) {
            $content = preg_replace(
                '/protected \$policies = \[.*?\];/s',
                $newPoliciesBlock,
                $content
            );
        } else {
            $content = preg_replace(
                '/class AuthServiceProvider extends ServiceProvider\s*\{/s',
                "class AuthServiceProvider extends ServiceProvider\n{\n    {$newPoliciesBlock}\n",
                $content
            );
        }

        file_put_contents($path, $content);
    }

    protected function implodePolicies(array $policies): string
    {
        return implode("\n", $policies);
    }
}
