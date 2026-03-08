<?php

namespace App\Services\Import;

use Exception;
use Illuminate\Support\Facades\DB;

class BulkInsertService
{
    /**
     * Insérer un batch de données
     */
    public function insertBatch(array $batch, string $tableName): void
    {
        if (empty($batch)) {
            return;
        }

        try {
            DB::transaction(function () use ($batch, $tableName) {
                // Ajouter les timestamps
                $now = now();
                $batch = array_map(function ($item) use ($now) {
                    $item['created_at'] = $now;
                    $item['updated_at'] = $now;
                    return $item;
                }, $batch);

                // Un seul INSERT pour tout le batch
                DB::table($tableName)->insert($batch);
            });
        } catch (Exception $e) {
            throw new Exception("Erreur lors de l'insertion batch: {$e->getMessage()}");
        }
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
