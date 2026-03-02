<?php

namespace App\Http\Controllers;

use App\Actions\Imports\ImportDataFromTextFileAction;
use App\Http\Requests\ImportDataRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ImportController extends Controller
{
    public function __construct(
        private ImportDataFromTextFileAction $importAction
    ) {}

    public function create(): Response
    {
        return Inertia::render('Import/Create');
    }

    public function store(ImportDataRequest $request): RedirectResponse
    {
        $file = $request->file('file');
        $filePath = $file->getRealPath();

        $result = $this->importAction->execute($filePath);

        if ($result['success']) {
            return redirect()
                ->route('tableau.index')
                ->with('success', $result['message'])
                ->with('report', $result['report']);
        }

        return redirect()
            ->back()
            ->withErrors(['file' => $result['message']])
            ->with('report', $result['report'] ?? null);
    }
}
