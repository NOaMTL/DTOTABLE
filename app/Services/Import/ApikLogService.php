<?php

namespace App\Services\Import;

use Exception;

class ApikLogService
{
    private array $logs = [];

    /**
     * Ajouter un log
     */
    public function addLog(string $event, array $data = [], string $level = 'info'): void
    {
        $this->logs[] = [
            'timestamp' => now()->toIso8601String(),
            'event' => $event,
            'level' => $level,
            'data' => $data,
        ];
    }

    /**
     * Envoyer les logs à ApikLog
     */
    public function sendLogs(
        string $importType,
        string $tableName,
        array $summary,
        array $missingFiles,
        array $errors,
        array $metadata,
        int $filesProcessed
    ): void {
        try {
            $apikLog = new \ApikLog();
            
            // Préparer le payload complet
            $logPayload = [
                'import_type' => $importType,
                'table' => $tableName,
                'summary' => $summary,
                'missing_files' => $missingFiles,
                'errors' => array_slice($errors, -50), // Limiter aux 50 dernières erreurs
                'events' => $this->logs,
                'metadata' => $metadata,
            ];

            // Envoyer le log général
            $apikLog->log('tableau_import', $logPayload);
            
            // Envoyer les erreurs si présentes
            if ($summary['total_errors'] > 0 || !empty($missingFiles)) {
                $errorPayload = [
                    'import_type' => $importType,
                    'table' => $tableName,
                    'total_errors' => $summary['total_errors'],
                    'missing_files_count' => count($missingFiles),
                    'missing_files' => $missingFiles,
                    'sample_errors' => array_slice($errors, -10),
                    'duration' => $summary['duration'],
                ];
                
                $apikLog->error('tableau_import_errors', $errorPayload);
            } else {
                // Envoyer le succès
                $successPayload = [
                    'import_type' => $importType,
                    'table' => $tableName,
                    'total_success' => $summary['total_success'],
                    'duration' => $summary['duration'],
                    'rate_per_second' => $summary['rate_per_second'],
                    'files_processed' => $filesProcessed,
                ];
                
                $apikLog->success('tableau_import_success', $successPayload);
            }
            
        } catch (Exception $e) {
            throw new Exception("Impossible d'envoyer les logs à ApikLog: {$e->getMessage()}");
        }
    }

    public function getLogs(): array
    {
        return $this->logs;
    }
}
