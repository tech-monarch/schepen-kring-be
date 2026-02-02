<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YachtImage extends Model {
    protected $fillable = ['yacht_id', 'url', 'category', 'part_name', 'sort_order'];

    public function yacht(): BelongsTo {
        return $this->belongsTo(Yacht::class);
    }

    protected $appends = ['full_url'];

public function getFullUrlAttribute()
{
    return asset('storage/' . $this->url);
}

}