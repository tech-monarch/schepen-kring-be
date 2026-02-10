<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasSystemLogs;

class Bidding extends Model
{
    use HasSystemLogs;
    
    // Define which events to log
    protected $logEvents = ['created', 'updated'];
    
    protected $fillable = [
        'yacht_id',
        'user_id',
        'amount',
        'status',
        'notes'
    ];
    
    public function yacht()
    {
        return $this->belongsTo(Yacht::class);
    }
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}