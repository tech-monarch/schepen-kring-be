<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'log_type',
        'user_id',
        'action',
        'description',
        'ip_address',
        'user_agent',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Helper method to log activities
    public static function log($logType, $action, $description, $userId = null, $metadata = []): self
    {
        $request = request();
        
        return self::create([
            'log_type' => $logType,
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'description' => $description,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => $metadata
        ]);
    }

    // Scope for filtering
    public function scopeFilter($query, $filters)
    {
        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        
        if (isset($filters['log_type'])) {
            $query->where('log_type', $filters['log_type']);
        }
        
        if (isset($filters['action'])) {
            $query->where('action', $filters['action']);
        }
        
        if (isset($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        
        if (isset($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
        
        return $query;
    }
}