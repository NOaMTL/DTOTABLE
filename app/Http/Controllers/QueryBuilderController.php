<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Services\DynamicQueryBuilderService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class QueryBuilderController extends Controller
{
    public function __construct(
        private DynamicQueryBuilderService $queryBuilder
    ) {}

    /**
     * Affiche la page du Query Builder (POC)
     */
    public function index(): Response
    {
        return Inertia::render('QueryBuilder/Index', [
            'fields' => Client::getQueryBuilderFields(),
            'totalClients' => Client::count(),
        ]);
    }

    /**
     * Exécute une requête et retourne les résultats
     */
    public function execute(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'filters' => 'nullable|array',
            'filters.*.field' => 'required|string',
            'filters.*.operator' => 'required|string',
            'filters.*.value' => 'nullable',
            'filters.*.logic' => 'nullable|string|in:and,or,AND,OR',
            'groups' => 'nullable|array',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:500',
            'columns' => 'nullable|array',
        ]);

        $filters = $validated['filters'] ?? [];
        $groups = $validated['groups'] ?? [];
        $perPage = $validated['per_page'] ?? 50;
        $columns = $validated['columns'] ?? ['*'];

        try {
            $query = Client::query();
            
            // Compter d'abord
            $count = $this->queryBuilder->count($query, $filters, $groups);
            
            // Puis paginer
            $results = $this->queryBuilder->execute(
                $query,
                $filters,
                $groups,
                $perPage,
                $columns
            );

            return response()->json([
                'success' => true,
                'data' => $results->items(),
                'total' => $count,
                'per_page' => $results->perPage(),
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'exécution de la requête',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Compte uniquement les résultats (pour l'aperçu en temps réel)
     */
    public function count(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'filters' => 'nullable|array',
            'groups' => 'nullable|array',
        ]);

        $filters = $validated['filters'] ?? [];
        $groups = $validated['groups'] ?? [];

        try {
            $count = $this->queryBuilder->count(Client::query(), $filters, $groups);

            return response()->json([
                'success' => true,
                'count' => $count,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du comptage',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Génère le SQL de la requête (pour debug)
     */
    public function sql(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'filters' => 'nullable|array',
            'groups' => 'nullable|array',
        ]);

        $filters = $validated['filters'] ?? [];
        $groups = $validated['groups'] ?? [];

        try {
            $sql = $this->queryBuilder->toSql(Client::query(), $filters, $groups);

            return response()->json([
                'success' => true,
                'sql' => $sql,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du SQL',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Parse une recherche en langage naturel
     */
    public function parseSearch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => 'required|string',
        ]);

        $parsed = $this->queryBuilder->parseNaturalLanguage($validated['search']);

        return response()->json([
            'success' => $parsed !== null,
            'filter' => $parsed,
        ]);
    }

    /**
     * Exporte les résultats (CSV)
     */
    public function export(Request $request)
    {
        $validated = $request->validate([
            'filters' => 'nullable|array',
            'groups' => 'nullable|array',
            'format' => 'nullable|string|in:csv,xlsx',
        ]);

        $filters = $validated['filters'] ?? [];
        $groups = $validated['groups'] ?? [];
        $format = $validated['format'] ?? 'csv';

        try {
            $query = Client::query();
            $results = $this->queryBuilder->buildQuery($query, $filters, $groups)->get();

            if ($format === 'csv') {
                return $this->exportCsv($results);
            }

            // TODO: Implémenter export Excel si nécessaire
            return response()->json([
                'success' => false,
                'message' => 'Format non supporté',
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'export',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Exporte en CSV
     */
    private function exportCsv($results)
    {
        $filename = 'clients_export_' . date('Y-m-d_His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($results) {
            $file = fopen('php://output', 'w');
            
            // BOM UTF-8 pour Excel
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // En-têtes
            if ($results->isNotEmpty()) {
                fputcsv($file, array_keys($results->first()->toArray()), ';');
            }
            
            // Données
            foreach ($results as $row) {
                fputcsv($file, $row->toArray(), ';');
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Sauvegarde un filtre favori (à implémenter avec une table dédiée)
     */
    public function saveFavorite(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'filters' => 'required|array',
            'groups' => 'nullable|array',
        ]);

        // TODO: Créer une table 'saved_queries' pour stocker les favoris
        
        return response()->json([
            'success' => true,
            'message' => 'Filtre sauvegardé avec succès',
            'data' => $validated,
        ]);
    }
}
