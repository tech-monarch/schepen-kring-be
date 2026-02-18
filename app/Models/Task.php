<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'priority',               // Low, Medium, High, Urgent, Critical
        'status',                  // To Do, In Progress, Done
        'assignment_status',       // pending, accepted, rejected (only for assigned tasks)
        'assigned_to',
        'user_id',                 // creator / owner (for personal tasks)
        'created_by',
        'yacht_id',
        'due_date',
        'type',                    // personal, assigned
    ];

    protected $casts = [
        'due_date' => 'date',
    ];

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function yacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class);
    }

    // Scopes
    public function scopeForUser($query, $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('assigned_to', $userId)
              ->orWhere('user_id', $userId)
              ->orWhere('created_by', $userId);
        });
    }
}