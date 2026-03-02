<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Carbon;

class DataImportService
{
    private array $errors = [];
    private int $successCount = 0;
    private int $errorCount = 0;

    public function parseTextFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new Exception("Le fichier n'existe pas: {$filePath}");
        }

        $content = file_get_contents($filePath);
        $lines = explode("\n", $content);
        
        $data = [];
        $lineNumber = 0;

        foreach ($lines as $line) {
            $lineNumber++;
            $line = trim($line);

            // Ignorer les lignes vides
            if (empty($line)) {
                continue;
            }

            try {
                $parsed = $this->parseLine($line);
                
                if ($this->validateData($parsed)) {
                    $data[] = $parsed;
                    $this->successCount++;
                } else {
                    $this->errorCount++;
                    $this->errors[] = "Ligne {$lineNumber}: Données invalides";
                }
            } catch (Exception $e) {
                $this->errorCount++;
                $this->errors[] = "Ligne {$lineNumber}: {$e->getMessage()}";
            }
        }

        return $data;
    }

    private function parseLine(string $line): array
    {
        // Support de plusieurs formats: CSV, TSV, pipe-separated
        $delimiter = $this->detectDelimiter($line);
        $fields = str_getcsv($line, $delimiter);

        if (count($fields) < 6) {
            throw new Exception("Format de ligne invalide (nombre de colonnes insuffisant)");
        }

        return [
            'reference' => trim($fields[0]),
            'date_operation' => $this->parseDate($fields[1]),
            'libelle' => trim($fields[2]),
            'montant' => $this->parseAmount($fields[3]),
            'devise' => trim($fields[4] ?? 'EUR'),
            'compte' => trim($fields[5]),
            'agence' => trim($fields[6] ?? ''),
            'type_operation' => trim($fields[7] ?? ''),
            'statut' => trim($fields[8] ?? 'completed'),
        ];
    }

    private function detectDelimiter(string $line): string
    {
        $delimiters = [';', "\t", '|', ','];
        $counts = [];

        foreach ($delimiters as $delimiter) {
            $counts[$delimiter] = substr_count($line, $delimiter);
        }

        arsort($counts);
        return array_key_first($counts);
    }

    private function parseDate(string $date): string
    {
        $formats = [
            'Y-m-d',
            'd/m/Y',
            'd-m-Y',
            'Y/m/d',
            'd.m.Y',
        ];

        foreach ($formats as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, trim($date));
                if ($parsed) {
                    return $parsed->format('Y-m-d');
                }
            } catch (Exception $e) {
                continue;
            }
        }

        throw new Exception("Format de date invalide: {$date}");
    }

    private function parseAmount(string $amount): float
    {
        // Nettoyer le montant (supprimer espaces, remplacer virgule par point)
        $amount = trim($amount);
        $amount = str_replace([' ', ','], ['', '.'], $amount);
        
        if (!is_numeric($amount)) {
            throw new Exception("Montant invalide: {$amount}");
        }

        return (float) $amount;
    }

    private function validateData(array $data): bool
    {
        return !empty($data['reference']) &&
               !empty($data['date_operation']) &&
               !empty($data['libelle']) &&
               isset($data['montant']) &&
               !empty($data['compte']);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    public function getErrorCount(): int
    {
        return $this->errorCount;
    }

    public function generateImportReport(): array
    {
        return [
            'success_count' => $this->successCount,
            'error_count' => $this->errorCount,
            'total_lines' => $this->successCount + $this->errorCount,
            'errors' => $this->errors,
        ];
    }
}
