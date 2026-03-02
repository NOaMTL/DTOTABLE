<?php

namespace App\Http\Controllers\Api;

use App\Actions\Exports\ExportTableauToPdfAction;
use App\DataTransferObjects\TableauFilterDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExportTableauRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportController extends Controller
{
    public function __construct(
        private ExportTableauToPdfAction $exportAction
    ) {}

    public function exportPdf(ExportTableauRequest $request): JsonResponse
    {
        $filters = TableauFilterDTO::fromRequest($request->validated());
        
        $result = $this->exportAction->execute(
            filters: $filters,
            userId: $request->user()->id ?? 1,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Export PDF généré avec succès',
            'filename' => $result['filename'],
            'download_url' => route('api.export.download', ['filename' => $result['filename']]),
            'records_count' => $result['records_count'],
            'execution_time' => $result['execution_time'],
        ]);
    }

    public function download(string $filename): BinaryFileResponse
    {
        $filepath = storage_path('app/public/exports/' . $filename);

        if (!file_exists($filepath)) {
            abort(404, 'Fichier non trouvé');
        }

        return response()->download($filepath, $filename, [
            'Content-Type' => 'application/pdf',
        ])->deleteFileAfterSend(true);
    }
}
