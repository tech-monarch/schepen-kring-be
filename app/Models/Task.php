<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    // 1. THIS IS THE FIX: We must tell Laravel these fields are "Fillable"
    protected $fillable = [
        'title', 
        'description', 
        'priority', 
        'status', 
        'assigned_to', 
        'yacht_id', 
        'due_date'
    ];

    // 2. This matches your controller's ->load(['assignedTo'])
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    // 3. This matches your controller's ->load(['yacht'])
    public function yacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class);
    }
}