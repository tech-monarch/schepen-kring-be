<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Yacht extends Model {
    protected $fillable = [
        'vessel_id', 
        'name', 
        'status', 
        'price', 
        'current_bid', 
        'year', 
        'length', 
        'main_image'
    ];

    /**
     * Auto-generate a unique Vessel ID when creating a yacht.
     */
    protected static function booted()
    {
        static::creating(function ($yacht) {
            if (!$yacht->vessel_id) {
                $yacht->vessel_id = 'Y-' . strtoupper(Str::random(10));
            }
        });
    }

    public function images(): HasMany {
        // Removed sort_order for now to prevent crashes until you add the column
        return $this->hasMany(YachtImage::class);
    }

    public function bids(): HasMany {
        return $this->hasMany(Bid::class)->orderBy('amount', 'desc');
    }

    public function tasks(): HasMany {
        return $this->hasMany(Task::class);
    }
}