<?php

namespace App\Services\Import;

use Exception;
use Illuminate\Support\Facades\DB;

class DatabaseIndexService
{
    /**
     * Supprimer les indexes d'une table
     */
    public function dropIndexes(string $importType, string $tableName): array
    {
        $indexes = $this->getIndexesForType($importType, $tableName);
        $dropped = [];
        
        foreach ($indexes as $index) {
            try {
                DB::statement("ALTER TABLE {$tableName} DROP INDEX {$index}");
                $dropped[] = $index;
            } catch (Exception $e) {
                // Index n'existe peut-être pas
            }
        }
        
        return $dropped;
    }

    /**
     * Restaurer les indexes
     */
    public function restoreIndexes(array $indexes, string $importType, string $tableName): array
    {
        $indexDefinitions = $this->getIndexDefinitions($importType, $tableName);
        $restored = [];
        
        foreach ($indexes as $index) {
            if (isset($indexDefinitions[$index])) {
                try {
                    DB::statement($indexDefinitions[$index]);
                    $restored[] = $index;
                } catch (Exception $e) {
                    // Ignorer les erreurs
                }
            }
        }
        
        return $restored;
    }

    /**
     * Obtenir la liste des indexes selon le type
     */
    private function getIndexesForType(string $type, string $tableName): array
    {
        if ($type === 'ClientCommercial') {
            return [
                "{$tableName}_reference_index",
                "{$tableName}_date_operation_index",
                "{$tableName}_montant_index",
                "{$tableName}_compte_index",
                "{$tableName}_type_operation_index",
                "{$tableName}_statut_index",
            ];
        } elseif ($type === 'Partenaire') {
            return [
                "{$tableName}_code_partenaire_index",
                "{$tableName}_date_transaction_index",
                "{$tableName}_montant_ttc_index",
                "{$tableName}_statut_index",
            ];
        }
        
        return [];
    }

    /**
     * Obtenir les définitions SQL des indexes
     */
    private function getIndexDefinitions(string $type, string $tableName): array
    {
        if ($type === 'ClientCommercial') {
            return [
                "{$tableName}_reference_index" => "ALTER TABLE {$tableName} ADD INDEX {$tableName}_reference_index(reference)",
                "{$tableName}_date_operation_index" => "ALTER TABLE {$tableName} ADD INDEX {$tableName}_date_operation_index(date_operation)",
                "{$tableName}_montant_index" => "ALTER TABLE {$tableName} ADD INDEX {$tableName}_montant_index(montant)",
                "{$tableName}_compte_index" => "ALTER TABLE {$tableName} ADD INDEX {$tableName}_compte_index(compte)",
                "{$tableName}_type_operation_index" => "ALTER TABLE {$tableName} ADD INDEX {$tableName}_type_operation_index(type_operation)",
                "{$tableName}_statut_index" => "ALTER TABLE {$tableName} ADD INDEX {$tableName}_statut_index(statut)",
            ];
        } elseif ($type === 'Partenaire') {
            return [
                "{$tableName}_code_partenaire_index" => "ALTER TABLE {$tableName} ADD INDEX {$tableName}_code_partenaire_index(code_partenaire)",
                "{$tableName}_date_transaction_index" => "ALTER TABLE {$tableName} ADD INDEX {$tableName}_date_transaction_index(date_transaction)",
                "{$tableName}_montant_ttc_index" => "ALTER TABLE {$tableName} ADD INDEX {$tableName}_montant_ttc_index(montant_ttc)",
                "{$tableName}_statut_index" => "ALTER TABLE {$tableName} ADD INDEX {$tableName}_statut_index(statut)",
            ];
        }
        
        return [];
    }
}
