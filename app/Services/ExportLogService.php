<?php

namespace App\Services;

use App\DataTransferObjects\ExportLogDTO;
use App\Repositories\ExportLogRepository;
use App\Models\ExportLog;

class ExportLogService
{
    public function __construct(
        private ExportLogRepository $repository
    ) {}

    public function logExport(
        int $userId,
        string $exportType,
        array $filters,
        int $resultsCount,
        string $filePath,
        int $fileSize,
        float $executionTime,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): ExportLog {
        $dto = new ExportLogDTO(
            userId: $userId,
            exportType: $exportType,
            filters: $filters,
            resultsCount: $resultsCount,
            filePath: $filePath,
            fileSize: $fileSize,
            executionTime: $executionTime,
            ipAddress: $ipAddress,
            userAgent: $userAgent
        );

        return $this->repository->create($dto);
    }

    public function getRecentExportsByUser(int $userId, int $limit = 10)
    {
        return $this->repository->getRecentByUser($userId, $limit);
    }

    public function getAllExports(int $limit = 100)
    {
        return $this->repository->getAll($limit);
    }
}
