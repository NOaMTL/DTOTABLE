<?php

namespace App\DataTransferObjects;

class ExportLogDTO
{
    public function __construct(
        public readonly int $userId,
        public readonly string $exportType,
        public readonly array $filters,
        public readonly int $resultsCount,
        public readonly string $filePath,
        public readonly int $fileSize,
        public readonly float $executionTime,
        public readonly ?string $ipAddress = null,
        public readonly ?string $userAgent = null,
    ) {}

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'export_type' => $this->exportType,
            'filters' => $this->filters,
            'results_count' => $this->resultsCount,
            'file_path' => $this->filePath,
            'file_size' => $this->fileSize,
            'execution_time' => $this->executionTime,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
        ];
    }
}
