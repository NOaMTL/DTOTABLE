<?php

namespace App\DataTransferObjects;

class TableauFilterDTO
{
    public function __construct(
        public readonly ?array $agGridFilterModel = null,
        public readonly ?string $dateDebut = null,
        public readonly ?string $dateFin = null,
        public readonly ?string $compte = null,
        public readonly ?float $montantMin = null,
        public readonly ?float $montantMax = null,
        public readonly ?string $devise = null,
        public readonly ?string $typeOperation = null,
        public readonly ?string $statut = null,
        public readonly ?string $searchTerm = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            agGridFilterModel: $data['filterModel'] ?? null,
            dateDebut: $data['date_debut'] ?? null,
            dateFin: $data['date_fin'] ?? null,
            compte: $data['compte'] ?? null,
            montantMin: isset($data['montant_min']) ? (float) $data['montant_min'] : null,
            montantMax: isset($data['montant_max']) ? (float) $data['montant_max'] : null,
            devise: $data['devise'] ?? null,
            typeOperation: $data['type_operation'] ?? null,
            statut: $data['statut'] ?? null,
            searchTerm: $data['search'] ?? null,
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            agGridFilterModel: $data['agGridFilterModel'] ?? null,
            dateDebut: $data['dateDebut'] ?? null,
            dateFin: $data['dateFin'] ?? null,
            compte: $data['compte'] ?? null,
            montantMin: $data['montantMin'] ?? null,
            montantMax: $data['montantMax'] ?? null,
            devise: $data['devise'] ?? null,
            typeOperation: $data['typeOperation'] ?? null,
            statut: $data['statut'] ?? null,
            searchTerm: $data['searchTerm'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'agGridFilterModel' => $this->agGridFilterModel,
            'dateDebut' => $this->dateDebut,
            'dateFin' => $this->dateFin,
            'compte' => $this->compte,
            'montantMin' => $this->montantMin,
            'montantMax' => $this->montantMax,
            'devise' => $this->devise,
            'typeOperation' => $this->typeOperation,
            'statut' => $this->statut,
            'searchTerm' => $this->searchTerm,
        ];
    }

    public function hasFilters(): bool
    {
        return !empty($this->agGridFilterModel) ||
               $this->dateDebut !== null ||
               $this->dateFin !== null ||
               $this->compte !== null ||
               $this->montantMin !== null ||
               $this->montantMax !== null ||
               $this->devise !== null ||
               $this->typeOperation !== null ||
               $this->statut !== null ||
               $this->searchTerm !== null;
    }
}
