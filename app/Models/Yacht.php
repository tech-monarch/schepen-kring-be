<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Yacht extends Model {
    // app/Models/Yacht.php
protected $fillable = [

    // Core
    'user_id',
    'name','price','status','year','length','make','model',
    'beam','draft','engine_type','fuel_type','fuel_capacity',
    'water_capacity','cabins','heads','description','location',
    'main_image','vessel_id','allow_bidding',

    // General Specifications
    'vat_status','reference_code','construction_material','dimensions',
    'berths','hull_shape','hull_color','deck_color','clearance',
    'displacement','steering',

    // Engine & Electricity
    'engine_brand','engine_model','engine_power','engine_hours',
    'max_speed','fuel_consumption','voltage',

    // Accommodation & Systems
    'interior_type','water_tank','water_system',

    // Equipment & Safety
    'navigation_electronics','exterior_equipment','safety_equipment',
    'trailer_included',

    // XML Core
    'external_url','print_url','owners_comment','reg_details',
    'known_defects','last_serviced','passenger_capacity',
    'loa','lwl','air_draft','designer','builder','where',
    'hull_construction','super_structure_colour',
    'super_structure_construction',

    // Controls & Structure
    'cockpit_type','control_type','flybridge',

    // Kitchen / Comfort
    'oven','microwave','fridge','freezer','air_conditioning',

    // Engine extras
    'stern_thruster','horse_power','generator','inverter',

    // Media & Deck
    'television','cd_player','dvd_player','anchor',
    'spray_hood','bimini','fenders',

    // Navigation / Metrics
    'hours_counter','cruising_speed','max_draft','min_draft',

    // Heating & Fuel
    'central_heating','heating','fuel',
];

protected $casts = [
    'price' => 'float',
    'year' => 'integer',

    // booleans
    'trailer_included' => 'boolean',
    'flybridge' => 'boolean',
    'oven' => 'boolean',
    'microwave' => 'boolean',
    'fridge' => 'boolean',
    'freezer' => 'boolean',
    'air_conditioning' => 'boolean',

    // numeric-like
    'engine_hours' => 'integer',
    'passenger_capacity' => 'integer',
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