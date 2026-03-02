<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TableauData extends Model
{
    use HasFactory;

    protected $table = 'tableau_data';

    protected $fillable = [
        'reference',
        'date_operation',
        'libelle',
        'montant',
        'devise',
        'compte',
        'agence',
        'type_operation',
        'statut',
    ];

    protected $casts = [
        'date_operation' => 'date',
        'montant' => 'decimal:2',
    ];

    // Scopes pour faciliter les requêtes
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date_operation', [$startDate, $endDate]);
    }

    public function scopeByCompte($query, $compte)
    {
        return $query->where('compte', $compte);
    }

    public function scopeByMontantRange($query, $min, $max)
    {
        return $query->whereBetween('montant', [$min, $max]);
    }
}
