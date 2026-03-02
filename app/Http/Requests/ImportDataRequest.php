<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:txt,csv,tsv',
                'max:10240', // 10MB max
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Veuillez sélectionner un fichier à importer',
            'file.mimes' => 'Le fichier doit être au format TXT, CSV ou TSV',
            'file.max' => 'Le fichier ne doit pas dépasser 10 Mo',
        ];
    }
}
