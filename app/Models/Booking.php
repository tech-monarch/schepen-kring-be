<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model {
    // app/Models/Booking.php
    protected $fillable = ['yacht_id', 'start_at', 'end_at', 'status'];
}