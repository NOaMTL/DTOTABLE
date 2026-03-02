<?php

namespace App\QueryBuilders;

use App\DataTransferObjects\TableauFilterDTO;
use Illuminate\Database\Eloquent\Builder;

class TableauQueryBuilder
{
    public function buildQuery(Builder $query, TableauFilterDTO $filters): Builder
    {
        // Appliquer les filtres AGGrid si présents
        if (!empty($filters->agGridFilterModel)) {
            $this->applyAgGridFilters($query, $filters->agGridFilterModel);
        }

        // Appliquer les filtres personnalisés
        if ($filters->dateDebut) {
            $query->where('date_operation', '>=', $filters->dateDebut);
        }

        if ($filters->dateFin) {
            $query->where('date_operation', '<=', $filters->dateFin);
        }

        if ($filters->compte) {
            $query->where('compte', $filters->compte);
        }

        if ($filters->montantMin !== null) {
            $query->where('montant', '>=', $filters->montantMin);
        }

        if ($filters->montantMax !== null) {
            $query->where('montant', '<=', $filters->montantMax);
        }

        if ($filters->devise) {
            $query->where('devise', $filters->devise);
        }

        if ($filters->typeOperation) {
            $query->where('type_operation', $filters->typeOperation);
        }

        if ($filters->statut) {
            $query->where('statut', $filters->statut);
        }

        if ($filters->searchTerm) {
            $searchTerm = '%' . $filters->searchTerm . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('reference', 'like', $searchTerm)
                    ->orWhere('libelle', 'like', $searchTerm)
                    ->orWhere('compte', 'like', $searchTerm)
                    ->orWhere('agence', 'like', $searchTerm);
            });
        }

        return $query;
    }

    private function applyAgGridFilters(Builder $query, array $filterModel): void
    {
        foreach ($filterModel as $field => $filter) {
            $filterType = $filter['filterType'] ?? $filter['type'] ?? 'text';

            match ($filterType) {
                'text' => $this->applyTextFilter($query, $field, $filter),
                'number' => $this->applyNumberFilter($query, $field, $filter),
                'date' => $this->applyDateFilter($query, $field, $filter),
                'set' => $this->applySetFilter($query, $field, $filter),
                default => null,
            };
        }
    }

    private function applyTextFilter(Builder $query, string $field, array $filter): void
    {
        $type = $filter['type'] ?? 'contains';
        $filterValue = $filter['filter'] ?? '';

        match ($type) {
            'equals' => $query->where($field, '=', $filterValue),
            'notEqual' => $query->where($field, '!=', $filterValue),
            'contains' => $query->where($field, 'like', "%{$filterValue}%"),
            'notContains' => $query->where($field, 'not like', "%{$filterValue}%"),
            'startsWith' => $query->where($field, 'like', "{$filterValue}%"),
            'endsWith' => $query->where($field, 'like', "%{$filterValue}"),
            'blank' => $query->whereNull($field)->orWhere($field, '=', ''),
            'notBlank' => $query->whereNotNull($field)->where($field, '!=', ''),
            default => null,
        };
    }

    private function applyNumberFilter(Builder $query, string $field, array $filter): void
    {
        $type = $filter['type'] ?? 'equals';
        $filterValue = $filter['filter'] ?? 0;
        $filterTo = $filter['filterTo'] ?? null;

        match ($type) {
            'equals' => $query->where($field, '=', $filterValue),
            'notEqual' => $query->where($field, '!=', $filterValue),
            'lessThan' => $query->where($field, '<', $filterValue),
            'lessThanOrEqual' => $query->where($field, '<=', $filterValue),
            'greaterThan' => $query->where($field, '>', $filterValue),
            'greaterThanOrEqual' => $query->where($field, '>=', $filterValue),
            'inRange' => $filterTo ? $query->whereBetween($field, [$filterValue, $filterTo]) : null,
            'blank' => $query->whereNull($field),
            'notBlank' => $query->whereNotNull($field),
            default => null,
        };
    }

    private function applyDateFilter(Builder $query, string $field, array $filter): void
    {
        $type = $filter['type'] ?? 'equals';
        $dateFrom = $filter['dateFrom'] ?? null;
        $dateTo = $filter['dateTo'] ?? null;

        if (!$dateFrom) {
            return;
        }

        match ($type) {
            'equals' => $query->whereDate($field, '=', $dateFrom),
            'notEqual' => $query->whereDate($field, '!=', $dateFrom),
            'lessThan' => $query->whereDate($field, '<', $dateFrom),
            'greaterThan' => $query->whereDate($field, '>', $dateFrom),
            'inRange' => $dateTo ? $query->whereBetween($field, [$dateFrom, $dateTo]) : null,
            'blank' => $query->whereNull($field),
            'notBlank' => $query->whereNotNull($field),
            default => null,
        };
    }

    private function applySetFilter(Builder $query, string $field, array $filter): void
    {
        $values = $filter['values'] ?? [];

        if (!empty($values)) {
            $query->whereIn($field, $values);
        }
    }
}
