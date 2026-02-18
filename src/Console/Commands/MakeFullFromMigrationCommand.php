<?php

namespace Campelo\MakeFull\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class MakeFullFromMigrationCommand extends Command
{
    protected $signature = 'make:full-from-migration 
                            {--path= : Caminho das migrations (padrão: database/migrations)}';

    protected $description = 'Gera Request, Resource, Controller, Service, Repository e Policy para todas as migrations do projeto';

    public function handle(): int
    {
        $path = $this->option('path') ?: base_path('database/migrations');

        if (!is_dir($path)) {
            $this->error("Diretório de migrations não encontrado: {$path}");
            return self::FAILURE;
        }

        $files = glob($path . '/*.php');

        if (empty($files)) {
            $this->error("Nenhuma migration encontrada em: {$path}");
            return self::FAILURE;
        }

        $migrations = $this->collectMigrations($files);

        if (empty($migrations)) {
            $this->warn('Nenhuma migration válida encontrada.');
            return self::SUCCESS;
        }

        foreach ($migrations as $migration) {

            $this->info("Gerando estrutura para: {$migration['model']}");

            // 🔥 Garantir isolamento total
            config()->offsetUnset('make-full._relations');

            Artisan::call('make:full', [
                'name' => $migration['model'],
                '--fields' => $migration['fieldsString'],
                '--no-migration' => true,
            ]);

            $this->line(Artisan::output());
        }

        $this->info('Processamento finalizado com sucesso.');
        return self::SUCCESS;
    }

    /**
     * Coleta todas migrations válidas
     */
    protected function collectMigrations(array $files): array
    {
        $migrations = [];

        foreach ($files as $file) {
            $data = $this->extractMigrationFields($file);

            if ($data) {
                $migrations[] = $data;
            }
        }

        return $migrations;
    }

    /**
     * Extrai nome do model, campos e relações
     */
    protected function extractMigrationFields(string $file): ?array
    {
        $content = file_get_contents($file);

        if (!preg_match('/Schema::create\([\'"](\w+)[\'"]/', $content, $match)) {
            return null;
        }

        $table = $match[1];

        // Ignorar tabelas padrão
        $ignoreTables = config('make-full.ignore_tables', [
            'migrations',
            'password_resets',
            'failed_jobs',
            'personal_access_tokens',
            'sessions',
            'cache',
            'cache_locks',
            'jobs',
            'job_batches'
        ]);

        if (in_array($table, $ignoreTables, true)) {
            return null;
        }

        $model = Str::studly(Str::singular($table));

        preg_match_all(
            '/\$table->(\w+)\([\'"](\w+)[\'"]?(?:,\s*([\d,\s]+))?\)([^;]*)/',
            $content,
            $columns,
            PREG_SET_ORDER
        );

        $fields = [];
        $relations = [];

        foreach ($columns as $column) {

            $type = $column[1];
            $name = $column[2] ?? null;
            $extra = $column[4] ?? '';

            if (!$name) {
                continue;
            }

            // Ignorar campos automáticos
            if (in_array($name, ['id', 'created_at', 'updated_at', 'deleted_at'], true)) {
                continue;
            }

            $modifiers = [];

            if (str_contains($extra, 'nullable')) $modifiers[] = 'nullable';
            if (str_contains($extra, 'unique')) $modifiers[] = 'unique';
            if (str_contains($extra, 'index')) $modifiers[] = 'index';

            if (preg_match('/default\(([^)]+)\)/', $extra, $def)) {
                $modifiers[] = 'default(' . trim($def[1], "'\"") . ')';
            }

            // Detectar foreign key
            if ($type === 'foreignId' || str_ends_with($name, '_id')) {
                $relatedModel = Str::studly(str_replace('_id', '', $name));

                $relations[] = [
                    'field' => $name,
                    'related' => $relatedModel,
                ];
            }

            $fieldString = $name . ':' . $type;

            if (!empty($modifiers)) {
                $fieldString .= ':' . implode(':', $modifiers);
            }

            $fields[] = $fieldString;
        }

        if (empty($fields)) {
            return null;
        }

        return [
            'model' => $model,
            'table' => $table,
            'fields' => array_values(array_unique($fields)),
            'fieldsString' => implode(',', array_unique($fields)),
            'relations' => array_values(array_unique($relations, SORT_REGULAR)),
        ];
    }
}
