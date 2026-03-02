<?php

namespace App\Repositories;

use App\DataTransferObjects\ExportLogDTO;
use App\Models\ExportLog;
use Illuminate\Database\Eloquent\Collection;

class ExportLogRepository
{
    public function create(ExportLogDTO $dto): ExportLog
    {
        return ExportLog::create($dto->toArray());
    }

    public function getRecentByUser(int $userId, int $limit = 10): Collection
    {
        return ExportLog::byUser($userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getAll(int $limit = 100): Collection
    {
        return ExportLog::with('user')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
