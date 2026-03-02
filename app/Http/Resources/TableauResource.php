<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TableauResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'date_operation' => $this->date_operation?->format('Y-m-d'),
            'date_operation_formatted' => $this->date_operation?->format('d/m/Y'),
            'libelle' => $this->libelle,
            'montant' => (float) $this->montant,
            'montant_formatted' => number_format($this->montant, 2, ',', ' '),
            'devise' => $this->devise,
            'compte' => $this->compte,
            'agence' => $this->agence,
            'type_operation' => $this->type_operation,
            'statut' => $this->statut,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
