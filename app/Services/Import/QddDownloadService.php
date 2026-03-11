<?php

namespace App\Services\Import;

use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class QddDownloadService
{
    private array $downloadedFiles = [];
    private array $missingFiles = [];
    private ?\QDDClient $qddClient = null;
    private ?int $lastConnectionTime = null;
    private int $maxConnectionDuration = 1500; // 25 minutes (5min de marge)

    /**
     * Obtenir ou créer une connexion QDD valide
     */
    private function getQddClient(): \QDDClient
    {
        $now = time();
        
        // Si pas de client OU si connexion > 25 minutes → reconnecter
        if ($this->qddClient === null || 
            ($this->lastConnectionTime !== null && ($now - $this->lastConnectionTime) > $this->maxConnectionDuration)) {
            
            if ($this->qddClient !== null) {
                Log::info('QDD: Reconnexion préventive (session > 25min)');
            }
            
            $this->qddClient = new \QDDClient();
            $this->lastConnectionTime = $now;
        }
        
        return $this->qddClient;
    }

    /**
     * Télécharger un fichier via QDD avec gestion automatique de la reconnexion
     */
    public function downloadFile(string $fileName, string $fileType, string $remotePath, string $tempDir): ?string
    {
        $remoteFile = $remotePath ? rtrim($remotePath, '/') . '/' . $fileName : $fileName;
        $localFile = $tempDir . '/' . $fileName;

        $maxRetries = 2;
        $attempt = 0;

        while ($attempt < $maxRetries) {
            try {
                $qdd = $this->getQddClient();
                $qdd->downloadToFile($remoteFile, $localFile);
                
                if (!file_exists($localFile)) {
                    throw new Exception("Le fichier n'a pas été téléchargé");
                }

                $this->downloadedFiles[] = $localFile;
                
                return $localFile;
                
            } catch (Exception $e) {
                $attempt++;
                $errorMessage = $e->getMessage();
                $errorCode = method_exists($e, 'getCode') ? $e->getCode() : 0;
                
                // Si erreur 403 ou erreur d'autorisation → forcer reconnexion
                if ($errorCode === 403 || 
                    stripos($errorMessage, '403') !== false ||
                    stripos($errorMessage, 'forbidden') !== false ||
                    stripos($errorMessage, 'authorization') !== false ||
                    stripos($errorMessage, 'authentication') !== false ||
                    stripos($errorMessage, 'expired') !== false) {
                    
                    Log::warning("QDD: Erreur d'autorisation détectée (tentative $attempt/$maxRetries)", [
                        'error' => $errorMessage,
                        'code' => $errorCode,
                        'file' => $fileName
                    ]);
                    
                    // Forcer reconnexion
                    $this->qddClient = null;
                    $this->lastConnectionTime = null;
                    
                    if ($attempt < $maxRetries) {
                        sleep(1); // Attendre 1 seconde avant retry
                        continue;
                    }
                }
                
                // Autre erreur ou max retries atteint
                $this->missingFiles[] = [
                    'file' => $fileName,
                    'type' => $fileType,
                    'error' => $errorMessage
                ];
                
                throw $e;
            }
        }
        
        throw new Exception("Échec du téléchargement après $maxRetries tentatives");
    }

    /**
     * Nettoyer les fichiers téléchargés
     */
    public function cleanup(string $tempDir): int
    {
        $deleted = 0;
        
        foreach ($this->downloadedFiles as $file) {
            if (file_exists($file)) {
                try {
                    unlink($file);
                    $deleted++;
                } catch (Exception $e) {
                    // Ignorer
                }
            }
        }
        
        // Supprimer le dossier temporaire s'il est vide
        if (File::isDirectory($tempDir) && count(File::files($tempDir)) === 0) {
            File::deleteDirectory($tempDir);
        }
        
        return $deleted;
    }

    /**
     * Supprimer un fichier immédiatement après traitement
     */
    public function deleteFile(string $filePath): bool
    {
        if (file_exists($filePath)) {
            try {
                unlink($filePath);
                return true;
            } catch (Exception $e) {
                return false;
            }
        }
        
        return false;
    }

    public function getDownloadedFiles(): array
    {
        return $this->downloadedFiles;
    }

    public function getMissingFiles(): array
    {
        return $this->missingFiles;
    }
}
