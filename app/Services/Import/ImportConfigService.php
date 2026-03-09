<?php

namespace App\Services\Import;

class ImportConfigService
{
    /**
     * Valider le type d'import
     */
    public function validateImportType(string $type): bool
    {
        return in_array($type, ['ClientCommercial', 'Partenaire']);
    }

    /**
     * Obtenir le nom de la table selon le type
     */
    public function getTableName(string $type): string
    {
        return match($type) {
            'ClientCommercial' => config('imports.tables.client_commercial', 'client_commercial_data'),
            'Partenaire' => config('imports.tables.partenaire', 'partenaire_data'),
            default => 'tableau_data',
        };
    }

    /**
     * Obtenir le mapping des colonnes selon le type
     */
    public function getColumnMapping(string $type): array
    {
        // Récupérer le mapping depuis la config
        $importTypeKey = $this->getImportTypeKey($type);
        $mapping = config("imports.column_mapping.{$importTypeKey}");
        
        if (!$mapping) {
            // Fallback sur l'ancien format si pas de config
            return match($type) {
                'ClientCommercial' => [
                    0 => 'reference',
                    1 => 'date_operation',
                    2 => 'libelle',
                    3 => 'montant',
                    4 => 'devise',
                    5 => 'compte',
                    6 => 'agence',
                    7 => 'type_operation',
                    8 => 'statut',
                ],
                'Partenaire' => [
                    0 => 'code_partenaire',
                    1 => 'date_transaction',
                    2 => 'description',
                    3 => 'montant_ht',
                    4 => 'montant_ttc',
                    5 => 'taux_tva',
                    6 => 'devise',
                    7 => 'statut',
                ],
                default => [],
            };
        }
        
        return $mapping;
    }

    /**
     * Convertir le type d'import en clé de config
     */
    private function getImportTypeKey(string $importType): string
    {
        return $importType === 'ClientCommercial' ? 'client_commercial' : 'partenaire';
    }

    /**
     * Obtenir la liste des fichiers attendus selon le type
     */
    public function getExpectedFiles(string $type): array
    {
        $configKey = match($type) {
            'ClientCommercial' => 'imports.files.client_commercial',
            'Partenaire' => 'imports.files.partenaire',
            default => 'imports.expected_files',
        };
        
        return config($configKey, []);
    }
}
