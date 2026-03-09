<?php

namespace App\Services\Import;

use Exception;
use Illuminate\Support\Facades\DB;

class BulkInsertService
{
    /**
     * Insérer un batch de données avec optimisation SQL Server
     */
    public function insertBatch(array $batch, string $tableName): void
    {
        if (empty($batch)) {
            return;
        }

        try {
            // Ajouter les timestamps
            $now = now();
            $batch = array_map(function ($item) use ($now) {
                $item['created_at'] = $now;
                $item['updated_at'] = $now;
                return $item;
            }, $batch);

            // Utiliser une méthode différente selon le driver
            if (DB::getDriverName() === 'sqlsrv') {
                $this->insertBatchSqlServer($batch, $tableName);
            } else {
                $this->insertBatchStandard($batch, $tableName);
            }
        } catch (Exception $e) {
            throw new Exception("Erreur lors de l'insertion batch: {$e->getMessage()}");
        }
    }

    /**
     * Insert standard avec paramètres bindés (MySQL, PostgreSQL)
     */
    private function insertBatchStandard(array $batch, string $tableName): void
    {
        DB::transaction(function () use ($batch, $tableName) {
            DB::table($tableName)->insert($batch);
        });
    }

    /**
     * Insert optimisé pour SQL Server (contourne la limite des 2100 paramètres)
     * Utilise des valeurs littérales au lieu de binding
     */
    private function insertBatchSqlServer(array $batch, string $tableName): void
    {
        // Diviser en sous-batchs de 250 lignes pour ne pas surcharger la requête
        $chunks = array_chunk($batch, 250);
        
        DB::transaction(function () use ($chunks, $tableName) {
            foreach ($chunks as $chunk) {
                $this->insertChunkWithLiterals($chunk, $tableName);
            }
        });
    }

    /**
     * Construire et exécuter un INSERT avec des valeurs littérales
     */
    private function insertChunkWithLiterals(array $rows, string $tableName): void
    {
        if (empty($rows)) {
            return;
        }

        // Récupérer les colonnes depuis la première ligne
        $columns = array_keys($rows[0]);
        $columnList = implode(', ', array_map(fn($col) => "[$col]", $columns));

        // Construire les VALUES
        $valuesList = [];
        foreach ($rows as $row) {
            $values = [];
            foreach ($columns as $column) {
                $value = $row[$column];
                $values[] = $this->formatValueForSql($value);
            }
            $valuesList[] = '(' . implode(', ', $values) . ')';
        }

        // Construire et exécuter la requête
        $sql = "INSERT INTO [{$tableName}] ({$columnList}) VALUES " . implode(', ', $valuesList);
        
        DB::statement($sql);
    }

    /**
     * Formater une valeur pour l'insertion SQL
     */
    private function formatValueForSql($value): string
    {
        if (is_null($value)) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        // Échapper les chaînes de caractères
        $escaped = str_replace("'", "''", (string) $value);
        return "N'{$escaped}'";
    }

    /**
     * Optimiser les performances de la base de données
     */
    public function optimizePerformance(): void
    {
        // Désactiver les logs de requêtes (économise beaucoup de mémoire)
        DB::connection()->disableQueryLog();

        // Augmenter la mémoire si nécessaire
        ini_set('memory_limit', '512M');

        // Optimisations MySQL/PostgreSQL
        try {
            if (DB::getDriverName() === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
                DB::statement('SET UNIQUE_CHECKS=0');
                DB::statement('SET AUTOCOMMIT=0');
            }
        } catch (Exception $e) {
            // Ignorer si pas supporté
        }
    }

    /**
     * Restaurer les paramètres de performance
     */
    public function restorePerformance(): void
    {
        try {
            if (DB::getDriverName() === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
                DB::statement('SET UNIQUE_CHECKS=1');
                DB::statement('SET AUTOCOMMIT=1');
            }
        } catch (Exception $e) {
            // Ignorer
        }
    }
}
