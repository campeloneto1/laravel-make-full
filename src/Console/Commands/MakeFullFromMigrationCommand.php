<?php

namespace Campelo\MakeFull\Console\Commands;

use Illuminate\Console\Command;

class MakeFullFromMigrationCommand extends Command
{

    protected $signature = 'make:full-from-migration {--path= : Caminho das migrations (padrão: database/migrations)}';
    protected $description = 'Gera Request, Resource, Controller, Service, Repository e Policy para todas as migrations do projeto';

    public function handle()
    {
        $path = $this->option('path') ?: base_path('database/migrations');

        if (!is_dir($path)) {
            $this->error('Diretório de migrations não encontrado: ' . $path);
            return 1;
        }

        $files = glob($path . '/*.php');
        if (empty($files)) {
            $this->error('Nenhuma migration encontrada em: ' . $path);
            return 1;
        }

        // 1. Coletar informações de todas as migrations
        $migrations = [];
        foreach ($files as $file) {
            $migrationData = $this->extractMigrationFields($file);
            if ($migrationData) {
                $migrations[] = $migrationData;
            }
        }

        // 2. Detectar tabelas pivô (pivot)
        $pivotRelations = [];
        foreach ($migrations as $migration) {
            // Pivot: tabela só com 2 FKs e sem outros campos
            if (count($migration['relations'] ?? []) === 2 && count($migration['fields'] ?? []) === 2) {
                $models = array_map(fn($rel) => $rel['related'], $migration['relations']);
                $pivotTable = $migration['table'] ?? '';
                // Adiciona belongsToMany para ambos os models
                $pivotRelations[$models[0]][] = [
                    'type' => 'belongsToMany',
                    'related' => $models[1],
                    'pivot' => $pivotTable,
                ];
                $pivotRelations[$models[1]][] = [
                    'type' => 'belongsToMany',
                    'related' => $models[0],
                    'pivot' => $pivotTable,
                ];
            }
        }

        // 3. Gerar arquivos normalmente, mas passando as relações belongsToMany detectadas
        foreach ($migrations as $migration) {
            $this->info('Processando migration: ' . ($migration['table'] ?? $migration['model']));
            $modelName = $migration['model'];
            $fieldsString = $migration['fieldsString'];
            $relations = $migration['relations'] ?? [];
            // Adiciona belongsToMany se houver
            $customRelations = $relations;
            if (isset($pivotRelations[$modelName])) {
                foreach ($pivotRelations[$modelName] as $pivotRel) {
                    $customRelations[] = $pivotRel;
                }
            }
            config(['make-full._relations' => $customRelations]);
            $this->call('make:full', [
                'name' => $modelName,
                '--fields' => $fieldsString,
            ]);
            config(['make-full._relations' => null]);
        }

        $this->info('Processamento de todas as migrations finalizado.');
        return 0;
    }

    /**
     * Extrai o nome do model e os campos da migration.
     * Retorna ['model' => 'User', 'fieldsString' => 'name:string,email:string:unique']
     */
    protected function extractMigrationFields(string $file): ?array
    {
        $content = file_get_contents($file);
        if (!preg_match('/Schema::create\([\'\"](\w+)[\'\"]/', $content, $matches)) {
            return null;
        }
        $table = $matches[1];
        $model = ucfirst(\Illuminate\Support\Str::singular(\Illuminate\Support\Str::studly($table)));

        // Regex para pegar campos comuns e FKs
        preg_match_all('/\$table->(\w+)\([\'\"](\w+)[\'\"](,\s*\d+)?\)([^;]*)/', $content, $cols, PREG_SET_ORDER);
        $fields = [];
        $relations = [];
        foreach ($cols as $col) {
            $type = $col[1];
            $name = $col[2];
            $extra = $col[4] ?? '';
            $modifiers = [];
            if (strpos($extra, 'nullable') !== false) $modifiers[] = 'nullable';
            if (strpos($extra, 'unique') !== false) $modifiers[] = 'unique';
            if (strpos($extra, 'index') !== false) $modifiers[] = 'index';
            if (preg_match('/default\(([^)]+)\)/', $extra, $def)) $modifiers[] = 'default(' . trim($def[1], "'\"") . ')';

            // Detecta FK: foreignId + constrained ou references
            $isForeign = false;
            if ($type === 'foreignId' && (strpos($extra, 'constrained') !== false || strpos($extra, 'references') !== false)) {
                $isForeign = true;
                $relatedModel = ucfirst(\Illuminate\Support\Str::studly(str_replace('_id', '', $name)));
                $relations[] = [
                    'field' => $name,
                    'related' => $relatedModel,
                ];
            }
            $fields[] = $name . ':' . $type . ($modifiers ? ':' . implode(':', $modifiers) : '');
        }
        if (empty($fields)) return null;
        // Passa os relacionamentos para uso posterior (ex: generator pode usar via config ou option)
        return [
            'model' => $model,
            'table' => $table,
            'fieldsString' => implode(',', $fields),
            'fields' => $fields,
            'relations' => $relations,
        ];
    }
}

