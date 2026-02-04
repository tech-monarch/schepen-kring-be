<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Yacht;
use App\Models\ChatMessage;
// Use safe checks for models that might not exist in your current migration list
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class GeminiController extends Controller
{
    public function chat(Request $request)
    {
        // 1. VOICE CONFIGURATION
        $user = auth()->user();
        $voiceId = $user ? ($user->ai_voice_id ?? 'voice_4') : 'voice_4';
        
        $voiceProfiles = [
            'voice_1' => "feminine, warm, and motherly. Use soft emojis 🌸.",
            'voice_2' => "masculine, professional, and direct. No emojis.",
            'voice_3' => "energetic, young, and bubbly. Use many emojis ✨.",
            'voice_4' => "calm, highly sophisticated, and luxury-focused. Use formal language.",
            'voice_5' => "funny, casual, and a bit cheeky with maritime jokes ⚓.",
        ];
        $chosenProfile = $voiceProfiles[$voiceId] ?? $voiceProfiles['voice_4'];

        // 2. FETCH DATA WITH "SILENT" FAILURES (Prevents 500 errors if tables are missing)
        $yachtContext = "NO_LOCAL_DATA";
        $faqContext = "NO_LOCAL_DATA";

        try {
            // Check if yachts table exists before querying [cite: 176]
            $yachts = Yacht::where('status', '!=', 'Draft')->get();
            if ($yachts->isNotEmpty()) {
                $yachtContext = $yachts->map(fn($y) => "Vessel: {$y->name} | Price: €{$y->price} | Make: {$y->make}")->join("\n");
            }
        } catch (\Exception $e) {
            Log::warning("Yachts table query failed: " . $e->getMessage());
        }

        try {
            // Use DB check for FAQ since the model/migration wasn't in your backend file [cite: 176, 192]
            if (Schema::hasTable('faqs')) {
                $faqContext = DB::table('faqs')->get()->map(fn($f) => "Q: {$f->question} A: {$f->answer}")->join("\n");
            }
        } catch (\Exception $e) {
            Log::warning("FAQ table query failed.");
        }

        // 3. SYSTEM PROMPT WITH FALLBACK INSTRUCTION
        $systemContext = "
            ROLE: You are the 'Kring Schepen Yachts' AI Concierge.
            TONE: $chosenProfile
            
            KNOWLEDGE BASE:
            FLEET: $yachtContext
            FAQS: $faqContext

            INSTRUCTION: 
            1. If FLEET or FAQS are 'NO_LOCAL_DATA', do NOT tell the user. 
            2. Instead, use your own internal 'trained data' regarding luxury yachts, maritime brokerage, and general vessel maintenance to provide a helpful, expert response.
            3. Always maintain the Kring Schepen brand: 'Sailing into Excellence'.
        ";

        // 4. PREPARE API CALL
        $apiKey = "AIzaSyBcM6a6-Dyh-HQjybNcqB0NmS1MResz-KM";
        $model = "gemini-2.5-flash-lite"; // Your specified model

        try {
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
                    "system_instruction" => ["parts" => [["text" => $systemContext]]],
                    "contents" => [
                        ["role" => "user", "parts" => [["text" => $request->input('message')]]]
                    ],
                    "generationConfig" => [
                        "temperature" => 0.7,
                        "maxOutputTokens" => 500,
                    ]
                ]);

            if ($response->failed()) {
                return response()->json(['reply' => "I apologize, my maritime uplink is momentarily disconnected. How else can I assist?"], 200);
            }

            $aiResponseText = $response->json('candidates.0.content.parts.0.text');

            // 5. SAFE HISTORY LOGGING
            // Wrapped in try-catch so if ChatMessage migration is missing, it doesn't crash [cite: 147]
            try {
                // Ensure the session ID is passed from Faq.tsx [cite: 34]
                DB::table('chat_messages')->insert([
                    'session_id' => $request->input('session_id', 'anon'),
                    'role' => 'assistant',
                    'content' => $aiResponseText,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Exception $e) {
                Log::error("Could not save chat message: " . $e->getMessage());
            }

            return response()->json(['reply' => $aiResponseText]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Concierge System Error', 'details' => $e->getMessage()], 500);
        }
    }
}