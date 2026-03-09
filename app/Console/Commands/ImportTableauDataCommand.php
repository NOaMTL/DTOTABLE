<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Exception;

class ImportTableauDataCommand extends Command
{
    protected $signature = 'tableau:import 
                            {type : Type d\'import (ClientCommercial ou Partenaire)}
                            {--chunk-size=1000 : Nombre de lignes par batch}
                            {--truncate : Vider la table avant import}
                            {--drop-indexes : Supprimer les indexes pendant import (plus rapide)}
                            {--keep-files : Conserver les fichiers après import}';

    protected $description = 'Import optimisé des fichiers de données via QDD (ClientCommercial ou Partenaire)';

    private int $totalProcessed = 0;
    private int $totalSuccess = 0;
    private int $totalErrors = 0;
    private array $errors = [];
    private int $chunkSize;
    private array $missingFiles = [];
    private array $downloadedFiles = [];
    private string $tempDir;
    private string $importType;
    private string $tableName;
    private array $columnMapping;
    private array $logs = [];

    public function handle(): int
    {
        $startTime = microtime(true);
        $this->chunkSize = (int) $this->option('chunk-size');
        $this->tempDir = storage_path('app/temp_imports');
        
        // Récupérer et valider le type d'import
        $this->importType = $this->argument('type');
        
        if (!$this->validateImportType($this->importType)) {
            $this->error("❌ Type d'import invalide: {$this->importType}");
            $this->line("Types acceptés: ClientCommercial, Partenaire");
            return self::FAILURE;
        }
        
        // Initialiser la table et le mapping selon le type
        $this->tableName = $this->getTableName($this->importType);
        $this->columnMapping = $this->getColumnMapping($this->importType);

        $this->info("╔═══════════════════════════════════════════════════════════════╗");
        $this->info("║     Import Optimisé - {$this->importType} via QDD            ║");
        $this->info("╚═══════════════════════════════════════════════════════════════╝");
        $this->newLine();
        $this->info("📋 Type: <fg=cyan>{$this->importType}</>");
        $this->info("🗄️  Table: <fg=cyan>{$this->tableName}</>");
        $this->newLine();

        // Logger le démarrage
        $this->addLog('import_started', [
            'type' => $this->importType,
            'table' => $this->tableName,
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

        // Définir la liste des fichiers attendus avec leurs types
        $expectedFiles = $this->getExpectedFiles($this->importType);

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
        $this->optimizePerformance();

        // Supprimer indexes si demandé
        $droppedIndexes = [];
        if ($this->option('drop-indexes')) {
            $droppedIndexes = $this->dropIndexes();
        }

        // Truncate si demandé
        if ($this->option('truncate')) {
            $this->info("🗑️  Suppression des données existantes ({$this->tableName})...");
            DB::table($this->tableName)->truncate();
            $this->info('✅ Table vidée');
            $this->addLog('table_truncated', ['table' => $this->tableName]);
            $this->newLine();
        }

        // Télécharger et traiter chaque fichier
        $fileNumber = 0;
        foreach ($expectedFiles as $fileInfo) {
            $fileNumber++;
            [$fileName, $fileType] = $fileInfo;
            
            $this->processFileFromQDD($fileName, $fileType, $fileNumber, count($expectedFiles), $remotePath);
        }

        // Restaurer les indexes
        if (!empty($droppedIndexes)) {
            $this->restoreIndexes($droppedIndexes);
        }

        // Restaurer les paramètres DB
        $this->restorePerformance();

        // Nettoyer le dossier temporaire (les fichiers ont déjà été supprimés individuellement)
        if (!$this->option('keep-files')) {
            $this->cleanupTempDirectory();
        }

        // Afficher le résumé
        $this->displaySummary($startTime);

        // Envoyer les logs à ApikLog
        $this->sendLogsToApikLog($startTime);

        return $this->totalErrors > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Valider le type d'import
     */
    private function validateImportType(string $type): bool
    {
        return in_array($type, ['ClientCommercial', 'Partenaire']);
    }

    /**
     * Obtenir le nom de la table selon le type
     */
    private function getTableName(string $type): string
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
    private function getColumnMapping(string $type): array
    {
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

    /**
     * Définir la liste des fichiers attendus selon le type
     * Format: [["nom_fichier.txt", "TypeA"], ["autre_fichier.txt", "TypeB"]]
     */
    private function getExpectedFiles(string $type): array
    {
        $configKey = match($type) {
            'ClientCommercial' => 'imports.files.client_commercial',
            'Partenaire' => 'imports.files.partenaire',
            default => 'imports.expected_files',
        };
        
        return config($configKey, []);
    }

    private function processFileFromQDD(string $fileName, string $fileType, int $fileNumber, int $totalFiles, string $remotePath): void
    {
        $this->info("📄 [{$fileNumber}/{$totalFiles}] {$fileName} (Type: {$fileType})");

        // Construire les chemins
        $remoteFile = $remotePath ? rtrim($remotePath, '/') . '/' . $fileName : $fileName;
        $localFile = $this->tempDir . '/' . $fileName;

        // Télécharger le fichier via QDD
        try {
            $this->line("  ⬇️  Téléchargement depuis QDD...");
            
            $qdd = new \QDDClient();
            $qdd->downloadToFile($remoteFile, $localFile);
            
            $this->downloadedFiles[] = $localFile;
            
            if (!file_exists($localFile)) {
                throw new Exception("Le fichier n'a pas été téléchargé");
            }

            $fileSize = $this->formatBytes(filesize($localFile));
            $this->line("  ✅ Téléchargé ({$fileSize})");
            
            $this->addLog('file_downloaded', [
                'file' => $fileName,
                'type' => $fileType,
                'size' => filesize($localFile),
                'size_formatted' => $fileSize,
            ]);
            
        } catch (Exception $e) {
            $this->error("  ❌ Erreur téléchargement: {$e->getMessage()}");
            $this->missingFiles[] = [
                'file' => $fileName,
                'type' => $fileType,
                'error' => $e->getMessage()
            ];
            $this->addLog('file_download_failed', [
                'file' => $fileName,
                'type' => $fileType,
                'error' => $e->getMessage(),
            ], 'error');
            $this->newLine();
            return;
        }

        // Traiter le fichier
        $this->processLocalFile($localFile, $fileName, $fileType);
        
        // Supprimer le fichier immédiatement après traitement (optimisation K8s)
        if (!$this->option('keep-files')) {
            if (file_exists($localFile)) {
                try {
                    unlink($localFile);
                    $this->line("  🗑️  Fichier supprimé");
                } catch (Exception $e) {
                    // Ignorer les erreurs
                }
            }
        }
    }

    private function processLocalFile(string $filePath, string $fileName, string $fileType): void
    {
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

            // Lecture ligne par ligne (streaming - pas de limite mémoire)
            while (($line = fgets($handle)) !== false) {
                $lineNumber++;
                $line = trim($line);

                // Ignorer la ligne 1 (entête)
                if ($lineNumber === 1) {
                    continue;
                }

                // Ignorer la ligne 2 (metadata/format)
                if ($lineNumber === 2) {
                    continue;
                }

                // Ignorer les lignes vides
                if (empty($line)) {
                    continue;
                }

                try {
                    $parsed = $this->parseLine($line, $fileType, $this->columnMapping, $fileName);
                    
                    if ($this->validateData($parsed, $this->columnMapping)) {
                        $batch[] = $parsed;
                        $fileSuccess++;

                        // Bulk insert quand le batch est plein
                        if (count($batch) >= $this->chunkSize) {
                            $this->insertBatch($batch);
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

            // Insérer le reste du batch
            if (!empty($batch)) {
                $this->insertBatch($batch);
                $bar->advance(count($batch));
            }

            $bar->finish();
            $this->newLine();

            fclose($handle);

            $this->totalSuccess += $fileSuccess;
            $this->totalErrors += $fileErrors;
            $this->totalProcessed += $lineNumber;

            $this->line("  ✅ Succès: {$fileSuccess} | ❌ Erreurs: {$fileErrors}");
            $this->addLog('file_processed', [
                'file' => $fileName,
                'type' => $fileType,
                'lines_processed' => $lineNumber,
                'success' => $fileSuccess,
                'errors' => $fileErrors,
            ]);
            $this->newLine();

        } catch (Exception $e) {
            $this->error("  ❌ Erreur fichier: {$e->getMessage()}");
            $this->addLog('file_processing_failed', [
                'file' => $fileName,
                'type' => $fileType,
                'error' => $e->getMessage(),
            ], 'error');
            $this->newLine();
        }
    }

    private function insertBatch(array $batch): void
    {
        if (empty($batch)) {
            return;
        }

        try {
            // Ajouter les timestamps
            $now = now();
            $batch = array_map(function ($item) use ($now) {
                $item['created_at'] = $now;
                $item['updated_at'] = $now;
                return $item;
            }, $batch);

            // Utiliser une méthode différente selon le driver
            if (DB::getDriverName() === 'sqlsrv') {
                $this->insertBatchSqlServer($batch);
            } else {
                DB::transaction(function () use ($batch) {
                    DB::table($this->tableName)->insert($batch);
                });
            }
        } catch (Exception $e) {
            $this->error("Erreur lors de l'insertion batch: {$e->getMessage()}");
        }
    }

    /**
     * Insert optimisé pour SQL Server (contourne la limite des 2100 paramètres)
     */
    private function insertBatchSqlServer(array $batch): void
    {
        // Diviser en sous-batchs de 250 lignes
        $chunks = array_chunk($batch, 250);
        
        DB::transaction(function () use ($chunks) {
            foreach ($chunks as $chunk) {
                $this->insertChunkWithLiterals($chunk);
            }
        });
    }

    /**
     * Construire et exécuter un INSERT avec des valeurs littérales
     */
    private function insertChunkWithLiterals(array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        $columns = array_keys($rows[0]);
        $columnList = implode(', ', array_map(fn($col) => "[$col]", $columns));

        $valuesList = [];
        foreach ($rows as $row) {
            $values = [];
            foreach ($columns as $column) {
                $value = $row[$column];
                $values[] = $this->formatValueForSql($value);
            }
            $valuesList[] = '(' . implode(', ', $values) . ')';
        }

        $sql = "INSERT INTO [{$this->tableName}] ({$columnList}) VALUES " . implode(', ', $valuesList);
        DB::statement($sql);
    }

    /**
     * Formater une valeur pour l'insertion SQL
     */
    private function formatValueForSql($value): string
    {
        if (is_null($value)) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        // Échapper les chaînes de caractères
        $escaped = str_replace("'", "''", (string) $value);
        return "N'{$escaped}'";
    }

    private function parseLine(string $line, string $fileType, array $mapping, string $fileName = ''): array
    {
        // Le délimiteur est toujours \t (tabulation)
        $fields = explode("\t", $line);

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
                    // Valeur fixe
                    $value = $config['value'];
                } elseif (isset($config['file_index'])) {
                    // Valeur depuis le fichier
                    $value = $fields[$config['file_index']] ?? '';
                } elseif (isset($config['file_type']) && $config['file_type']) {
                    // Type du fichier
                    $value = $fileType;
                } elseif (isset($config['file_name']) && $config['file_name']) {
                    // Nom du fichier
                    $value = $fileName;
                } else {
                    $value = '';
                }
            } else {
                // Ancien format : config est directement un index
                $value = $fields[$config] ?? '';
            }
            
            // Traitement spécifique selon le nom de colonne ou le type de valeur
            if (isset($config['value'])) {
                // Valeur fixe : pas de traitement
                $data[$columnName] = $value;
            } elseif (str_contains($columnName, 'date')) {
                $data[$columnName] = !empty(trim($value)) ? $this->parseDate($value) : null;
            } elseif (str_contains($columnName, 'montant') || str_contains($columnName, 'taux')) {
                $data[$columnName] = !empty(trim($value)) ? $this->parseAmount($value) : 0;
            } else {
                $data[$columnName] = trim($value);
            }
        }

        return $data;
    }

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

    private function validateData(array $data, array $mapping): bool
    {
        // Validation selon le type d'import
        if ($this->importType === 'ClientCommercial') {
            return !empty($data['reference']) 
                && !empty($data['date_operation'])
                && isset($data['montant']);
        } elseif ($this->importType === 'Partenaire') {
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

    private function optimizePerformance(): void
    {
        // Désactiver les logs de requêtes (économise beaucoup de mémoire)
        DB::connection()->disableQueryLog();

        // Augmenter la mémoire si nécessaire
        ini_set('memory_limit', '512M');

        // Optimisations MySQL/PostgreSQL
        try {
            if (DB::getDriverName() === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
                DB::statement('SET UNIQUE_CHECKS=0');
                DB::statement('SET AUTOCOMMIT=0');
            }
        } catch (Exception $e) {
            // Ignorer si pas supporté
        }
    }

    private function restorePerformance(): void
    {
        try {
            if (DB::getDriverName() === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
                DB::statement('SET UNIQUE_CHECKS=1');
                DB::statement('SET AUTOCOMMIT=1');
            }
        } catch (Exception $e) {
            // Ignorer
        }
    }

    private function dropIndexes(): array
    {
        $this->info('🔧 Suppression temporaire des indexes pour accélérer l\'import...');
        
        // Obtenir les indexes selon le type
        $indexes = $this->getIndexesForType($this->importType, $this->tableName);

        $dropped = [];
        
        foreach ($indexes as $index) {
            try {
                DB::statement("ALTER TABLE {$this->tableName} DROP INDEX {$index}");
                $dropped[] = $index;
            } catch (Exception $e) {
                // Index n'existe peut-être pas
            }
        }

        $this->info('✅ ' . count($dropped) . ' indexes supprimés');
        return $dropped;
    }

    private function getIndexesForType(string $type, string $tableName): array
    {
        // Retourner les indexes selon le type et la table
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

    private function restoreIndexes(array $indexes): void
    {
        $this->newLine();
        $this->info('🔧 Reconstruction des indexes...');
        
        // Génération dynamique des définitions d'index
        $indexDefinitions = $this->getIndexDefinitions($this->importType, $this->tableName);

        foreach ($indexes as $index) {
            if (isset($indexDefinitions[$index])) {
                try {
                    DB::statement($indexDefinitions[$index]);
                    $this->line("  ✅ {$index}");
                } catch (Exception $e) {
                    $this->warn("  ⚠️  Erreur reconstruction {$index}: {$e->getMessage()}");
                }
            }
        }

        $this->info('✅ Indexes reconstruits');
    }

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

    private function logError(string $file, int $line, string $message): void
    {
        $this->errors[] = [
            'file' => $file,
            'line' => $line,
            'message' => $message,
        ];

        // Limiter les erreurs stockées en mémoire
        if (count($this->errors) > 100) {
            array_shift($this->errors);
        }
    }

    private function cleanupFiles(): void
    {
        $this->newLine();
        $this->info('🗑️  Nettoyage des fichiers téléchargés...');
        
        $deleted = 0;
        foreach ($this->downloadedFiles as $file) {
            if (file_exists($file)) {
                try {
                    unlink($file);
                    $deleted++;
                } catch (Exception $e) {
                    $this->warn("  ⚠️  Impossible de supprimer: " . basename($file));
                }
            }
        }
        
        $this->line("  ✅ {$deleted} fichier(s) supprimé(s)");
        
        // Supprimer le dossier temporaire s'il est vide
        if (File::isDirectory($this->tempDir) && count(File::files($this->tempDir)) === 0) {
            File::deleteDirectory($this->tempDir);
        }
    }

    private function cleanupTempDirectory(): void
    {
        // Nettoyer uniquement le dossier si vide (les fichiers ont déjà été supprimés)
        if (File::isDirectory($this->tempDir) && count(File::files($this->tempDir)) === 0) {
            File::deleteDirectory($this->tempDir);
            $this->newLine();
            $this->info('🗑️  Dossier temporaire nettoyé');
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

        // Afficher les fichiers manquants
        if (!empty($this->missingFiles)) {
            $this->error("❌ Fichiers manquants ou non téléchargés:");
            foreach ($this->missingFiles as $missing) {
                $this->line("  • {$missing['file']} (Type: {$missing['type']}) - {$missing['error']}");
            }
            $this->newLine();
        }

        // Afficher quelques erreurs
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

    /**
     * Ajouter un log pour l'envoi centralisé
     */
    private function addLog(string $event, array $data = [], string $level = 'info'): void
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
    private function sendLogsToApikLog(float $startTime): void
    {
        try {
            $duration = microtime(true) - $startTime;
            $apikLog = new \ApikLog();
            
            // Préparer le payload complet
            $logPayload = [
                'import_type' => $this->importType,
                'table' => $this->tableName,
                'summary' => [
                    'total_processed' => $this->totalProcessed,
                    'total_success' => $this->totalSuccess,
                    'total_errors' => $this->totalErrors,
                    'duration' => $this->formatDuration($duration),
                    'duration_raw_seconds' => round($duration, 2),
                    'rate_per_second' => $this->totalSuccess > 0 ? round($this->totalSuccess / $duration, 2) : 0,
                ],
                'missing_files' => $this->missingFiles,
                'errors' => array_slice($this->errors, -50), // Limiter aux 50 dernières erreurs
                'events' => $this->logs,
                'metadata' => [
                    'chunk_size' => $this->chunkSize,
                    'truncate' => $this->option('truncate'),
                    'drop_indexes' => $this->option('drop-indexes'),
                    'keep_files' => $this->option('keep-files'),
                    'executed_at' => now()->toDateTimeString(),
                    'user' => get_current_user(),
                    'php_version' => PHP_VERSION,
                    'laravel_version' => app()->version(),
                ],
            ];

            // Envoyer le log général
            $apikLog->log('tableau_import', $logPayload);
            
            // Envoyer les erreurs si présentes
            if ($this->totalErrors > 0 || !empty($this->missingFiles)) {
                $errorPayload = [
                    'import_type' => $this->importType,
                    'table' => $this->tableName,
                    'total_errors' => $this->totalErrors,
                    'missing_files_count' => count($this->missingFiles),
                    'missing_files' => $this->missingFiles,
                    'sample_errors' => array_slice($this->errors, -10),
                    'duration' => $this->formatDuration($duration),
                ];
                
                $apikLog->error('tableau_import_errors', $errorPayload);
                $this->info('❌ Erreurs envoyées à ApikLog');
            } else {
                // Envoyer le succès
                $successPayload = [
                    'import_type' => $this->importType,
                    'table' => $this->tableName,
                    'total_success' => $this->totalSuccess,
                    'duration' => $this->formatDuration($duration),
                    'rate_per_second' => round($this->totalSuccess / $duration, 2),
                    'files_processed' => count($this->downloadedFiles),
                ];
                
                $apikLog->success('tableau_import_success', $successPayload);
                $this->info('✅ Succès envoyé à ApikLog');
            }
            
            $this->newLine();
            $this->info('📤 Logs envoyés à ApikLog');
            
        } catch (Exception $e) {
            $this->warn("⚠️  Impossible d'envoyer les logs à ApikLog: {$e->getMessage()}");
        }
    }
}
