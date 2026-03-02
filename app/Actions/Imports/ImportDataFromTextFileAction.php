<?php

namespace App\Actions\Imports;

use App\Repositories\TableauRepository;
use App\Services\DataImportService;
use Exception;

class ImportDataFromTextFileAction
{
    public function __construct(
        private DataImportService $importService,
        private TableauRepository $repository
    ) {}

    public function execute(string $filePath): array
    {
        try {
            // Parser le fichier
            $data = $this->importService->parseTextFile($filePath);

            if (empty($data)) {
                return [
                    'success' => false,
                    'message' => 'Aucune donnée valide trouvée dans le fichier',
                    'report' => $this->importService->generateImportReport(),
                ];
            }

            // Insertion en masse
            $this->repository->bulkInsert($data);

            $report = $this->importService->generateImportReport();

            return [
                'success' => true,
                'message' => "Import réussi: {$report['success_count']} lignes importées",
                'report' => $report,
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'import: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }
}
