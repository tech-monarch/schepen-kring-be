<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'entity_type',
        'entity_id',
        'entity_name',
        'action',
        'description',
        'severity',
        'ip_address',
        'user_agent',
        'old_data',
        'new_data',
        'metadata'
    ];

    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the user that performed the action
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for severity filter
     */
    public function scopeOfSeverity($query, $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope for entity type filter
     */
    public function scopeOfEntityType($query, $entityType)
    {
        return $query->where('entity_type', $entityType);
    }

    /**
     * Scope for date range
     */
    public function scopeDateRange($query, $startDate, $endDate = null)
    {
        $endDate = $endDate ?? now();
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope for user activity
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}