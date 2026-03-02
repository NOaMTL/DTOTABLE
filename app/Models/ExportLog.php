<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExportLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'export_type',
        'filters',
        'results_count',
        'file_path',
        'file_size',
        'execution_time',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'filters' => 'array',
        'execution_time' => 'decimal:3',
        'results_count' => 'integer',
        'file_size' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
