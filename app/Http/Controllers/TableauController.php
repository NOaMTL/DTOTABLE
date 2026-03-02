<?php

namespace App\Http\Controllers;

use App\DataTransferObjects\TableauFilterDTO;
use App\Http\Resources\TableauResource;
use App\Repositories\TableauRepository;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TableauController extends Controller
{
    public function __construct(
        private TableauRepository $repository
    ) {}

    public function index(): Response
    {
        return Inertia::render('Tableau/Index', [
            'filters' => [
                'devises' => $this->repository->getUniqueValues('devise'),
                'comptes' => $this->repository->getUniqueValues('compte'),
                'types' => $this->repository->getUniqueValues('type_operation'),
                'statuts' => $this->repository->getUniqueValues('statut'),
            ],
        ]);
    }

    public function getData(Request $request)
    {
        $filters = TableauFilterDTO::fromRequest($request->all());
        
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('perPage', 100);

        $data = $this->repository->getFilteredData($filters, $page, $perPage);

        return TableauResource::collection($data);
    }

    public function count(Request $request)
    {
        $filters = TableauFilterDTO::fromRequest($request->all());
        $count = $this->repository->countFilteredData($filters);

        return response()->json([
            'count' => $count,
        ]);
    }
}
