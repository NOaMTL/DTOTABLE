<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'numero_client',
        'nom',
        'prenom',
        'email',
        'telephone',
        'date_naissance',
        'solde_compte',
        'revenus_mensuels',
        'credit_en_cours',
        'a_credit_immobilier',
        'a_assurance_vie',
        'adresse',
        'code_postal',
        'ville',
        'region',
        'pays',
        'type_compte',
        'statut',
        'date_ouverture_compte',
        'derniere_transaction',
        'nombre_operations_mois',
        'categorie_client',
        'score_fidelite',
    ];

    protected $casts = [
        'date_naissance' => 'date',
        'date_ouverture_compte' => 'date',
        'derniere_transaction' => 'datetime',
        'solde_compte' => 'decimal:2',
        'revenus_mensuels' => 'decimal:2',
        'credit_en_cours' => 'decimal:2',
        'a_credit_immobilier' => 'boolean',
        'a_assurance_vie' => 'boolean',
        'nombre_operations_mois' => 'integer',
        'score_fidelite' => 'integer',
    ];

    /**
     * Retourne la configuration des champs pour le query builder
     */
    public static function getQueryBuilderFields(): array
    {
        return [
            'informations_client' => [
                'label' => 'Informations Client',
                'fields' => [
                    'nom' => ['label' => 'Nom', 'type' => 'text', 'operators' => ['=', '!=', 'contains', 'starts_with', 'ends_with']],
                    'prenom' => ['label' => 'Prénom', 'type' => 'text', 'operators' => ['=', '!=', 'contains', 'starts_with', 'ends_with']],
                    'email' => ['label' => 'Email', 'type' => 'text', 'operators' => ['=', '!=', 'contains']],
                    'telephone' => ['label' => 'Téléphone', 'type' => 'text', 'operators' => ['=', '!=', 'contains']],
                    'age' => ['label' => 'Âge', 'type' => 'number', 'operators' => ['=', '!=', '>', '<', '>=', '<=', 'between']],
                    'date_naissance' => ['label' => 'Date de naissance', 'type' => 'date', 'operators' => ['=', '>', '<', '>=', '<=', 'between']],
                ],
            ],
            'finances' => [
                'label' => 'Données Financières',
                'fields' => [
                    'solde_compte' => ['label' => 'Solde du compte', 'type' => 'number', 'operators' => ['=', '!=', '>', '<', '>=', '<=', 'between'], 'suffix' => '€'],
                    'revenus_mensuels' => ['label' => 'Revenus mensuels', 'type' => 'number', 'operators' => ['=', '!=', '>', '<', '>=', '<=', 'between'], 'suffix' => '€'],
                    'credit_en_cours' => ['label' => 'Crédit en cours', 'type' => 'number', 'operators' => ['=', '!=', '>', '<', '>=', '<=', 'between'], 'suffix' => '€'],
                    'a_credit_immobilier' => ['label' => 'A un crédit immobilier', 'type' => 'boolean', 'operators' => ['=']],
                    'a_assurance_vie' => ['label' => 'A une assurance vie', 'type' => 'boolean', 'operators' => ['=']],
                ],
            ],
            'localisation' => [
                'label' => 'Géolocalisation',
                'fields' => [
                    'ville' => ['label' => 'Ville', 'type' => 'text', 'operators' => ['=', '!=', 'contains', 'in']],
                    'code_postal' => ['label' => 'Code postal', 'type' => 'text', 'operators' => ['=', '!=', 'starts_with', 'in']],
                    'region' => ['label' => 'Région', 'type' => 'text', 'operators' => ['=', '!=', 'in']],
                ],
            ],
            'compte' => [
                'label' => 'Informations Compte',
                'fields' => [
                    'type_compte' => ['label' => 'Type de compte', 'type' => 'select', 'operators' => ['=', '!=', 'in'], 'options' => ['particulier', 'professionnel', 'premium']],
                    'statut' => ['label' => 'Statut', 'type' => 'select', 'operators' => ['=', '!=', 'in'], 'options' => ['actif', 'inactif', 'suspendu']],
                    'categorie_client' => ['label' => 'Catégorie', 'type' => 'select', 'operators' => ['=', '!=', 'in'], 'options' => ['bronze', 'silver', 'gold', 'platinum']],
                    'date_ouverture_compte' => ['label' => 'Date d\'ouverture', 'type' => 'date', 'operators' => ['=', '>', '<', '>=', '<=', 'between']],
                    'derniere_transaction' => ['label' => 'Dernière transaction', 'type' => 'datetime', 'operators' => ['=', '>', '<', '>=', '<=', 'between']],
                    'nombre_operations_mois' => ['label' => 'Nb opérations / mois', 'type' => 'number', 'operators' => ['=', '!=', '>', '<', '>=', '<=', 'between']],
                    'score_fidelite' => ['label' => 'Score de fidélité', 'type' => 'number', 'operators' => ['=', '!=', '>', '<', '>=', '<=', 'between']],
                ],
            ],
        ];
    }
}
