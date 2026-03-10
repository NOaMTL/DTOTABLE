<?php

namespace App\Services\Import;

use Exception;

class FileParserService
{
    private array $dateColumnsCache = [];
    private array $amountColumnsCache = [];
    
    /**
     * Parser une ligne selon le mapping défini
     *
     * @param string $line Ligne du fichier texte
     * @param string $fileType Type du fichier (ex: 'OPERATIONS')
     * @param array $mapping Mapping des colonnes avec format:
     *   ['col' => ['value' => 'fixe'], 'col2' => ['file_index' => 0], 'col3' => ['file_type' => true]]
     * @param string $importType Type d'import (ClientCommercial/Partenaire)
     * @param string $fileName Nom du fichier (optionnel)
     */
    public function parseLine(string $line, string $fileType, array $mapping, string $importType, string $fileName = ''): array
    {
        // Le délimiteur est toujours \t (tabulation)
        $fields = explode("\t", $line);

        // Précompiler les colonnes nécessitant un traitement spécial (1 seule fois)
        $cacheKey = md5(serialize(array_keys($mapping)));
        if (!isset($this->dateColumnsCache[$cacheKey])) {
            $this->precompileMappingTypes($mapping, $cacheKey);
        }

        // Compter combien de colonnes du fichier sont nécessaires
        $maxFileIndex = -1;
        foreach ($mapping as $columnName => $config) {
            if (is_array($config) && isset($config['file_index'])) {
                $maxFileIndex = max($maxFileIndex, $config['file_index']);
            }
        }
        
        if ($maxFileIndex >= 0 && count($fields) <= $maxFileIndex) {
            throw new Exception("Format invalide (colonne index {$maxFileIndex} requise, " . count($fields) . " colonnes trouvées)");
        }

        // Mapper les champs selon le mapping défini
        $data = [];
        foreach ($mapping as $columnName => $config) {
            $value = null;
            
            // Déterminer la source de la valeur
            if (is_array($config)) {
                if (isset($config['value'])) {
                    // Valeur fixe : pas de traitement
                    $data[$columnName] = $config['value'];
                    continue;
                } elseif (isset($config['file_index'])) {
                    // Valeur depuis le fichier
                    $value = $fields[$config['file_index']] ?? '';
                } elseif (isset($config['file_type']) && $config['file_type']) {
                    // Type du fichier
                    $value = $fileType;
                } elseif (isset($config['file_name']) && $config['file_name']) {
                    // Nom du fichier
                    $value = $fileName;
                } elseif (isset($config['special'])) {
                    // Valeur dynamique (mots-clés spéciaux)
                    $data[$columnName] = $this->resolveSpecialValue($config['special']);
                    continue;
                } else {
                    $value = '';
                }
            } else {
                // Ancien format : config est directement un index
                $value = $fields[$config] ?? '';
            }
            
            // Traitement spécifique selon le type de colonne (précompilé)
            if (isset($this->dateColumnsCache[$cacheKey][$columnName])) {
                $data[$columnName] = !empty(trim($value)) ? $this->parseDate($value) : null;
            } elseif (isset($this->amountColumnsCache[$cacheKey][$columnName])) {
                $data[$columnName] = !empty(trim($value)) ? $this->parseAmount($value) : 0;
            } else {
                $data[$columnName] = trim($value);
            }
        }

        return $data;
    }

    /**
     * Précompiler les types de colonnes pour éviter str_contains répétés
     */
    private function precompileMappingTypes(array $mapping, string $cacheKey): void
    {
        $this->dateColumnsCache[$cacheKey] = [];
        $this->amountColumnsCache[$cacheKey] = [];
        
        foreach ($mapping as $columnName => $config) {
            // Ignorer les valeurs fixes et spéciales
            if (is_array($config) && (isset($config['value']) || isset($config['special']))) {
                continue;
            }
            
            if (str_contains($columnName, 'date')) {
                $this->dateColumnsCache[$cacheKey][$columnName] = true;
            } elseif (str_contains($columnName, 'montant') || str_contains($columnName, 'taux')) {
                $this->amountColumnsCache[$cacheKey][$columnName] = true;
            }
        }
    }

    /**
     * Parser une date
     */
    private function parseDate(string $date): string
    {
        $date = trim($date);
        
        $formats = [
            'Y-m-d',
            'd/m/Y',
            'd-m-Y',
            'Y/m/d',
            'd.m.Y',
            'Ymd',
        ];

        foreach ($formats as $format) {
            try {
                $parsed = \DateTime::createFromFormat($format, $date);
                if ($parsed) {
                    return $parsed->format('Y-m-d');
                }
            } catch (Exception $e) {
                continue;
            }
        }

        throw new Exception("Format de date invalide: {$date}");
    }

    /**
     * Parser un montant
     */
    private function parseAmount(string $amount): float
    {
        // Nettoyer le montant
        $amount = trim($amount);
        $amount = str_replace([' ', "'"], '', $amount);
        $amount = str_replace(',', '.', $amount);
        
        if (!is_numeric($amount)) {
            throw new Exception("Montant invalide: {$amount}");
        }

        return (float) $amount;
    }

    /**
     * Résoudre une valeur spéciale (mot-clé dynamique)
     */
    private function resolveSpecialValue(string $keyword): mixed
    {
        return match($keyword) {
            'now' => now()->format('Y-m-d H:i:s'),       // Format datetime string
            'today' => today()->format('Y-m-d'),         // Format date string
            'year' => date('Y'),                         // Année actuelle
            'month' => date('m'),                        // Mois actuel
            'day' => date('d'),                          // Jour actuel
            'date' => date('Y-m-d'),                     // Date formatée
            'datetime' => date('Y-m-d H:i:s'),           // Date/heure formatée
            'time' => date('H:i:s'),                     // Heure actuelle
            'timestamp' => time(),                       // Unix timestamp
            'user' => get_current_user(),                // Utilisateur système
            'hostname' => gethostname(),                 // Nom de la machine
            'php_version' => PHP_VERSION,                // Version PHP
            default => null,                             // Mot-clé inconnu
        };
    }

    /**
     * Valider les données
     */
    public function validateData(array $data, array $mapping, string $importType): bool
    {
        // Validation selon le type d'import
        if ($importType === 'ClientCommercial') {
            return !empty($data['reference']) 
                && !empty($data['date_operation'])
                && isset($data['montant']);
        } elseif ($importType === 'Partenaire') {
            return !empty($data['code_partenaire']) 
                && !empty($data['date_transaction'])
                && isset($data['montant_ttc']);
        }
        
        // Validation générique : vérifier que les 3 premières colonnes issues du fichier existent
        $fileColumns = [];
        foreach ($mapping as $columnName => $config) {
            if (is_array($config) && isset($config['file_index'])) {
                $fileColumns[] = $columnName;
                if (count($fileColumns) >= 3) break;
            }
        }
        
        foreach ($fileColumns as $column) {
            if (!isset($data[$column]) || (is_string($data[$column]) && empty($data[$column]))) {
                return false;
            }
        }
        
        return true;
    }
}
