<?php

namespace App\Http\Controllers;

use App\Models\Yacht;
use App\Models\YachtImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use App\Models\YachtAvailabilityRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;


class YachtController extends Controller {

public function index(): JsonResponse {
    // Use boat_name instead of name for ordering
    return response()->json(Yacht::with(['images', 'availabilityRules'])
        ->orderBy('boat_name', 'asc')
        ->get());
}

    public function partnerIndex(): JsonResponse {
        $user = Auth::user();
        
        return response()->json(
            Yacht::with(['images', 'availabilityRules'])
                ->where('user_id', $user->id)
                ->orderBy('boat_name', 'asc')
                ->get()
        );
    }


    public function store(Request $request): JsonResponse {
        return $this->saveYacht($request);
    }

    public function update(Request $request, $id): JsonResponse {
        return $this->saveYacht($request, $id);
    }

protected function saveYacht(Request $request, $id = null): JsonResponse
{
    try {
        DB::beginTransaction();

        $isUpdate = $id !== null;
        $yacht = $isUpdate ? Yacht::findOrFail($id) : new Yacht();

        // Basic validation
        $rules = [
            'boat_name' => $isUpdate ? 'sometimes|required|string' : 'required|string',
            'price' => $isUpdate ? 'sometimes|nullable|numeric' : 'nullable|numeric',
            'year' => 'nullable|integer',
            'main_image' => $isUpdate ? 'nullable|image|max:5120' : 'sometimes|image|max:5120',

            // ✅ ADDED
            'min_bid_amount' => $isUpdate
                ? 'sometimes|nullable|numeric|min:0'
                : 'nullable|numeric|min:0',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
    // Handle display_specs field
    if ($request->has('display_specs')) {
        $displaySpecs = [];
        
        // Collect all checked specs from request
        foreach ($request->all() as $key => $value) {
            if (str_starts_with($key, 'display_specs[') && $value === 'on') {
                // Extract field name from display_specs[field_name]
                preg_match('/display_specs\[(.*?)\]/', $key, $matches);
                if (isset($matches[1])) {
                    $displaySpecs[] = $matches[1];
                }
            }
        }
        
        $yacht->display_specs = $displaySpecs;
    } elseif (!$isUpdate) {
        // For new yachts, default to empty (show all)
        $yacht->display_specs = [];
    }

        // Define all fields from the new structure
        $allFields = [
            // Core
            'boat_name', 'price', 'status', 'year', 'main_image',

            // ✅ ADDED
            'min_bid_amount',

            // URLs and references
            'external_url', 'print_url', 'owners_comment', 'reg_details',
            'known_defects', 'last_serviced',

            // Dimensions
            'beam', 'draft', 'loa', 'lwl', 'air_draft', 'passenger_capacity',

            // Construction
            'designer', 'builder', 'where', 'hull_colour', 'hull_construction',
            'hull_number', 'hull_type', 'super_structure_colour', 'super_structure_construction',
            'deck_colour', 'deck_construction',

            // Configuration
            'cockpit_type', 'control_type', 'ballast', 'displacement',

            // Accommodation
            'cabins', 'berths', 'toilet', 'shower', 'bath',

            // Kitchen equipment
            'heating',

            // Engine and propulsion
            'stern_thruster', 'bow_thruster', 'fuel', 'hours', 'cruising_speed', 'max_speed',
            'horse_power', 'engine_manufacturer', 'tankage', 'gallons_per_hour',
            'starting_type', 'drive_type',
        ];

        // Text fields
        $textFields = [
            'owners_comment', 'reg_details', 'known_defects', 'ballast',
            'engine_quantity', 'tankage', 'litres_per_hour', 'gearbox',
            'cylinders', 'propeller_type', 'engine_location', 'cooling_system',
            'genoa', 'tri_sail', 'storm_jib', 'main_sail', 'winches',
            'cabins', 'berths', 'toilet', 'shower', 'bath'
        ];

        // Boolean fields
        $booleanFields = [
            'allow_bidding', 'flybridge', 'oven', 'microwave', 'fridge', 'freezer',
            'air_conditioning', 'navigation_lights', 'compass', 'depth_instrument',
            'wind_instrument', 'autopilot', 'gps', 'vhf', 'plotter', 'speed_instrument',
            'radar', 'life_raft', 'epirb', 'bilge_pump', 'fire_extinguisher',
            'mob_system', 'spinnaker', 'battery', 'battery_charger', 'generator',
            'inverter', 'television', 'cd_player', 'dvd_player', 'anchor',
            'spray_hood', 'bimini'
        ];

        // Handle regular fields
        foreach ($allFields as $field) {
            if ($request->has($field)) {
                $value = $request->input($field);
                $yacht->{$field} = ($value === '' || $value === 'undefined' || $value === null) ? null : $value;
            }
        }

        // Handle text fields
        foreach ($textFields as $field) {
            if ($request->has($field)) {
                $value = $request->input($field);
                $yacht->{$field} = ($value === '' || $value === 'undefined' || $value === null) ? null : $value;
            }
        }

        // Handle boolean fields
        foreach ($booleanFields as $field) {
            if ($request->has($field)) {
                $value = $request->input($field);
                $yacht->{$field} = ($value === '1' || $value === 'true' || $value === 1 || $value === true);
            } elseif (!$isUpdate) {
                $yacht->{$field} = false;
            }
        }

        // Handle main image
        if ($request->hasFile('main_image')) {
            if ($isUpdate && $yacht->main_image) {
                Storage::disk('public')->delete($yacht->main_image);
            }
            $yacht->main_image = $request->file('main_image')->store('yachts/main', 'public');
        }

        // Set user_id for new yachts
        if (!$isUpdate) {
            $yacht->user_id = auth()->id();

            if (!$yacht->vessel_id) {
                $yacht->vessel_id = 'SK-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
            }
        }

        $yacht->save();

        // Handle availability rules
        if ($request->filled('availability_rules')) {
            try {
                $rules = json_decode($request->input('availability_rules'), true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($rules)) {
                    $yacht->availabilityRules()->delete();

                    foreach ($rules as $rule) {
                        if (!empty($rule['day_of_week']) && !empty($rule['start_time']) && !empty($rule['end_time'])) {
                            $yacht->availabilityRules()->create([
                                'day_of_week' => (int) $rule['day_of_week'],
                                'start_time' => $rule['start_time'],
                                'end_time' => $rule['end_time'],
                            ]);
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Failed to save availability rules: ' . $e->getMessage());
            }
        }

        DB::commit();

        $yacht->load(['images', 'availabilityRules']);

        return response()->json($yacht, $isUpdate ? 200 : 201);

    } catch (\Throwable $e) {
        DB::rollBack();

        Log::error("Yacht Save Error: " . $e->getMessage(), [
            'line' => $e->getLine(),
            'file' => $e->getFile(),
            'trace' => $e->getTraceAsString(),
            'request_data' => $request->except(['main_image', 'images']),
            'yacht_id' => $id ?? 'new'
        ]);

        return response()->json([
            'message' => 'Failed to save yacht',
            'error' => $e->getMessage(),
            'line' => $e->getLine()
        ], 500);
    }
}

    public function uploadGallery(Request $request, $id): JsonResponse {
        $request->validate([
            'images.*' => 'required|image|max:5120',
            'category' => 'required|string|in:Exterior,Interior,Engine Room,Bridge,General',
        ]);

        $yacht = Yacht::findOrFail($id);
        
        $files = $request->file('images') ?? $request->file('images[]');

        if (empty($files)) {
            return response()->json(['message' => 'No images detected'], 422);
        }

        $files = is_array($files) ? $files : [$files];
        $uploaded = [];

        foreach ($files as $image) {
            if ($image instanceof \Illuminate\Http\UploadedFile) {
                $folderName = $yacht->vessel_id ?? $yacht->id;
                $path = $image->store("yachts/gallery/{$folderName}", 'public');
                
                $uploaded[] = $yacht->images()->create([
                    'url'        => $path,
                    'category'   => $request->input('category', 'General'),
                    'part_name'  => $request->input('category', 'General'),
                ]);
            }
        }

        return response()->json(['status' => 'success', 'data' => $uploaded], 200);
    }

    public function deleteGalleryImage($id): JsonResponse {
        $image = YachtImage::findOrFail($id);
        Storage::disk('public')->delete($image->url);
        $image->delete();
        return response()->json(['message' => 'Image removed']);
    }

    public function show($id): JsonResponse {
        $yacht = Yacht::with(['images', 'availabilityRules'])->find($id);
        return $yacht ? response()->json($yacht) : response()->json(['message' => 'Vessel not found'], 404);
    }

    public function destroy($id): JsonResponse {
        $yacht = Yacht::findOrFail($id);
        if ($yacht->main_image) {
            Storage::disk('public')->delete($yacht->main_image);
        }
        
        // Delete gallery images from storage too
        foreach($yacht->images as $img) {
            Storage::disk('public')->delete($img->url);
        }
        
        $yacht->delete();
        return response()->json(['message' => 'Vessel removed from fleet.']);
    }

    public function classifyImages(Request $request): JsonResponse
    {
        $request->validate([
            'images.*' => 'required|image|max:5120',
        ]);

        $apiKey = "AIzaSyDwuu7UILyKXZNyB2KclKyGpEYiBNUNhc0";
        $model = "gemini-2.0-flash"; 
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $results = [];

        foreach ($request->file('images') as $image) {
            try {
                $imageData = base64_encode(file_get_contents($image->getRealPath()));
                
                $response = Http::timeout(15)->post($endpoint, [
                    'contents' => [['parts' => [
                        ['text' => "Return only one word: Exterior, Interior, Engine Room, Bridge, or General."],
                        ['inline_data' => ['mime_type' => $image->getMimeType(), 'data' => $imageData]]
                    ]]]
                ]);

                if ($response->successful()) {
                    $text = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? 'General';
                    $category = trim(preg_replace('/[^A-Za-z\s]/', '', $text));
                    // Validate category
                    $validCategories = ['Exterior', 'Interior', 'Engine Room', 'Bridge', 'General'];
                    if (!in_array($category, $validCategories)) {
                        $category = 'General';
                    }
                } else {
                    $category = 'General';
                }

                $results[] = [
                    'category' => $category,
                    'preview' => 'data:' . $image->getMimeType() . ';base64,' . $imageData,
                    'originalName' => $image->getClientOriginalName()
                ];

            } catch (\Exception $e) {
                $results[] = [
                    'category' => 'General',
                    'preview' => '', 
                    'originalName' => $image->getClientOriginalName(),
                    'error' => true
                ];
            }
        }

        return response()->json($results);
    }
}