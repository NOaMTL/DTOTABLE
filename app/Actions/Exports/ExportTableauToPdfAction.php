<?php

namespace App\Actions\Exports;

use App\DataTransferObjects\TableauFilterDTO;
use App\Repositories\TableauRepository;
use App\Services\ExportLogService;
use App\Services\PdfExportService;
use Exception;

class ExportTableauToPdfAction
{
    public function __construct(
        private TableauRepository $tableauRepository,
        private PdfExportService $pdfExportService,
        private ExportLogService $exportLogService
    ) {}

    public function execute(
        TableauFilterDTO $filters,
        int $userId,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array {
        $startTime = microtime(true);

        try {
            // Récupérer les données filtrées
            $data = $this->tableauRepository->getFilteredDataForExport($filters);

            if ($data->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'Aucune donnée à exporter',
                ];
            }

            // Générer le PDF
            $filename = $this->pdfExportService->generatePdf($data, $filters->toArray());
            $filepath = storage_path('app/public/exports/' . $filename);
            $filesize = file_exists($filepath) ? filesize($filepath) : 0;

            // Calculer le temps d'exécution
            $executionTime = round(microtime(true) - $startTime, 3);

            // Logger l'export
            $this->exportLogService->logExport(
                userId: $userId,
                exportType: 'pdf',
                filters: $filters->toArray(),
                resultsCount: $data->count(),
                filePath: $filename,
                fileSize: $filesize,
                executionTime: $executionTime,
                ipAddress: $ipAddress,
                userAgent: $userAgent
            );

            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'filesize' => $filesize,
                'records_count' => $data->count(),
                'execution_time' => $executionTime,
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'export: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }
}
