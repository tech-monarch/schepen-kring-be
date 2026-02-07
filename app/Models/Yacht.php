<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Yacht extends Model {
    
    protected $fillable = [
        // Core
        'user_id', 'vessel_id', 'boat_name', 'price', 'status', 'allow_bidding', 'main_image',
        
        // URLs and references
        'external_url', 'print_url', 'owners_comment', 'reg_details', 'known_defects', 'last_serviced',
        
        // Dimensions
        'beam', 'draft', 'loa', 'lwl', 'air_draft', 'passenger_capacity',
        
        // Construction
        'designer', 'builder', 'where', 'year', 'hull_colour', 'hull_construction',
        'hull_number', 'hull_type', 'super_structure_colour', 'super_structure_construction',
        'deck_colour', 'deck_construction',
        
        // Configuration
        'cockpit_type', 'control_type', 'flybridge', 'ballast', 'displacement',
        
        // Accommodation
        'cabins', 'berths', 'toilet', 'shower', 'bath',
        
        // Kitchen equipment
        'oven', 'microwave', 'fridge', 'freezer', 'heating', 'air_conditioning',
        
        // Engine and propulsion
        'stern_thruster', 'bow_thruster', 'fuel', 'hours', 'cruising_speed', 'max_speed',
        'horse_power', 'engine_manufacturer', 'engine_quantity', 'tankage', 'gallons_per_hour',
        'litres_per_hour', 'engine_location', 'gearbox', 'cylinders', 'propeller_type',
        'starting_type', 'drive_type', 'cooling_system',
        
        // Navigation equipment
        'navigation_lights', 'compass', 'depth_instrument', 'wind_instrument',
        'autopilot', 'gps', 'vhf', 'plotter', 'speed_instrument', 'radar',
        
        // Safety equipment
        'life_raft', 'epirb', 'bilge_pump', 'fire_extinguisher', 'mob_system',
        
        // Sailing equipment
        'genoa', 'spinnaker', 'tri_sail', 'storm_jib', 'main_sail', 'winches',
        
        // Electrical
        'battery', 'battery_charger', 'generator', 'inverter',
        
        // Entertainment
        'television', 'cd_player', 'dvd_player',
        
        // Deck equipment
        'anchor', 'spray_hood', 'bimini', 'fenders',
    ];

    protected $casts = [
        'price' => 'float',
        'year' => 'integer',
        'passenger_capacity' => 'integer',
        
        // Booleans
        'allow_bidding' => 'boolean',
        'flybridge' => 'boolean',
        'oven' => 'boolean',
        'microwave' => 'boolean',
        'fridge' => 'boolean',
        'freezer' => 'boolean',
        'air_conditioning' => 'boolean',
        'navigation_lights' => 'boolean',
        'compass' => 'boolean',
        'depth_instrument' => 'boolean',
        'wind_instrument' => 'boolean',
        'autopilot' => 'boolean',
        'gps' => 'boolean',
        'vhf' => 'boolean',
        'plotter' => 'boolean',
        'speed_instrument' => 'boolean',
        'radar' => 'boolean',
        'life_raft' => 'boolean',
        'epirb' => 'boolean',
        'bilge_pump' => 'boolean',
        'fire_extinguisher' => 'boolean',
        'mob_system' => 'boolean',
        'spinnaker' => 'boolean',
        'battery' => 'boolean',
        'battery_charger' => 'boolean',
        'generator' => 'boolean',
        'inverter' => 'boolean',
        'television' => 'boolean',
        'cd_player' => 'boolean',
        'dvd_player' => 'boolean',
        'anchor' => 'boolean',
        'spray_hood' => 'boolean',
        'bimini' => 'boolean',
    ];

    /**
     * Auto-generate a unique Vessel ID when creating a yacht.
     */
    protected static function booted()
    {
        static::creating(function ($yacht) {
            if (!$yacht->vessel_id) {
                $yacht->vessel_id = 'SK-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
            }
        });
    }

    public function images(): HasMany {
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

    public function availabilityRules()
    {
        return $this->hasMany(YachtAvailabilityRule::class, 'yacht_id');
    }
}