<?php
// app/Models/Faq.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    use HasFactory;

    protected $fillable = [
        'question',
        'answer',
        'category',
        'views',
        'helpful',
        'not_helpful'
    ];

    protected $casts = [
        'views' => 'integer',
        'helpful' => 'integer',
        'not_helpful' => 'integer'
    ];
}