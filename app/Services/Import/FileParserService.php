<?php

namespace App\Services\Import;

use Exception;

class FileParserService
{
    /**
     * Parser une ligne selon le mapping défini
     */
    public function parseLine(string $line, string $fileType, array $mapping, string $importType): array
    {
        // Le délimiteur est toujours \t (tabulation)
        $fields = explode("\t", $line);

        $minColumns = count($mapping);
        if (count($fields) < $minColumns) {
            throw new Exception("Format invalide (minimum {$minColumns} colonnes requises, " . count($fields) . " trouvées)");
        }

        // Mapper les champs selon le mapping défini
        $data = [];
        foreach ($mapping as $index => $columnName) {
            $value = $fields[$index] ?? '';
            
            // Traitement spécifique selon le nom de colonne
            if (str_contains($columnName, 'date')) {
                $data[$columnName] = !empty(trim($value)) ? $this->parseDate($value) : null;
            } elseif (str_contains($columnName, 'montant') || str_contains($columnName, 'taux')) {
                $data[$columnName] = !empty(trim($value)) ? $this->parseAmount($value) : 0;
            } else {
                $data[$columnName] = trim($value);
            }
        }
        
        // Valeurs par défaut selon le type
        if ($importType === 'ClientCommercial') {
            $data['devise'] = $data['devise'] ?? 'EUR';
            $data['type_operation'] = $data['type_operation'] ?? $fileType;
            $data['statut'] = $data['statut'] ?? 'completed';
        } elseif ($importType === 'Partenaire') {
            $data['devise'] = $data['devise'] ?? 'EUR';
            $data['statut'] = $data['statut'] ?? 'active';
        }

        return $data;
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
        
        // Validation générique : vérifier que les premières colonnes obligatoires existent
        $firstThreeColumns = array_slice($mapping, 0, 3);
        foreach ($firstThreeColumns as $column) {
            if (!isset($data[$column]) || (is_string($data[$column]) && empty($data[$column]))) {
                return false;
            }
        }
        
        return true;
    }
}
