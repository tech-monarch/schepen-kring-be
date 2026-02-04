<?php

namespace App\Http\Controllers;

    //namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Yacht;
use App\Models\FAQ;
use App\Models\Blog;
use App\Models\ChatMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class GeminiController extends Controller
{
// ... imports stay the same

public function chat(Request $request)
{
    // --- 1. VOICE & API CONFIG ---
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
    $apiKey = "AIzaSyBcM6a6-Dyh-HQjybNcqB0NmS1MResz-KM";
    $model = "gemini-2.5-flash"; // Use 1.5-flash for maximum stability

    // --- 2. FETCH DATA WITH FALLBACK FLAGS ---
    $yachts = Yacht::where('status', '!=', 'Draft')->get();
    $faqs = FAQ::all();
    
    // Convert to strings OR set as "NOT_AVAILABLE"
    $yachtContext = $yachts->isNotEmpty() 
        ? $yachts->map(fn($y) => "Vessel: {$y->name} | Price: €{$y->price} | Make: {$y->make}")->join("\n") 
        : "DATABASE_EMPTY";

    $faqContext = $faqs->isNotEmpty() 
        ? $faqs->map(fn($f) => "Q: {$f->question} A: {$f->answer}")->join("\n") 
        : "DATABASE_EMPTY";

    // --- 3. SYSTEM CONTEXT WITH FALLBACK INSTRUCTION ---
    $systemContext = "
    ROLE: You are the 'Kring Schepen Yachts' AI Concierge.
    PERSONALITY: $chosenProfile
    
    KNOWLEDGE SOURCE INSTRUCTIONS:
    1. If CURRENT_FLEET or FAQS contain data, prioritize that as your 'Absolute Truth'.
    2. If CURRENT_FLEET is 'DATABASE_EMPTY', do NOT say 'I have no yachts'. Instead, use your OWN trained knowledge to discuss luxury yachting, maritime trends, and general boat buying advice.
    3. If the user asks for a specific boat we don't have, offer to help them find a similar style using your general expertise.

    CURRENT_FLEET:
    $yachtContext
    
    FAQS:
    $faqContext
    ";

    // --- 4. PREPARE & CALL API ---
    $userMessage = $request->input('message');
    $formattedContents = [
        ["role" => "user", "parts" => [["text" => $userMessage]]]
    ];

    try {
        $response = Http::withHeaders(['Content-Type' => 'application/json'])
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
                "system_instruction" => ["parts" => [["text" => $systemContext]]],
                "contents" => $formattedContents,
                "generationConfig" => ["temperature" => 0.8, "maxOutputTokens" => 400]
            ]);

        if ($response->failed()) {
            return response()->json(['reply' => "I'm drifting a bit off course. Let's try that again."], 200);
        }

        $aiResponseText = $response->json('candidates.0.content.parts.0.text');

        // --- 5. LOG HISTORY (Wrapped to prevent 500 if table is missing) ---
        try {
            ChatMessage::create([
                'session_id' => $request->input('session_id', 'anon'),
                'role' => 'assistant',
                'content' => $aiResponseText,
            ]);
        } catch (\Exception $e) {}

        return response()->json(['reply' => $aiResponseText]);

    } catch (\Exception $e) {
        return response()->json(['error' => 'Connection Failed'], 500);
    }
}
}
