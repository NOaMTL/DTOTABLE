<?php

namespace App\Services\Import;

use Exception;

/**
 * Version ultra-optimisée du parser pour fichiers massifs (100+ colonnes, 100k+ lignes)
 * 
 * Optimisations appliquées :
 * - Pas de cache MD5, détection inline
 * - Trim minimal
 * - Format de date unique (Ymd ou Y-m-d)
 * - Parsing montant simplifié
 * - Pas de validation stricte
 */
class FileParserTurboService
{
    private array $dateColumns = [];
    private array $amountColumns = [];
    private bool $mappingCompiled = false;

    /**
     * Parser une ligne (version turbo)
     */
    public function parseLine(string $line, string $fileType, array $mapping, string $importType, string $fileName = ''): array
    {
        // Première ligne : précompiler
        if (!$this->mappingCompiled) {
            $this->compileMappingTypes($mapping);
            $this->mappingCompiled = true;
        }

        // Explode optimisé
        $fields = explode("\t", $line);
        $data = [];

        // Parcours unique sans conditions multiples
        foreach ($mapping as $columnName => $config) {
            if (is_array($config)) {
                // Valeur fixe : copie directe
                if (isset($config['value'])) {
                    $data[$columnName] = $config['value'];
                    continue;
                }
                
                // Valeur spéciale
                if (isset($config['special'])) {
                    $data[$columnName] = $this->resolveSpecialValueFast($config['special']);
                    continue;
                }
                
                // Type/nom fichier
                if (isset($config['file_type'])) {
                    $data[$columnName] = $fileType;
                    continue;
                }
                if (isset($config['file_name'])) {
                    $data[$columnName] = $fileName;
                    continue;
                }
                
                // Depuis fichier
                if (isset($config['file_index'])) {
                    $value = $fields[$config['file_index']] ?? '';
                    
                    // Traitement selon type précompilé
                    if (isset($this->dateColumns[$columnName])) {
                        $data[$columnName] = $value ? $this->parseDateFast($value) : null;
                    } elseif (isset($this->amountColumns[$columnName])) {
                        $data[$columnName] = $value ? $this->parseAmountFast($value) : 0;
                    } else {
                        $data[$columnName] = $value;
                    }
                    continue;
                }
            } else {
                // Ancien format
                $value = $fields[$config] ?? '';
                $data[$columnName] = $value;
            }
        }

        return $data;
    }

    /**
     * Précompiler les types de colonnes (1 seule fois)
     */
    private function compileMappingTypes(array $mapping): void
    {
        foreach ($mapping as $columnName => $config) {
            if (is_array($config) && (isset($config['value']) || isset($config['special']))) {
                continue;
            }
            
            // Détection rapide
            if (strpos($columnName, 'date') !== false) {
                $this->dateColumns[$columnName] = true;
            } elseif (strpos($columnName, 'montant') !== false || strpos($columnName, 'taux') !== false) {
                $this->amountColumns[$columnName] = true;
            }
        }
    }

    /**
     * Parsing date ultra-rapide : 2 formats seulement
     */
    private function parseDateFast(string $date): ?string
    {
        // Supprimer espaces
        $date = trim($date);
        if (empty($date)) {
            return null;
        }
        
        // Format 1: Ymd (20240115)
        if (strlen($date) === 8 && ctype_digit($date)) {
            return substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2);
        }
        
        // Format 2: Y-m-d ou d/m/Y
        if (strpos($date, '-') !== false) {
            // Déjà au bon format (probablement)
            return $date;
        }
        
        if (strpos($date, '/') !== false) {
            // d/m/Y -> Y-m-d
            $parts = explode('/', $date);
            if (count($parts) === 3) {
                return $parts[2] . '-' . str_pad($parts[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($parts[0], 2, '0', STR_PAD_LEFT);
            }
        }
        
        // Fallback : retourner tel quel
        return $date;
    }

    /**
     * Parsing montant ultra-rapide
     */
    private function parseAmountFast(string $amount): float
    {
        // Nettoyage minimal
        $amount = str_replace([' ', "'", ','], ['', '', '.'], $amount);
        return (float) $amount;
    }

    /**
     * Résolution valeurs spéciales (cache statique)
     */
    private function resolveSpecialValueFast(string $keyword): mixed
    {
        static $cache = [];
        
        // Cache des valeurs qui changent rarement
        if (!isset($cache['date'])) {
            $cache['date'] = date('Y-m-d');
            $cache['year'] = date('Y');
            $cache['month'] = date('m');
            $cache['day'] = date('d');
            $cache['user'] = get_current_user();
            $cache['hostname'] = gethostname();
            $cache['php_version'] = PHP_VERSION;
        }
        
        return match($keyword) {
            'now', 'datetime' => date('Y-m-d H:i:s'),  // Recalculer à chaque fois
            'today', 'date' => $cache['date'],
            'year' => $cache['year'],
            'month' => $cache['month'],
            'day' => $cache['day'],
            'time' => date('H:i:s'),
            'timestamp' => time(),
            'user' => $cache['user'],
            'hostname' => $cache['hostname'],
            'php_version' => $cache['php_version'],
            default => null,
        };
    }

    /**
     * Validation minimale (très permissive)
     */
    public function validateData(array $data, array $mapping, string $importType): bool
    {
        // Version turbo : toujours valide (la validation ralentit)
        // Si nécessaire, activer seulement pour debug
        return true;
    }
}
