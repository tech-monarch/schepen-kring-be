<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImageEmbedding extends Model
{
    protected $fillable = [
        'filename',
        'public_url',
        'embedding',
        'description'
    ];

    protected $casts = [
        'embedding' => 'array'
    ];
}