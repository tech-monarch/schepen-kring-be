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
    'trailer_included',       // Trailer (Boolean)

    // --- NEW XML CORE FIELDS ---
    'external_url',           // Link to yacht listing
    'print_url',              // Brochure link
    'owners_comment',         // Owner comments (nullable)
    'reg_details',            // Registration details
    'known_defects',          // Known defects
    'last_serviced',          // Last serviced date/info
    'passenger_capacity',     // Number of passengers
    'loa',                    // Length overall
    'lwl',                    // Waterline length
    'air_draft',              // Air draft
    'designer',               // Designer name
    'builder',                // Builder name
    'where',                  // Location / yard
    'hull_construction',      // Hull construction material
    'super_structure_colour', // Superstructure color
    'super_structure_construction', // Superstructure construction
    'cockpit_type',           // Cockpit type
    'control_type',           // Control type
    'flybridge',              // Boolean
    'oven',                   // Boolean
    'microwave',              // Boolean
    'fridge',                 // Boolean
    'freezer',                // Boolean
    'air_conditioning',       // Boolean
    'stern_thruster',         // Stern thruster info
    'horse_power',            // Engine horsepower description

    
    'control_type','cockpit_type','horse_power','stern_thruster','generator',
    'inverter','television','cd_player','dvd_player','anchor','spray_hood',
    'bimini','fenders','hours_counter','cruising_speed','max_draft','min_draft',
    'central_heating','heating','fuel',
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

    // app/Models/Yacht.php

// app/Models/Yacht.php

public function availabilityRules()
{
    // Link to the model created in Step 1
    return $this->hasMany(YachtAvailabilityRule::class, 'yacht_id');
}
}