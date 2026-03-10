<?php

namespace App\Console\Commands;

use App\Services\Import\ApikLogService;
use App\Services\Import\BulkInsertService;
use App\Services\Import\DatabaseIndexService;
use App\Services\Import\FileParserTurboService;
use App\Services\Import\ImportConfigService;
use App\Services\Import\QddDownloadService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ImportTableauDataTurboCommand extends Command
{
    protected $signature = 'tableau:import-turbo 
                            {type : Type d\'import (ClientCommercial ou Partenaire)}
                            {--chunk-size=1000 : Nombre de lignes par batch}
                            {--truncate : Vider la table avant import}
                            {--drop-indexes : Supprimer les indexes pendant import (plus rapide)}
                            {--keep-files : Conserver les fichiers après import}';

    protected $description = '🚀 Import TURBO - Version ultra-optimisée pour fichiers massifs (100+ colonnes)';

    private int $totalProcessed = 0;
    private int $totalSuccess = 0;
    private int $totalErrors = 0;
    private array $errors = [];
    private int $chunkSize;
    private string $tempDir;

    public function __construct(
        private ImportConfigService $configService,
        private QddDownloadService $downloadService,
        private FileParserTurboService $parserService,  // ← Version TURBO
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
        
        // Avertissement mode turbo
        $this->warn('⚡ MODE TURBO ACTIVÉ - Validation minimale, performances maximales');
        $this->newLine();
        
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
            'mode' => 'TURBO',
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
        $remotePath = config('imports.qdd.remote_base_path', '');
        if ($remotePath) {
            $this->info("🌐 Chemin distant: {$remotePath}");
        }
        $this->newLine();

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

        // Nettoyer le dossier temporaire
        if (!$this->option('keep-files') && File::isDirectory($this->tempDir)) {
            if (count(File::files($this->tempDir)) === 0) {
                File::deleteDirectory($this->tempDir);
                $this->newLine();
                $this->info('🗑️  Dossier temporaire nettoyé');
            }
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
        $this->info("║           🚀 IMPORT TURBO - {$importType} 🚀                 ║");
        $this->info("╚═══════════════════════════════════════════════════════════════╝");
        $this->newLine();
        $this->info("📋 Type: <fg=cyan>{$importType}</>");
        $this->info("🗄️  Table: <fg=cyan>{$tableName}</>");
        $this->info("⚡ Mode: <fg=yellow>TURBO (validation minimale)</>");
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
            
        } catch (Exception $e) {
            $this->error("  ❌ Erreur téléchargement: {$e->getMessage()}");
            $this->newLine();
            return;
        }

        // Traiter le fichier en mode TURBO
        $this->processLocalFileTurbo($localFile, $fileName, $fileType, $tableName, $columnMapping, $importType);
        
        // Supprimer le fichier immédiatement
        if (!$this->option('keep-files')) {
            if ($this->downloadService->deleteFile($localFile)) {
                $this->line("  🗑️  Fichier supprimé");
            }
        }
    }

    private function processLocalFileTurbo(
        string $filePath,
        string $fileName,
        string $fileType,
        string $tableName,
        array $columnMapping,
        string $importType
    ): void {
        $this->line("  🚀 Traitement TURBO...");

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            $this->error("  ❌ Impossible d'ouvrir le fichier");
            return;
        }

        $batch = [];
        $lineNumber = 0;
        $fileSuccess = 0;

        $bar = $this->output->createProgressBar();
        $bar->setFormat(' %current% lignes | %elapsed:6s% | %memory:6s%');
        $bar->start();

        while (($line = fgets($handle)) !== false) {
            $lineNumber++;
            
            // Ignorer les 2 premières lignes et lignes vides
            if ($lineNumber <= 2 || empty(trim($line))) {
                continue;
            }

            try {
                $parsed = $this->parserService->parseLine(trim($line), $fileType, $columnMapping, $importType, $fileName);
                $batch[] = $parsed;
                $fileSuccess++;

                if (count($batch) >= $this->chunkSize) {
                    $this->insertService->insertBatch($batch, $tableName);
                    $batch = [];
                    $bar->advance($this->chunkSize);
                }
            } catch (Exception $e) {
                // Mode turbo : on ignore les erreurs (optionnel)
                // Décommenter pour logger :
                // $this->totalErrors++;
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
        $this->totalProcessed += $lineNumber;

        $this->line("  ✅ Succès: {$fileSuccess}");
        $this->newLine();
    }

    private function displaySummary(float $startTime): void
    {
        $duration = microtime(true) - $startTime;
        
        $this->newLine();
        $this->info("╔═══════════════════════════════════════════════════════════════╗");
        $this->info("║                  🚀 RÉSUMÉ IMPORT TURBO 🚀                    ║");
        $this->info("╚═══════════════════════════════════════════════════════════════╝");
        $this->newLine();
        
        $this->line("  📊 Lignes traitées:  <fg=cyan>" . number_format($this->totalProcessed, 0, ',', ' ') . "</>");
        $this->line("  ✅ Succès:           <fg=green>" . number_format($this->totalSuccess, 0, ',', ' ') . "</>");
        $this->line("  ⏱️  Durée:            <fg=yellow>" . $this->formatDuration($duration) . "</>");
        
        if ($this->totalSuccess > 0) {
            $rate = $this->totalSuccess / $duration;
            $this->line("  ⚡ Vitesse:          <fg=magenta>" . number_format($rate, 0, ',', ' ') . " lignes/sec</>");
        }

        $this->newLine();
        $this->info("🎉 Import TURBO terminé !");
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
                'mode' => 'TURBO',
            ];

            $metadata = [
                'chunk_size' => $this->chunkSize,
                'truncate' => $this->option('truncate'),
                'drop_indexes' => $this->option('drop-indexes'),
                'executed_at' => now()->toDateTimeString(),
                'user' => get_current_user(),
                'mode' => 'TURBO',
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

            $this->newLine();
            $this->info('📤 Logs envoyés à ApikLog');
            
        } catch (Exception $e) {
            $this->warn("⚠️  {$e->getMessage()}");
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
