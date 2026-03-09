<?php

namespace App\Services\Import;

use Exception;
use Illuminate\Support\Facades\File;

class QddDownloadService
{
    private array $downloadedFiles = [];
    private array $missingFiles = [];

    /**
     * Télécharger un fichier via QDD
     */
    public function downloadFile(string $fileName, string $fileType, string $remotePath, string $tempDir): ?string
    {
        $remoteFile = $remotePath ? rtrim($remotePath, '/') . '/' . $fileName : $fileName;
        $localFile = $tempDir . '/' . $fileName;

        try {
            $qdd = new \QDDClient();
            $qdd->downloadToFile($remoteFile, $localFile);
            
            if (!file_exists($localFile)) {
                throw new Exception("Le fichier n'a pas été téléchargé");
            }

            $this->downloadedFiles[] = $localFile;
            
            return $localFile;
            
        } catch (Exception $e) {
            $this->missingFiles[] = [
                'file' => $fileName,
                'type' => $fileType,
                'error' => $e->getMessage()
            ];
            
            throw $e;
        }
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
