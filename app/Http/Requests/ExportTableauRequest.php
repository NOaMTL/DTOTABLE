<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExportTableauRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'filterModel' => 'nullable|array',
            'date_debut' => 'nullable|date',
            'date_fin' => 'nullable|date|after_or_equal:date_debut',
            'compte' => 'nullable|string|max:50',
            'montant_min' => 'nullable|numeric|min:0',
            'montant_max' => 'nullable|numeric|min:0|gte:montant_min',
            'devise' => 'nullable|string|max:10',
            'type_operation' => 'nullable|string|max:50',
            'statut' => 'nullable|string|max:50',
            'search' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'date_fin.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début',
            'montant_max.gte' => 'Le montant maximum doit être supérieur ou égal au montant minimum',
        ];
    }
}
