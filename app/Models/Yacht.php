<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Yacht extends Model {
    // app/Models/Yacht.php
protected $fillable = [
    // Original Fields
    'user_id',
    'name', 'price', 'status', 'year', 'length', 'make', 'model', 
    'beam', 'draft', 'engine_type', 'fuel_type', 'fuel_capacity', 
    'water_capacity', 'cabins', 'heads', 'description', 'location', 
    'main_image', 'vessel_id', 'allow_bidding',

    // --- NEW TECHNICAL FIELDS ---
    
    // General Specifications
    'vat_status',             // BTW-status
    'reference_code',         // Referentiecode
    'construction_material',  // Bouwmateriaal
    'dimensions',             // L x B x D ca
    'berths',                 // Slaapplaatsen
    'hull_shape',             // Rompvorm
    'hull_color',             // Rompkleur
    'deck_color',             // Dek en opbouw kleur
    'clearance',              // Doorvaarthoogte
    'displacement',           // Waterverplaatsing
    'steering',               // Besturing
    
    // Engine & Electricity
    'engine_brand',           // Merk motor
    'engine_model',           // Model motor
    'engine_power',           // Vermogen (pk)
    'engine_hours',           // Draaiuren
    'max_speed',              // Max snelheid
    'fuel_consumption',       // Verbruik
    'voltage',                // Voltage
    
    // Accommodation & Systems
    'interior_type',          // Type interieur
    'water_tank',             // Watertank & materiaal
    'water_system',           // Watersysteem
    
    // Equipment & Safety (Text areas)
    'navigation_electronics', // Navigatie en elektronica
    'exterior_equipment',     // Uitrusting buitenom
    'safety_equipment',       // Veiligheid
    'trailer_included'        // Trailer (Boolean)
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
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}