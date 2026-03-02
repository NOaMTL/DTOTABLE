<?php

namespace App\Repositories;

use App\DataTransferObjects\TableauFilterDTO;
use App\Models\TableauData;
use App\QueryBuilders\TableauQueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TableauRepository
{
    public function __construct(
        private TableauQueryBuilder $queryBuilder
    ) {}

    public function getFilteredData(
        TableauFilterDTO $filters,
        int $page = 1,
        int $perPage = 100
    ): LengthAwarePaginator {
        $query = TableauData::query();
        $query = $this->queryBuilder->buildQuery($query, $filters);

        return $query->orderBy('date_operation', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function getFilteredDataForExport(TableauFilterDTO $filters): Collection
    {
        $query = TableauData::query();
        $query = $this->queryBuilder->buildQuery($query, $filters);

        return $query->orderBy('date_operation', 'desc')
            ->orderBy('id', 'desc')
            ->get();
    }

    public function countFilteredData(TableauFilterDTO $filters): int
    {
        $query = TableauData::query();
        $query = $this->queryBuilder->buildQuery($query, $filters);

        return $query->count();
    }

    public function findById(int $id): ?TableauData
    {
        return TableauData::find($id);
    }

    public function create(array $data): TableauData
    {
        return TableauData::create($data);
    }

    public function bulkInsert(array $data): bool
    {
        return TableauData::insert($data);
    }

    public function getUniqueValues(string $column): Collection
    {
        return TableauData::distinct()
            ->pluck($column)
            ->filter()
            ->sort()
            ->values();
    }
}
