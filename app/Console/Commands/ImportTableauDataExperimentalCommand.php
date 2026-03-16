<?php

namespace App\Console\Commands;

use App\Services\Import\ImportConfigService;
use App\Services\Import\QddDownloadService;
use App\Services\Import\FileParserTurboService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ImportTableauDataExperimentalCommand extends Command
{
    protected $signature = 'tableau:import-experimental 
                            {type : Type d\'import (ClientCommercial ou Partenaire)}
                            {--truncate : Vider la table avant import}
                            {--keep-files : Conserver les fichiers après import}
                            {--no-bulk : Désactiver BULK INSERT (utiliser INSERT classique)}
                            {--no-tablock : Désactiver TABLOCK}
                            {--no-triggers-disable : Ne pas désactiver les triggers}';

    protected $description = '🔬 Import EXPERIMENTAL - Performance maximale avec BULK INSERT + TABLOCK + optimisations SQL Server avancées';

    private int $totalProcessed = 0;
    private int $totalSuccess = 0;
    private array $savedSettings = [];
    private string $tempDir;
    private int $optimalChunkSize = 5000;
    private array $tableSchema = [];
    private array $computedColumns = [];
    private string $currentFileName = '';
    private string $currentFileType = '';

    public function __construct(
        private ImportConfigService $configService,
        private QddDownloadService $downloadService,
        private FileParserTurboService $parserService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $startTime = microtime(true);
        $this->tempDir = storage_path('app/temp_imports');
        
        $this->warn('🔬🔬🔬 MODE EXPERIMENTAL - Performance maximale absolue 🔬🔬🔬');
        $this->warn('⚠️  Utilise BULK INSERT API native + optimisations SQL Server avancées');
        $this->newLine();
        
        $importType = $this->argument('type');
        
        if (!$this->configService->validateImportType($importType)) {
            $this->error("❌ Type d'import invalide: {$importType}");
            return self::FAILURE;
        }
        
        $tableName = $this->configService->getTableName($importType);
        $columnMapping = $this->configService->getColumnMapping($importType);
        $expectedFiles = $this->configService->getExpectedFiles($importType);

        $this->displayHeader($importType, $tableName);
        
        if (!File::isDirectory($this->tempDir)) {
            File::makeDirectory($this->tempDir, 0755, true);
        }

        if (empty($expectedFiles)) {
            $this->error("❌ Aucun fichier défini pour l'import");
            return self::FAILURE;
        }

        $remotePath = config('imports.qdd.remote_base_path', '');
        
        $this->info("📋 Fichiers attendus: " . count($expectedFiles));
        $this->newLine();

        // 🔥 OPTIMISATIONS SQL SERVER AVANCÉES
        $this->applyAdvancedOptimizations($tableName);

        // 📋 Charger le schéma de la table pour formatage intelligent
        $this->tableSchema = $this->loadTableSchema($tableName);
        $this->info("📋 Schéma chargé: " . count($this->tableSchema) . " colonnes analysées");
        
        // 🔧 Configurer les colonnes calculées
        $this->configureComputedColumns($importType);
        $this->newLine();

        // Truncate si demandé
        if ($this->option('truncate')) {
            $this->info("🗑️  Suppression des données existantes ({$tableName})...");
            DB::table($tableName)->truncate();
            $this->info('✅ Table vidée');
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

        // Restaurer les paramètres SQL Server
        $this->restoreAdvancedOptimizations($tableName);

        // Nettoyer
        if (!$this->option('keep-files') && File::isDirectory($this->tempDir)) {
            if (count(File::files($this->tempDir)) === 0) {
                File::deleteDirectory($this->tempDir);
                $this->newLine();
                $this->info('🗑️  Dossier temporaire nettoyé');
            }
        }

        // Afficher le résumé
        $this->displaySummary($startTime);

        return self::SUCCESS;
    }

    /**
     * 🔥 Appliquer toutes les optimisations SQL Server avancées
     */
    private function applyAdvancedOptimizations(string $tableName): void
    {
        $this->info('🔥 Application des optimisations SQL Server avancées...');
        $this->newLine();

        // 1. RECOVERY SIMPLE
        try {
            $result = DB::selectOne("SELECT recovery_model_desc FROM sys.databases WHERE name = DB_NAME()");
            $this->savedSettings['recovery_model'] = $result->recovery_model_desc ?? 'FULL';
            
            if ($this->savedSettings['recovery_model'] !== 'SIMPLE') {
                DB::statement("ALTER DATABASE CURRENT SET RECOVERY SIMPLE");
                $this->info("   ✓ RECOVERY MODEL: {$this->savedSettings['recovery_model']} → SIMPLE");
            }
        } catch (Exception $e) {
            $this->warn("   ⚠️ RECOVERY MODEL: " . $e->getMessage());
        }

        // 2. Désactiver AUTO_UPDATE_STATISTICS
        try {
            $result = DB::selectOne("SELECT is_auto_update_stats_on FROM sys.databases WHERE name = DB_NAME()");
            $this->savedSettings['auto_update_stats'] = $result->is_auto_update_stats_on ?? 1;
            
            if ($this->savedSettings['auto_update_stats']) {
                DB::statement("ALTER DATABASE CURRENT SET AUTO_UPDATE_STATISTICS OFF");
                $this->info("   ✓ AUTO_UPDATE_STATISTICS: ON → OFF");
            }
        } catch (Exception $e) {
            $this->warn("   ⚠️ AUTO_UPDATE_STATISTICS: " . $e->getMessage());
        }

        // 3. Sauvegarder et supprimer structure
        $this->info('   ⏳ Analyse de la structure...');
        $structure = $this->saveTableStructure($tableName);
        $this->savedSettings['structure'] = $structure;
        
        if (!empty($structure['indexes'])) {
            $this->info("   ✓ " . count($structure['indexes']) . " index(es) sauvegardé(s)");
        }
        
        $this->info('   ⏳ Suppression de la structure...');
        $this->dropTableStructure($tableName, $structure);

        // 4. Désactiver triggers
        if (!$this->option('no-triggers-disable')) {
            try {
                $triggers = DB::select("SELECT name FROM sys.triggers WHERE parent_id = OBJECT_ID(?)", [$tableName]);
                $this->savedSettings['triggers'] = array_column($triggers, 'name');
                
                if (!empty($triggers)) {
                    DB::statement("ALTER TABLE {$tableName} DISABLE TRIGGER ALL");
                    $this->info("   ✓ " . count($triggers) . " trigger(s) désactivé(s)");
                }
            } catch (Exception $e) {
                $this->warn("   ⚠️ TRIGGERS: " . $e->getMessage());
            }
        }

        // 5. Désactiver change tracking
        try {
            $result = DB::selectOne("
                SELECT COUNT(*) as has_ct 
                FROM sys.change_tracking_tables 
                WHERE object_id = OBJECT_ID(?)
            ", [$tableName]);
            
            if ($result->has_ct > 0) {
                DB::statement("ALTER TABLE {$tableName} DISABLE CHANGE_TRACKING");
                $this->savedSettings['change_tracking'] = true;
                $this->info("   ✓ CHANGE_TRACKING désactivé");
            }
        } catch (Exception $e) {
            // Change tracking pas activé
        }

        // 6. Lock escalation
        try {
            DB::statement("ALTER TABLE {$tableName} SET (LOCK_ESCALATION = TABLE)");
            $this->info("   ✓ LOCK_ESCALATION = TABLE");
        } catch (Exception $e) {
            $this->warn("   ⚠️ LOCK_ESCALATION: " . $e->getMessage());
        }

        $this->newLine();
        $this->info('✅ Optimisations appliquées - Performance maximale activée');
        $this->newLine();
    }

    /**
     * Restaurer les paramètres SQL Server
     */
    private function restoreAdvancedOptimizations(string $tableName): void
    {
        $this->newLine();
        $this->info('🔧 Restauration des paramètres SQL Server...');

        // 1. Restaurer structure
        if (!empty($this->savedSettings['structure'])) {
            $this->restoreTableStructure($tableName, $this->savedSettings['structure']);
            $total = count($this->savedSettings['structure']['indexes'] ?? []) +
                    count($this->savedSettings['structure']['constraints'] ?? []) +
                    count($this->savedSettings['structure']['foreign_keys'] ?? []);
            $this->info("   ✓ Structure restaurée ($total élément(s))");
        }

        // 2. Réactiver triggers
        if (!empty($this->savedSettings['triggers'])) {
            try {
                DB::statement("ALTER TABLE {$tableName} ENABLE TRIGGER ALL");
                $this->info("   ✓ " . count($this->savedSettings['triggers']) . " trigger(s) réactivé(s)");
            } catch (Exception $e) {
                $this->warn("   ⚠️ TRIGGERS: " . $e->getMessage());
            }
        }

        // 3. Réactiver change tracking
        if (!empty($this->savedSettings['change_tracking'])) {
            try {
                DB::statement("ALTER TABLE {$tableName} ENABLE CHANGE_TRACKING");
                $this->info("   ✓ CHANGE_TRACKING réactivé");
            } catch (Exception $e) {
                // Ignorer
            }
        }

        // 4. Restaurer AUTO_UPDATE_STATISTICS
        if (!empty($this->savedSettings['auto_update_stats'])) {
            try {
                DB::statement("ALTER DATABASE CURRENT SET AUTO_UPDATE_STATISTICS ON");
                DB::statement("UPDATE STATISTICS {$tableName} WITH FULLSCAN");
                $this->info("   ✓ AUTO_UPDATE_STATISTICS: OFF → ON + FULLSCAN");
            } catch (Exception $e) {
                $this->warn("   ⚠️ AUTO_UPDATE_STATISTICS: " . $e->getMessage());
            }
        }

        // 5. Restaurer RECOVERY MODEL
        if (!empty($this->savedSettings['recovery_model']) && $this->savedSettings['recovery_model'] !== 'SIMPLE') {
            try {
                DB::statement("ALTER DATABASE CURRENT SET RECOVERY {$this->savedSettings['recovery_model']}");
                $this->info("   ✓ RECOVERY MODEL: SIMPLE → {$this->savedSettings['recovery_model']}");
            } catch (Exception $e) {
                $this->warn("   ⚠️ RECOVERY MODEL: " . $e->getMessage());
            }
        }

        $this->info('✅ Paramètres restaurés');
    }

    private function displayHeader(string $importType, string $tableName): void
    {
        $this->info("╔═══════════════════════════════════════════════════════════════╗");
        $this->info("║        🔬🔬🔬 IMPORT EXPERIMENTAL - {$importType} 🔬🔬🔬      ║");
        $this->info("╚═══════════════════════════════════════════════════════════════╝");
        $this->newLine();
        $this->info("📋 Type: <fg=cyan>{$importType}</>");
        $this->info("🗄️  Table: <fg=cyan>{$tableName}</>");
        $this->info("🔬 Mode: <fg=yellow>EXPERIMENTAL (BULK INSERT + TABLOCK + SQL Server advanced)</>");
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

        // Télécharger
        try {
            $this->line("  ⬇️  Téléchargement...");
            $localFile = $this->downloadService->downloadFile($fileName, $fileType, $remotePath, $this->tempDir);
            $this->line("  ✅ Téléchargé (" . $this->formatBytes(filesize($localFile)) . ")");
        } catch (Exception $e) {
            $this->error("  ❌ Erreur: {$e->getMessage()}");
            $this->newLine();
            return;
        }

        // Traiter avec BULK INSERT ou INSERT classique
        $useBulkInsert = !$this->option('no-bulk');
        $useTablock = !$this->option('no-tablock');
        
        if ($useBulkInsert) {
            $this->processWithBulkInsert($localFile, $fileName, $fileType, $tableName, $columnMapping, $importType, $useTablock);
        } else {
            $this->processWithClassicInsert($localFile, $fileName, $fileType, $tableName, $columnMapping, $importType, $useTablock);
        }
        
        // Supprimer
        if (!$this->option('keep-files')) {
            $this->downloadService->deleteFile($localFile);
            $this->line("  🗑️  Fichier supprimé");
        }
    }

    /**
     * 🚀 BULK INSERT : API native SQL Server
     */
    private function processWithBulkInsert(
        string $filePath,
        string $fileName,
        string $fileType,
        string $tableName,
        array $columnMapping,
        string $importType,
        bool $useTablock
    ): void {
        $startTime = microtime(true);
        $this->line("  🚀 Traitement avec BULK INSERT API...");

        // Stocker contexte fichier pour colonnes calculées
        $this->currentFileName = $fileName;
        $this->currentFileType = $fileType;

        // Préparer le fichier pour BULK INSERT
        $bulkFile = $this->prepareBulkInsertFile($filePath, $fileType, $columnMapping, $importType, $fileName);
        
        if (!$bulkFile) {
            $this->error("  ❌ Impossible de préparer le fichier BULK");
            return;
        }

        // Convertir en chemin accessible par SQL Server (si distant)
        $sqlServerPath = $this->convertToSqlServerPath($bulkFile);
        
        if (!$sqlServerPath) {
            $this->warn("  ⚠️ SQL Server distant : BULK INSERT non disponible");
            $this->warn("  ⚠️ Fallback sur INSERT + TABLOCK...");
            @unlink($bulkFile);
            $this->processWithClassicInsert($filePath, $fileName, $fileType, $tableName, $columnMapping, $importType, $useTablock);
            return;
        }

        // Construire la commande BULK INSERT
        $tablock = $useTablock ? ', TABLOCK' : '';
        
        try {
            DB::statement("
                BULK INSERT {$tableName}
                FROM '{$sqlServerPath}'
                WITH (
                    DATAFILETYPE = 'char',
                    FIELDTERMINATOR = '\t',
                    ROWTERMINATOR = '\n',
                    FIRSTROW = 1,
                    BATCHSIZE = {$this->optimalChunkSize},
                    FIRE_TRIGGERS = OFF
                    {$tablock}
                )
            ");
            
            $duration = microtime(true) - $startTime;
            $lines = count(file($bulkFile));
            $this->totalSuccess += $lines;
            $this->totalProcessed += $lines;
            
            $rate = $lines / $duration;
            $this->line("  ✅ Succès: " . number_format($lines) . " lignes en " . round($duration, 2) . "s");
            $this->line("  ⚡ Vitesse: " . number_format($rate, 0) . " lignes/sec");
            
        } catch (Exception $e) {
            $this->error("  ❌ BULK INSERT échoué: {$e->getMessage()}");
            
            // Détecter si c'est un problème d'accès fichier
            if (str_contains($e->getMessage(), 'Cannot bulk load') || 
                str_contains($e->getMessage(), 'Operating system error code')) {
                $this->warn("  ⚠️ SQL Server ne peut pas accéder au fichier (serveur distant?)");
            }
            
            $this->warn("  ⚠️ Fallback sur INSERT + TABLOCK...");
            $this->processWithClassicInsert($filePath, $fileName, $fileType, $tableName, $columnMapping, $importType, $useTablock);
        } finally {
            @unlink($bulkFile);
        }
        
        $this->newLine();
    }

    /**
     * Préparer fichier pour BULK INSERT
     */
    private function prepareBulkInsertFile(
        string $filePath,
        string $fileType,
        array $columnMapping,
        string $importType,
        string $fileName
    ): ?string {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return null;
        }

        $bulkFile = tempnam($this->tempDir, 'bulk_') . '.txt';
        $bulkHandle = fopen($bulkFile, 'w');
        
        if (!$bulkHandle) {
            fclose($handle);
            return null;
        }

        $lineNumber = 0;
        while (($line = fgets($handle)) !== false) {
            $lineNumber++;
            
            // Ignorer en-têtes et lignes vides
            if ($lineNumber <= 2 || empty(trim($line))) {
                continue;
            }

            try {
                $parsed = $this->parserService->parseLine(trim($line), $fileType, $columnMapping, $importType, $fileName);
                
                // 🎯 Formatter selon le schéma de la table
                $formatted = $this->formatRowForSchema($parsed);
                
                // Convertir en ligne tab-delimited
                $values = array_values($formatted);
                $bulkLine = implode("\t", array_map(fn($v) => $v ?? '', $values)) . "\n";
                fwrite($bulkHandle, $bulkLine);
                
            } catch (Exception $e) {
                // Ignorer les erreurs
            }
        }

        fclose($handle);
        fclose($bulkHandle);

        return $bulkFile;
    }

    /**
     * INSERT classique avec TABLOCK
     */
    private function processWithClassicInsert(
        string $filePath,
        string $fileName,
        string $fileType,
        string $tableName,
        array $columnMapping,
        string $importType,
        bool $useTablock
    ): void {
        $this->line("  🚀 Traitement avec INSERT + " . ($useTablock ? "TABLOCK" : "standard") . "...");

        // Stocker contexte fichier pour colonnes calculées
        $this->currentFileName = $fileName;
        $this->currentFileType = $fileType;

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
            
            if ($lineNumber <= 2 || empty(trim($line))) {
                continue;
            }

            try {
                $parsed = $this->parserService->parseLine(trim($line), $fileType, $columnMapping, $importType, $fileName);
                
                // 🎯 Formatter selon le schéma de la table
                $formatted = $this->formatRowForSchema($parsed);
                $batch[] = $formatted;
                $fileSuccess++;

                if (count($batch) >= $this->optimalChunkSize) {
                    $this->insertBatchWithTablock($batch, $tableName, $useTablock);
                    $batch = [];
                    $bar->advance($this->optimalChunkSize);
                }
            } catch (Exception $e) {
                // Ignorer
            }
        }

        if (!empty($batch)) {
            $this->insertBatchWithTablock($batch, $tableName, $useTablock);
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

    /**
     * Insert avec TABLOCK hint
     */
    private function insertBatchWithTablock(array $batch, string $tableName, bool $useTablock): void
    {
        if (empty($batch)) {
            return;
        }

        $columns = array_keys($batch[0]);
        $columnsList = implode(', ', array_map(fn($col) => "[$col]", $columns));
        
        $values = [];
        foreach ($batch as $row) {
            $rowValues = array_map(function($value) {
                if ($value === null) {
                    return 'NULL';
                }
                return "'" . str_replace("'", "''", $value) . "'";
            }, array_values($row));
            
            $values[] = '(' . implode(', ', $rowValues) . ')';
        }

        $valuesString = implode(', ', $values);
        $tablock = $useTablock ? ' WITH (TABLOCK)' : '';
        
        DB::statement("INSERT INTO {$tableName}{$tablock} ({$columnsList}) VALUES {$valuesString}");
    }

    private function displaySummary(float $startTime): void
    {
        $duration = microtime(true) - $startTime;
        
        $this->newLine();
        $this->info("╔═══════════════════════════════════════════════════════════════╗");
        $this->info("║              🔬 RÉSUMÉ IMPORT EXPERIMENTAL 🔬                 ║");
        $this->info("╚═══════════════════════════════════════════════════════════════╝");
        $this->newLine();
        
        $this->line("  📊 Lignes traitées:  <fg=cyan>" . number_format($this->totalProcessed, 0, ',', ' ') . "</>");
        $this->line("  ✅ Succès:           <fg=green>" . number_format($this->totalSuccess, 0, ',', ' ') . "</>");
        $this->line("  ⏱️  Durée:            <fg=yellow>" . $this->formatDuration($duration) . "</>");
        
        if ($this->totalSuccess > 0 && $duration > 0) {
            $rate = $this->totalSuccess / $duration;
            $this->line("  ⚡ Vitesse:          <fg=magenta>" . number_format($rate, 0, ',', ' ') . " lignes/sec</>");
        }
        
        $this->newLine();
        $this->line("  🔬 Optimisations appliquées:");
        if (!$this->option('no-bulk')) {
            $this->line("     ✓ BULK INSERT API native");
        }
        if (!$this->option('no-tablock')) {
            $this->line("     ✓ TABLOCK hint");
        }
        $this->line("     ✓ Structure supprimée/restaurée");
        $this->line("     ✓ RECOVERY SIMPLE");
        $this->line("     ✓ AUTO_UPDATE_STATISTICS OFF");
        if (!$this->option('no-triggers-disable')) {
            $this->line("     ✓ Triggers désactivés");
        }
        $this->line("     ✓ LOCK_ESCALATION = TABLE");

        $this->newLine();
        $this->info("🎉 Import EXPERIMENTAL terminé - Performance maximale atteinte !");
    }

    private function formatDuration(float $seconds): string
    {
        if ($seconds < 60) {
            return round($seconds, 2) . 's';
        }
        
        $minutes = floor($seconds / 60);
        $seconds = $seconds % 60;
        
        return "{$minutes}m " . round($seconds) . 's';
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

    /**
     * 🔧 Convertir chemin local en chemin accessible par SQL Server
     */
    private function convertToSqlServerPath(string $localPath): ?string
    {
        // Configuration du chemin partagé accessible par SQL Server
        $sharedPath = config('imports.sql_server.bulk_insert_path', null);
        
        if (!$sharedPath) {
            // Pas de chemin partagé configuré
            // Si SQL Server est local (localhost, 127.0.0.1, ou nom machine), on peut utiliser le chemin local
            $host = config('database.connections.sqlsrv.host', 'localhost');
            
            if (in_array($host, ['localhost', '127.0.0.1', '(local)', '.'])) {
                // SQL Server local : utiliser le chemin Windows natif
                return str_replace('/', '\\', $localPath);
            }
            
            // SQL Server distant sans chemin partagé : impossible d'utiliser BULK INSERT
            return null;
        }
        
        // Copier le fichier vers le chemin partagé
        $fileName = basename($localPath);
        $sharedFile = rtrim($sharedPath, '\\/') . DIRECTORY_SEPARATOR . $fileName;
        
        if (!copy($localPath, $sharedFile)) {
            return null;
        }
        
        // Retourner le chemin UNC ou local accessible par SQL Server
        return str_replace('/', '\\', $sharedFile);
    }

    // Méthodes de gestion de structure (identiques à ImportTableauDataTurboCommand)
    
    /**
     * 📋 Charger le schéma complet de la table
     */
    private function loadTableSchema(string $tableName): array
    {
        $columns = DB::select("
            SELECT 
                c.name AS column_name,
                t.name AS data_type,
                c.max_length,
                c.precision,
                c.scale,
                c.is_nullable
            FROM sys.columns c
            INNER JOIN sys.types t ON c.user_type_id = t.user_type_id
            WHERE c.object_id = OBJECT_ID(?)
            ORDER BY c.column_id
        ", [$tableName]);

        $schema = [];
        foreach ($columns as $col) {
            $schema[$col->column_name] = [
                'type' => $col->data_type,
                'max_length' => $col->max_length,
                'precision' => $col->precision,
                'scale' => $col->scale,
                'nullable' => (bool) $col->is_nullable,
            ];
        }

        return $schema;
    }

    /**
     * 🎯 Formatter une ligne entière selon le schéma
     */
    private function formatRowForSchema(array $row): array
    {
        $formatted = [];
        
        // 1. Formatter les colonnes parsées
        foreach ($row as $columnName => $value) {
            $formatted[$columnName] = $this->formatValueForColumn($columnName, $value);
        }
        
        // 2. Ajouter les colonnes manquantes avec valeurs calculées
        $formatted = $this->addMissingColumns($formatted);
        
        return $formatted;
    }

    /**
     * 🔧 Ajouter les colonnes manquantes avec valeurs calculées/forcées
     */
    private function addMissingColumns(array $row): array
    {
        // Parcourir toutes les colonnes du schéma
        foreach ($this->tableSchema as $columnName => $schema) {
            // Si colonne déjà présente, passer
            if (isset($row[$columnName])) {
                continue;
            }
            
            // Si colonne calculée définie
            if (isset($this->computedColumns[$columnName])) {
                $row[$columnName] = $this->computeColumnValue($columnName, $row);
                continue;
            }
            
            // Sinon, valeur par défaut selon nullable
            $row[$columnName] = $schema['nullable'] ? null : $this->getDefaultValueForType($schema['type']);
        }
        
        return $row;
    }

    /**
     * 🔧 Configurer les colonnes calculées
     */
    private function configureComputedColumns(string $importType): void
    {
        // Colonnes calculées standard pour tous les imports
        $this->computedColumns = [
            // Timestamps Laravel
            'created_at' => fn() => now()->format('Y-m-d H:i:s'),
            'updated_at' => fn() => now()->format('Y-m-d H:i:s'),
            
            // Métadonnées d'import
            'import_date' => fn() => now()->format('Y-m-d'),
            'import_datetime' => fn() => now()->format('Y-m-d H:i:s'),
            'import_timestamp' => fn() => time(),
            
            // Informations fichier source
            'source_file' => fn() => $this->currentFileName,
            'source_type' => fn() => $this->currentFileType,
            'source_system' => fn() => 'QDD',
            
            // Flags de traitement
            'is_active' => fn() => 1,
            'is_deleted' => fn() => 0,
            'is_processed' => fn() => 0,
            'status' => fn() => 'imported',
        ];
        
        // Colonnes spécifiques selon le type d'import
        if ($importType === 'ClientCommercial') {
            $this->computedColumns['client_type'] = fn() => 'commercial';
            $this->computedColumns['origin'] = fn() => 'QDD_Import';
        } elseif ($importType === 'Partenaire') {
            $this->computedColumns['partner_type'] = fn() => 'standard';
            $this->computedColumns['origin'] = fn() => 'QDD_Import';
        }
        
        // Vous pouvez aussi récupérer depuis config
        $configColumns = config("imports.types.{$importType}.computed_columns", []);
        $this->computedColumns = array_merge($this->computedColumns, $configColumns);
    }

    /**
     * 🔧 Calculer la valeur d'une colonne
     */
    private function computeColumnValue(string $columnName, array $row): mixed
    {
        $computer = $this->computedColumns[$columnName];
        
        // Si c'est une closure, l'exécuter
        if ($computer instanceof \Closure) {
            return $computer($row);
        }
        
        // Sinon retourner la valeur directe
        return $computer;
    }

    /**
     * 🎯 Formatter une valeur selon le type de colonne
     */
    private function formatValueForColumn(string $columnName, mixed $value): mixed
    {
        // Si colonne pas dans schéma, retourner tel quel
        if (!isset($this->tableSchema[$columnName])) {
            return $value;
        }

        $schema = $this->tableSchema[$columnName];
        $type = strtolower($schema['type']);

        // NULL handling
        if ($value === null || $value === '') {
            return $schema['nullable'] ? null : $this->getDefaultValueForType($type);
        }

        // Formatter selon le type
        return match(true) {
            // VARCHAR, NVARCHAR, CHAR, NCHAR
            str_contains($type, 'varchar') || str_contains($type, 'char') => $this->formatString($value, $schema),
            
            // INT, BIGINT, SMALLINT, TINYINT
            in_array($type, ['int', 'bigint', 'smallint', 'tinyint']) => $this->formatInteger($value),
            
            // DECIMAL, NUMERIC, MONEY
            in_array($type, ['decimal', 'numeric', 'money', 'smallmoney']) => $this->formatDecimal($value, $schema),
            
            // FLOAT, REAL
            in_array($type, ['float', 'real']) => $this->formatFloat($value),
            
            // DATE, DATETIME, DATETIME2, SMALLDATETIME
            str_contains($type, 'date') || str_contains($type, 'time') => $this->formatDate($value),
            
            // BIT
            $type === 'bit' => $this->formatBoolean($value),
            
            // Autres types : retourner tel quel
            default => $value,
        };
    }

    /**
     * Formatter string avec troncature
     */
    private function formatString(mixed $value, array $schema): string
    {
        $str = (string) $value;
        
        // max_length pour NVARCHAR est * 2 (Unicode), diviser par 2
        $isUnicode = str_contains(strtolower($schema['type']), 'nvarchar') || 
                     str_contains(strtolower($schema['type']), 'nchar');
        
        $maxLength = $schema['max_length'];
        if ($isUnicode && $maxLength > 0) {
            $maxLength = (int) ($maxLength / 2);
        }
        
        // -1 = MAX (pas de limite)
        if ($maxLength === -1) {
            return $str;
        }
        
        // Tronquer si nécessaire
        if ($maxLength > 0 && mb_strlen($str) > $maxLength) {
            return mb_substr($str, 0, $maxLength);
        }
        
        return $str;
    }

    /**
     * Formatter integer
     */
    private function formatInteger(mixed $value): ?int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }
        
        // Extraire les chiffres
        $cleaned = preg_replace('/[^0-9-]/', '', (string) $value);
        return $cleaned !== '' ? (int) $cleaned : null;
    }

    /**
     * Formatter decimal avec précision/scale
     */
    private function formatDecimal(mixed $value, array $schema): ?string
    {
        if (!is_numeric($value)) {
            $cleaned = preg_replace('/[^0-9.-]/', '', (string) $value);
            if ($cleaned === '') {
                return null;
            }
            $value = $cleaned;
        }
        
        $precision = $schema['precision'] ?? 18;
        $scale = $schema['scale'] ?? 2;
        
        // Arrondir selon scale
        $rounded = round((float) $value, $scale);
        
        // Formatter avec le bon nombre de décimales
        return number_format($rounded, $scale, '.', '');
    }

    /**
     * Formatter float
     */
    private function formatFloat(mixed $value): ?float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        
        $cleaned = preg_replace('/[^0-9.-]/', '', (string) $value);
        return $cleaned !== '' ? (float) $cleaned : null;
    }

    /**
     * Formatter date
     */
    private function formatDate(mixed $value): ?string
    {
        if (empty($value) || $value === '0000-00-00' || $value === '00/00/0000') {
            return null;
        }
        
        try {
            // Si déjà au format Y-m-d ou Y-m-d H:i:s, retourner tel quel
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
                return $value;
            }
            
            // Sinon, parser et formatter
            $date = new \DateTime($value);
            return $date->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Formatter boolean
     */
    private function formatBoolean(mixed $value): int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        
        if (is_numeric($value)) {
            return (int) $value !== 0 ? 1 : 0;
        }
        
        $str = strtolower((string) $value);
        return in_array($str, ['true', 'yes', 'oui', '1', 'on']) ? 1 : 0;
    }

    /**
     * Valeur par défaut selon type (pour NOT NULL)
     */
    private function getDefaultValueForType(string $type): mixed
    {
        return match(true) {
            str_contains($type, 'int') => 0,
            str_contains($type, 'decimal') || str_contains($type, 'numeric') => '0.00',
            str_contains($type, 'float') || str_contains($type, 'real') => 0.0,
            $type === 'bit' => 0,
            str_contains($type, 'date') || str_contains($type, 'time') => null,
            default => '',
        };
    }
    
    private function saveTableStructure(string $tableName): array
    {
        $structure = ['indexes' => [], 'constraints' => [], 'foreign_keys' => []];

        $indexes = DB::select("
            SELECT i.name AS index_name, i.type_desc AS index_type, i.is_unique,
                   STRING_AGG(c.name, ',') WITHIN GROUP (ORDER BY ic.key_ordinal) AS columns
            FROM sys.indexes i
            INNER JOIN sys.index_columns ic ON i.object_id = ic.object_id AND i.index_id = ic.index_id
            INNER JOIN sys.columns c ON ic.object_id = c.object_id AND ic.column_id = c.column_id
            WHERE i.object_id = OBJECT_ID(?) AND i.is_primary_key = 0 AND i.type > 0
            GROUP BY i.name, i.type_desc, i.is_unique
        ", [$tableName]);

        foreach ($indexes as $index) {
            $structure['indexes'][] = [
                'name' => $index->index_name,
                'type' => $index->index_type,
                'is_unique' => $index->is_unique,
                'columns' => $index->columns,
            ];
        }

        $constraints = DB::select("
            SELECT cc.name AS constraint_name, cc.definition
            FROM sys.check_constraints cc
            WHERE cc.parent_object_id = OBJECT_ID(?)
        ", [$tableName]);

        foreach ($constraints as $constraint) {
            $structure['constraints'][] = [
                'name' => $constraint->constraint_name,
                'definition' => $constraint->definition,
            ];
        }

        $foreignKeys = DB::select("
            SELECT fk.name AS constraint_name,
                   OBJECT_NAME(fk.referenced_object_id) AS referenced_table,
                   COL_NAME(fkc.parent_object_id, fkc.parent_column_id) AS column_name,
                   COL_NAME(fkc.referenced_object_id, fkc.referenced_column_id) AS referenced_column
            FROM sys.foreign_keys fk
            INNER JOIN sys.foreign_key_columns fkc ON fk.object_id = fkc.constraint_object_id
            WHERE fk.parent_object_id = OBJECT_ID(?)
        ", [$tableName]);

        foreach ($foreignKeys as $fk) {
            $structure['foreign_keys'][] = [
                'name' => $fk->constraint_name,
                'column' => $fk->column_name,
                'referenced_table' => $fk->referenced_table,
                'referenced_column' => $fk->referenced_column,
            ];
        }

        return $structure;
    }

    private function dropTableStructure(string $tableName, array $structure): void
    {
        foreach ($structure['foreign_keys'] as $fk) {
            try {
                DB::statement("ALTER TABLE {$tableName} DROP CONSTRAINT [{$fk['name']}]");
            } catch (Exception $e) {}
        }

        foreach ($structure['constraints'] as $constraint) {
            try {
                DB::statement("ALTER TABLE {$tableName} DROP CONSTRAINT [{$constraint['name']}]");
            } catch (Exception $e) {}
        }

        foreach ($structure['indexes'] as $index) {
            try {
                DB::statement("DROP INDEX [{$index['name']}] ON {$tableName}");
            } catch (Exception $e) {}
        }
    }

    private function restoreTableStructure(string $tableName, array $structure): void
    {
        foreach ($structure['indexes'] as $index) {
            try {
                $unique = $index['is_unique'] ? 'UNIQUE' : '';
                $type = $index['type'] === 'CLUSTERED' ? 'CLUSTERED' : 'NONCLUSTERED';
                DB::statement("CREATE {$unique} {$type} INDEX [{$index['name']}] ON {$tableName} ({$index['columns']})");
            } catch (Exception $e) {
                $this->warn("   ⚠️ Index [{$index['name']}]: " . $e->getMessage());
            }
        }

        foreach ($structure['constraints'] as $constraint) {
            try {
                DB::statement("ALTER TABLE {$tableName} ADD CONSTRAINT [{$constraint['name']}] CHECK {$constraint['definition']}");
            } catch (Exception $e) {
                $this->warn("   ⚠️ Contrainte [{$constraint['name']}]: " . $e->getMessage());
            }
        }

        foreach ($structure['foreign_keys'] as $fk) {
            try {
                DB::statement("
                    ALTER TABLE {$tableName}
                    ADD CONSTRAINT [{$fk['name']}]
                    FOREIGN KEY ([{$fk['column']}])
                    REFERENCES {$fk['referenced_table']} ([{$fk['referenced_column']}])
                ");
            } catch (Exception $e) {
                $this->warn("   ⚠️ FK [{$fk['name']}]: " . $e->getMessage());
            }
        }
    }
}
