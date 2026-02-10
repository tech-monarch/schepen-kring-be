<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 
        'description', 
        'priority', 
        'status', 
        'assigned_to', 
        'yacht_id', 
        'due_date',
        'created_by'
    ];

    protected $casts = [
        'due_date' => 'datetime',
    ];

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function yacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Helper methods
    public function isAssignedTo(User $user): bool
    {
        return $this->assigned_to === $user->id;
    }

    public function canBeViewedBy(User $user): bool
    {
        return $user->role === 'Admin' || $this->isAssignedTo($user);
    }

    public function canBeUpdatedBy(User $user): bool
    {
        return $user->role === 'Admin' || $this->isAssignedTo($user);
    }
}