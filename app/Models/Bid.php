<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bid extends Model
{
    protected $fillable = ['yacht_id', 'user_id', 'amount', 'status'];

    public function yacht(): BelongsTo {
        return $this->belongsTo(Yacht::class);
    }

    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }
}