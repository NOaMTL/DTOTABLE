<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DynamicQueryBuilderService
{
    /**
     * Construit une requête dynamique à partir de filtres
     * 
     * @param Builder $query
     * @param array $filters Structure simple ou complexe avec groupes imbriqués
     * @param array $groups Deprecated - utiliser $filters directement avec groupes
     * @return Builder
     */
    public function buildQuery(Builder $query, array $filters, array $groups = []): Builder
    {
        if (empty($filters) && empty($groups)) {
            return $query;
        }

        // Support ancien format avec $groups séparé
        if (!empty($groups)) {
            return $this->buildGroupedQuery($query, $groups);
        }

        // Nouveau format avec groupes intégrés dans filters
        return $this->processFilters($query, $filters);
    }

    /**
     * Traite les filtres (supporte les groupes imbriqués)
     */
    private function processFilters(Builder $query, array $filters, string $firstLogic = 'and'): Builder
    {
        foreach ($filters as $index => $item) {
            $logic = strtolower($item['logic'] ?? ($index === 0 ? $firstLogic : 'and'));
            $method = ($index === 0 && $firstLogic === 'and') ? 'where' : ($logic === 'or' ? 'orWhere' : 'where');

            // C'est un groupe ?
            if (isset($item['type']) && $item['type'] === 'group') {
                $groupFilters = $item['filters'] ?? [];
                $groupLogic = strtolower($item['groupLogic'] ?? 'and');
                
                if (!empty($groupFilters)) {
                    $query->{$method}(function ($q) use ($groupFilters, $groupLogic) {
                        $this->processFilters($q, $groupFilters, $groupLogic);
                    });
                }
            } 
            // C'est un filtre simple
            else if (isset($item['field']) && isset($item['operator'])) {
                $field = $item['field'];
                $operator = $item['operator'];
                $value = $item['value'] ?? null;

                if ($field && $value !== null && $value !== '') {
                    $this->applyOperator($query, $field, $operator, $value, $method);
                }
            }
        }

        return $query;
    }

    /**
     * Applique les filtres simples
     */
    private function applyFilters(Builder $query, array $filters, string $boolean = 'and'): Builder
    {
        foreach ($filters as $index => $filter) {
            $field = $filter['field'] ?? null;
            $operator = $filter['operator'] ?? '=';
            $value = $filter['value'] ?? null;
            $logic = strtolower($filter['logic'] ?? 'and');

            if (!$field || $value === null) {
                continue;
            }

            // Déterminer si c'est AND ou OR
            $method = $index === 0 ? 'where' : ($logic === 'or' ? 'orWhere' : 'where');

            // Appliquer selon l'opérateur
            $query = $this->applyOperator($query, $field, $operator, $value, $method);
        }

        return $query;
    }

    /**
     * Construit une requête avec des groupes (parenthèses)
     */
    private function buildGroupedQuery(Builder $query, array $groups): Builder
    {
        foreach ($groups as $index => $group) {
            $logic = strtolower($group['logic'] ?? 'and');
            $filters = $group['filters'] ?? [];

            if (empty($filters)) {
                continue;
            }

            // Déterminer la méthode (where ou orWhere pour le groupe)
            $method = $index === 0 ? 'where' : ($logic === 'or' ? 'orWhere' : 'where');

            // Créer un groupe de conditions
            $query->{$method}(function ($q) use ($filters) {
                $this->applyFilters($q, $filters);
            });
        }

        return $query;
    }

    /**
     * Applique un opérateur spécifique
     */
    private function applyOperator(Builder $query, string $field, string $operator, mixed $value, string $method = 'where'): Builder
    {
        return match ($operator) {
            '=' => $query->{$method}($field, '=', $value),
            '!=' => $query->{$method}($field, '!=', $value),
            '>' => $query->{$method}($field, '>', $value),
            '<' => $query->{$method}($field, '<', $value),
            '>=' => $query->{$method}($field, '>=', $value),
            '<=' => $query->{$method}($field, '<=', $value),
            
            'contains' => $query->{$method}($field, 'LIKE', "%{$value}%"),
            'starts_with' => $query->{$method}($field, 'LIKE', "{$value}%"),
            'ends_with' => $query->{$method}($field, 'LIKE', "%{$value}"),
            'not_contains' => $query->{$method}($field, 'NOT LIKE', "%{$value}%"),
            
            'between' => is_array($value) && count($value) === 2
                ? $query->{$method.'Between'}($field, [$value[0], $value[1]])
                : $query,
            
            'not_between' => is_array($value) && count($value) === 2
                ? $query->{$method.'NotBetween'}($field, [$value[0], $value[1]])
                : $query,
            
            'in' => is_array($value)
                ? $query->{$method.'In'}($field, $value)
                : $query->{$method.'In'}($field, explode(',', $value)),
            
            'not_in' => is_array($value)
                ? $query->{$method.'NotIn'}($field, $value)
                : $query->{$method.'NotIn'}($field, explode(',', $value)),
            
            'is_null' => $query->{$method.'Null'}($field),
            'is_not_null' => $query->{$method.'NotNull'}($field),
            
            default => $query->{$method}($field, '=', $value),
        };
    }

    /**
     * Compte les résultats sans les charger
     */
    public function count(Builder $query, array $filters, array $groups = []): int
    {
        return $this->buildQuery(clone $query, $filters, $groups)->count();
    }

    /**
     * Exécute la requête avec pagination
     */
    public function execute(Builder $query, array $filters, array $groups = [], int $perPage = 50, array $columns = ['*'])
    {
        return $this->buildQuery($query, $filters, $groups)
            ->select($columns)
            ->paginate($perPage);
    }

    /**
     * Génère le SQL brut (pour debug)
     */
    public function toSql(Builder $query, array $filters, array $groups = []): string
    {
        $builtQuery = $this->buildQuery(clone $query, $filters, $groups);
        
        $sql = $builtQuery->toSql();
        $bindings = $builtQuery->getBindings();
        
        // Remplacer les ? par les valeurs (simplifié pour debug)
        foreach ($bindings as $binding) {
            $value = is_numeric($binding) ? $binding : "'{$binding}'";
            $sql = preg_replace('/\?/', $value, $sql, 1);
        }
        
        return $sql;
    }

    /**
     * Parse une recherche en langage naturel (ex: "âge > 30")
     */
    public function parseNaturalLanguage(string $search): ?array
    {
        $patterns = [
            // "âge > 30" ou "age >= 25"
            '/^(\w+)\s*([><=!]+)\s*(.+)$/' => function ($matches) {
                return [
                    'field' => $matches[1],
                    'operator' => $matches[2],
                    'value' => trim($matches[3]),
                ];
            },
            
            // "nom contient Dupont"
            '/^(\w+)\s+contient\s+(.+)$/i' => function ($matches) {
                return [
                    'field' => $matches[1],
                    'operator' => 'contains',
                    'value' => trim($matches[2]),
                ];
            },
            
            // "ville = Paris" ou "ville : Paris"
            '/^(\w+)\s*[:=]\s*(.+)$/' => function ($matches) {
                return [
                    'field' => $matches[1],
                    'operator' => '=',
                    'value' => trim($matches[2]),
                ];
            },
        ];

        foreach ($patterns as $pattern => $callback) {
            if (preg_match($pattern, $search, $matches)) {
                return $callback($matches);
            }
        }

        return null;
    }
}
