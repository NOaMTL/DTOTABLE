<?php

namespace App\Console\Commands;

use App\Services\Import\ApikLogService;
use App\Services\Import\BulkInsertService;
use App\Services\Import\DatabaseIndexService;
use App\Services\Import\FileParserService;
use App\Services\Import\ImportConfigService;
use App\Services\Import\QddDownloadService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ImportTableauDataRefactoredCommand extends Command
{
    protected $signature = 'tableau:import-v2 
                            {type : Type d\'import (ClientCommercial ou Partenaire)}
                            {--remote-path= : Chemin distant de base pour QDD} 
                            {--chunk-size=1000 : Nombre de lignes par batch}
                            {--truncate : Vider la table avant import}
                            {--drop-indexes : Supprimer les indexes pendant import (plus rapide)}
                            {--keep-files : Conserver les fichiers après import}';

    protected $description = 'Import optimisé des fichiers via QDD (version refactorisée avec services)';

    private int $totalProcessed = 0;
    private int $totalSuccess = 0;
    private int $totalErrors = 0;
    private array $errors = [];
    private int $chunkSize;
    private string $tempDir;

    public function __construct(
        private ImportConfigService $configService,
        private QddDownloadService $downloadService,
        private FileParserService $parserService,
        private BulkInsertService $insertService,
        private DatabaseIndexService $indexService,
        private ApikLogService $logService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $startTime = microtime(true);
        $this->chunkSize = (int) $this->option('chunk-size');
        $this->tempDir = storage_path('app/temp_imports');
        
        // Récupérer et valider le type d'import
        $importType = $this->argument('type');
        
        if (!$this->configService->validateImportType($importType)) {
            $this->error("❌ Type d'import invalide: {$importType}");
            $this->line("Types acceptés: ClientCommercial, Partenaire");
            return self::FAILURE;
        }
        
        // Initialiser la configuration
        $tableName = $this->configService->getTableName($importType);
        $columnMapping = $this->configService->getColumnMapping($importType);
        $expectedFiles = $this->configService->getExpectedFiles($importType);

        $this->displayHeader($importType, $tableName);
        $this->logService->addLog('import_started', [
            'type' => $importType,
            'table' => $tableName,
            'options' => [
                'truncate' => $this->option('truncate'),
                'drop_indexes' => $this->option('drop-indexes'),
                'chunk_size' => $this->chunkSize,
            ],
        ]);

        // Créer le dossier temporaire
        if (!File::isDirectory($this->tempDir)) {
            File::makeDirectory($this->tempDir, 0755, true);
        }

        if (empty($expectedFiles)) {
            $this->error("❌ Aucun fichier défini pour l'import");
            return self::FAILURE;
        }

        $this->info("📋 Fichiers attendus: " . count($expectedFiles));
        $remotePath = $this->option('remote-path') ?? '';
        if ($remotePath) {
            $this->info("🌐 Chemin distant: {$remotePath}");
        }
        $this->newLine();

        // Confirmation si truncate
        if ($this->option('truncate')) {
            if (!$this->confirm("⚠️  Voulez-vous vraiment SUPPRIMER toutes les données de {$tableName} ?", false)) {
                $this->warn('Import annulé.');
                return self::SUCCESS;
            }
        }

        // Optimisations de performance
        $this->insertService->optimizePerformance();

        // Supprimer indexes si demandé
        $droppedIndexes = [];
        if ($this->option('drop-indexes')) {
            $this->info('🔧 Suppression temporaire des indexes...');
            $droppedIndexes = $this->indexService->dropIndexes($importType, $tableName);
            $this->info('✅ ' . count($droppedIndexes) . ' indexes supprimés');
        }

        // Truncate si demandé
        if ($this->option('truncate')) {
            $this->info("🗑️  Suppression des données existantes ({$tableName})...");
            DB::table($tableName)->truncate();
            $this->info('✅ Table vidée');
            $this->logService->addLog('table_truncated', ['table' => $tableName]);
            $this->newLine();
        }

        // Traiter chaque fichier
        $fileNumber = 0;
        foreach ($expectedFiles as $fileInfo) {
            $fileNumber++;
            [$fileName, $fileType] = $fileInfo;
            
            $this->processFile(
                $fileName,
                $fileType,
                $fileNumber,
                count($expectedFiles),
                $remotePath,
                $tableName,
                $columnMapping,
                $importType
            );
        }

        // Restaurer les indexes
        if (!empty($droppedIndexes)) {
            $this->newLine();
            $this->info('🔧 Reconstruction des indexes...');
            $restored = $this->indexService->restoreIndexes($droppedIndexes, $importType, $tableName);
            $this->info('✅ ' . count($restored) . ' indexes reconstruits');
        }

        // Restaurer les paramètres DB
        $this->insertService->restorePerformance();

        // Nettoyer les fichiers
        if (!$this->option('keep-files')) {
            $this->newLine();
            $this->info('🗑️  Nettoyage des fichiers téléchargés...');
            $deleted = $this->downloadService->cleanup($this->tempDir);
            $this->line("  ✅ {$deleted} fichier(s) supprimé(s)");
        }

        // Afficher le résumé
        $this->displaySummary($startTime);

        // Envoyer les logs à ApikLog
        $this->sendLogsToApikLog($startTime, $importType, $tableName);

        return $this->totalErrors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function displayHeader(string $importType, string $tableName): void
    {
        $this->info("╔═══════════════════════════════════════════════════════════════╗");
        $this->info("║     Import Optimisé - {$importType} via QDD (v2)             ║");
        $this->info("╚═══════════════════════════════════════════════════════════════╝");
        $this->newLine();
        $this->info("📋 Type: <fg=cyan>{$importType}</>");
        $this->info("🗄️  Table: <fg=cyan>{$tableName}</>");
        $this->newLine();
    }

    private function processFile(
        string $fileName,
        string $fileType,
        int $fileNumber,
        int $totalFiles,
        string $remotePath,
        string $tableName,
        array $columnMapping,
        string $importType
    ): void {
        $this->info("📄 [{$fileNumber}/{$totalFiles}] {$fileName} (Type: {$fileType})");

        // Télécharger le fichier
        try {
            $this->line("  ⬇️  Téléchargement depuis QDD...");
            $localFile = $this->downloadService->downloadFile($fileName, $fileType, $remotePath, $this->tempDir);
            
            $fileSize = $this->formatBytes(filesize($localFile));
            $this->line("  ✅ Téléchargé ({$fileSize})");
            
            $this->logService->addLog('file_downloaded', [
                'file' => $fileName,
                'type' => $fileType,
                'size' => filesize($localFile),
                'size_formatted' => $fileSize,
            ]);
            
        } catch (Exception $e) {
            $this->error("  ❌ Erreur téléchargement: {$e->getMessage()}");
            $this->logService->addLog('file_download_failed', [
                'file' => $fileName,
                'type' => $fileType,
                'error' => $e->getMessage(),
            ], 'error');
            $this->newLine();
            return;
        }

        // Traiter le fichier
        $this->processLocalFile($localFile, $fileName, $fileType, $tableName, $columnMapping, $importType);
    }

    private function processLocalFile(
        string $filePath,
        string $fileName,
        string $fileType,
        string $tableName,
        array $columnMapping,
        string $importType
    ): void {
        $this->line("  📊 Traitement du fichier...");

        try {
            $handle = fopen($filePath, 'r');
            if (!$handle) {
                throw new Exception("Impossible d'ouvrir le fichier");
            }

            $batch = [];
            $lineNumber = 0;
            $fileSuccess = 0;
            $fileErrors = 0;

            $bar = $this->output->createProgressBar();
            $bar->setFormat(' %current% lignes | %elapsed:6s% | %memory:6s%');
            $bar->start();

            while (($line = fgets($handle)) !== false) {
                $lineNumber++;
                $line = trim($line);

                // Ignorer les 2 premières lignes et les lignes vides
                if ($lineNumber <= 2 || empty($line)) {
                    continue;
                }

                try {
                    $parsed = $this->parserService->parseLine($line, $fileType, $columnMapping, $importType);
                    
                    if ($this->parserService->validateData($parsed, $columnMapping, $importType)) {
                        $batch[] = $parsed;
                        $fileSuccess++;

                        if (count($batch) >= $this->chunkSize) {
                            $this->insertService->insertBatch($batch, $tableName);
                            $batch = [];
                            $bar->advance($this->chunkSize);
                        }
                    } else {
                        $fileErrors++;
                        $this->logError($fileName, $lineNumber, "Données invalides");
                    }
                } catch (Exception $e) {
                    $fileErrors++;
                    $this->logError($fileName, $lineNumber, $e->getMessage());
                }
            }

            // Insérer le reste
            if (!empty($batch)) {
                $this->insertService->insertBatch($batch, $tableName);
                $bar->advance(count($batch));
            }

            $bar->finish();
            $this->newLine();
            fclose($handle);

            $this->totalSuccess += $fileSuccess;
            $this->totalErrors += $fileErrors;
            $this->totalProcessed += $lineNumber;

            $this->line("  ✅ Succès: {$fileSuccess} | ❌ Erreurs: {$fileErrors}");
            $this->logService->addLog('file_processed', [
                'file' => $fileName,
                'type' => $fileType,
                'lines_processed' => $lineNumber,
                'success' => $fileSuccess,
                'errors' => $fileErrors,
            ]);
            $this->newLine();

        } catch (Exception $e) {
            $this->error("  ❌ Erreur fichier: {$e->getMessage()}");
            $this->logService->addLog('file_processing_failed', [
                'file' => $fileName,
                'type' => $fileType,
                'error' => $e->getMessage(),
            ], 'error');
            $this->newLine();
        }
    }

    private function displaySummary(float $startTime): void
    {
        $duration = microtime(true) - $startTime;
        
        $this->newLine();
        $this->info("╔═══════════════════════════════════════════════════════════════╗");
        $this->info("║                      RÉSUMÉ DE L'IMPORT                       ║");
        $this->info("╚═══════════════════════════════════════════════════════════════╝");
        $this->newLine();
        
        $this->line("  📊 Lignes traitées:  <fg=cyan>" . number_format($this->totalProcessed, 0, ',', ' ') . "</>");
        $this->line("  ✅ Succès:           <fg=green>" . number_format($this->totalSuccess, 0, ',', ' ') . "</>");
        $this->line("  ❌ Erreurs:          <fg=red>" . number_format($this->totalErrors, 0, ',', ' ') . "</>");
        $this->line("  ⏱️  Durée:            <fg=yellow>" . $this->formatDuration($duration) . "</>");
        
        if ($this->totalSuccess > 0) {
            $rate = $this->totalSuccess / $duration;
            $this->line("  ⚡ Vitesse:          <fg=magenta>" . number_format($rate, 0, ',', ' ') . " lignes/sec</>");
        }

        $this->newLine();

        $missingFiles = $this->downloadService->getMissingFiles();
        if (!empty($missingFiles)) {
            $this->error("❌ Fichiers manquants ou non téléchargés:");
            foreach ($missingFiles as $missing) {
                $this->line("  • {$missing['file']} (Type: {$missing['type']}) - {$missing['error']}");
            }
            $this->newLine();
        }

        if ($this->totalErrors > 0 && !empty($this->errors)) {
            $this->warn("⚠️  Dernières erreurs (max 10):");
            $displayErrors = array_slice($this->errors, -10);
            
            foreach ($displayErrors as $error) {
                $this->line("  • {$error['file']}:{$error['line']} - {$error['message']}");
            }
            $this->newLine();
        }

        if ($this->totalErrors === 0) {
            $this->info("🎉 Import terminé avec succès !");
        } else {
            $this->warn("⚠️  Import terminé avec des erreurs.");
        }
    }

    private function sendLogsToApikLog(float $startTime, string $importType, string $tableName): void
    {
        try {
            $duration = microtime(true) - $startTime;
            
            $summary = [
                'total_processed' => $this->totalProcessed,
                'total_success' => $this->totalSuccess,
                'total_errors' => $this->totalErrors,
                'duration' => $this->formatDuration($duration),
                'duration_raw_seconds' => round($duration, 2),
                'rate_per_second' => $this->totalSuccess > 0 ? round($this->totalSuccess / $duration, 2) : 0,
            ];

            $metadata = [
                'chunk_size' => $this->chunkSize,
                'truncate' => $this->option('truncate'),
                'drop_indexes' => $this->option('drop-indexes'),
                'keep_files' => $this->option('keep-files'),
                'remote_path' => $this->option('remote-path'),
                'executed_at' => now()->toDateTimeString(),
                'user' => get_current_user(),
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
            ];

            $this->logService->sendLogs(
                $importType,
                $tableName,
                $summary,
                $this->downloadService->getMissingFiles(),
                $this->errors,
                $metadata,
                count($this->downloadService->getDownloadedFiles())
            );

            if ($this->totalErrors === 0) {
                $this->info('✅ Succès envoyé à ApikLog');
            } else {
                $this->info('❌ Erreurs envoyées à ApikLog');
            }
            
            $this->newLine();
            $this->info('📤 Logs envoyés à ApikLog');
            
        } catch (Exception $e) {
            $this->warn("⚠️  {$e->getMessage()}");
        }
    }

    private function logError(string $file, int $line, string $message): void
    {
        $this->errors[] = [
            'file' => $file,
            'line' => $line,
            'message' => $message,
        ];

        if (count($this->errors) > 100) {
            array_shift($this->errors);
        }
    }

    private function formatDuration(float $seconds): string
    {
        if ($seconds < 60) {
            return round($seconds, 2) . 's';
        }
        
        $minutes = floor($seconds / 60);
        $seconds = $seconds % 60;
        
        if ($minutes < 60) {
            return "{$minutes}m " . round($seconds) . 's';
        }
        
        $hours = floor($minutes / 60);
        $minutes = $minutes % 60;
        
        return "{$hours}h {$minutes}m " . round($seconds) . 's';
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
