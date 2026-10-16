<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;

/**
 * Imprime o SQL das migrations que ainda não rodaram no banco conectado, para
 * importar no phpMyAdmin do servidor (a hospedagem não tem acesso SSH).
 * Usado por scripts/gerar-pacote-deploy.sh contra uma cópia do banco do servidor.
 */
class SqlAtualizacaoCommand extends Command
{
    protected $signature = 'linkvideo:sql-atualizacao';

    protected $description = 'Gera o SQL das migrations pendentes (para importar no phpMyAdmin)';

    public function handle(Migrator $migrator): int
    {
        $ran = $migrator->repositoryExists() ? $migrator->getRepository()->getRan() : [];
        $pending = array_diff_key($migrator->getMigrationFiles(database_path('migrations')), array_flip($ran));
        $connection = $migrator->resolveConnection(null);

        foreach ($pending as $name => $path) {
            // As migrations do projeto são classes anônimas: o arquivo devolve a instância.
            $migration = require $path;

            $this->line("-- {$name}");
            foreach ($connection->pretend(fn () => $migration->up()) as $query) {
                // Tabelas novas: pode importar de novo sem erro.
                $sql = preg_replace('/^create table `/i', 'create table if not exists `', $query['query']);
                $this->line(rtrim($sql, ';').';');
            }
            $this->line(
                "INSERT INTO `migrations` (`migration`, `batch`) SELECT * FROM (SELECT '{$name}' AS m, "
                .'COALESCE(MAX(`batch`), 0) + 1 AS b FROM `migrations`) AS nova '
                ."WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '{$name}');"
            );
            $this->newLine();
        }

        return self::SUCCESS;
    }
}
