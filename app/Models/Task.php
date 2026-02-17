<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\HasSystemLogs;

class Task extends Model
{
    use HasFactory;
    use HasSystemLogs;
    
    // Define which events to log
    protected $logEvents = ['created', 'updated', 'deleted'];

protected $fillable = [
    'title',
    'description',
    'priority',
    'status',
    'assignment_status',   // 👈 add this line
    'assigned_to',
    'yacht_id',
    'due_date',
    'user_id',
    'type',
    'created_by'
];

protected $casts = [
    'due_date' => 'datetime',
    'assignment_status' => 'string',
];

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function yacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scope for user's tasks
    public function scopeForUser($query, $userId)
    {
        return $query->where(function($q) use ($userId) {
            $q->where('assigned_to', $userId)
              ->orWhere('user_id', $userId);
        });
    }

    // Scope for personal tasks
    public function scopePersonal($query)
    {
        return $query->where('type', 'personal');
    }

    // Scope for assigned tasks
    public function scopeAssigned($query)
    {
        return $query->where('type', 'assigned');
    }
}