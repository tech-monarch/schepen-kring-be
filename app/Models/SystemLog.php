<?php
// app/Models/SystemLog.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemLog extends Model
{
    protected $table = 'system_logs';
    
    protected $fillable = [
        'event_type',
        'entity_type',
        'entity_id',
        'user_id',
        'old_data',
        'new_data',
        'changes',
        'description',
        'ip_address',
        'user_agent'
    ];
    
    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
        'changes' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    
    /**
     * Relationship with user who performed the action
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Get the related entity (Task, Yacht, Bid, etc.)
     */
    public function entity()
    {
        return $this->morphTo();
    }
    
    /**
     * Scope for specific event types
     */
    public function scopeEventType($query, $type)
    {
        return $query->where('event_type', $type);
    }
    
    /**
     * Scope for specific entity
     */
    public function scopeForEntity($query, $entityType, $entityId = null)
    {
        $query = $query->where('entity_type', $entityType);
        
        if ($entityId) {
            $query->where('entity_id', $entityId);
        }
        
        return $query;
    }
    
    /**
     * Scope for user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
    
    /**
     * Scope for date range
     */
    public function scopeDateRange($query, $from, $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }
}