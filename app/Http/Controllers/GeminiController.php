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
    public function chat(Request $request)
    {
        // --- START VOICE INTEGRATION ---
        $user = auth()->user();
        $voiceId = $user->ai_voice_id ?? 'voice_4'; // Default to 'Sophisticated' for Yachts

        $voiceProfiles = [
            'voice_1' => "feminine, warm, and motherly. Use soft emojis 🌸.",
            'voice_2' => "masculine, professional, and direct. No emojis.",
            'voice_3' => "energetic, young, and bubbly. Use many emojis ✨.",
            'voice_4' => "calm, highly sophisticated, and luxury-focused. Use formal language.",
            'voice_5' => "funny, casual, and a bit cheeky with maritime jokes ⚓.",
        ];

        $chosenProfile = $voiceProfiles[$voiceId] ?? $voiceProfiles['voice_4'];
        // --- END VOICE INTEGRATION ---

        // 1. API CONFIGURATION
        $apiKey = "AIzaSyBcM6a6-Dyh-HQjybNcqB0NmS1MResz-KM";
        $model = "gemini-2.5-flash-lite"; 

        // 2. FETCH KNOWLEDGE BASE
        // Fetch Yachts
        $yachtData = Yacht::where('status', '!=', 'Draft')->get()->map(function($y) {
            return "Vessel: {$y->name} | Price: €{$y->price} | Status: {$y->status} | Make: {$y->make} | Engine: {$y->engine_brand}";
        })->join("\n");

        // Fetch FAQs
        $faqData = FAQ::all()->map(function($f) {
            return "Q: {$f->question}\nA: {$f->answer}";
        })->join("\n\n");

        // Fetch Recent Blogs (Market Updates)
        $blogData = Blog::latest()->limit(3)->get()->map(function($b) {
            return "Article: {$b->title} Content: " . substr($b->content, 0, 200);
        })->join("\n");

        // 3. SYSTEM CONTEXT
        $systemContext = "
        ROLE: You are the 'Kring Answer Yachts' AI Concierge. You are a premium maritime consultant.
        
        TONE & PERSONALITY: You must sound $chosenProfile

        ABOUT KRING ANSWER YACHTS:
        We are a high-end maritime auction and brokerage house.
        1. Bidding: Users can place bids on 'For Bid' vessels.
        2. Direct Purchase: Users can buy 'For Sale' vessels immediately.
        3. Services: We offer Sea Trials (Test Sails), Technical Appraisals, and Maritime Legal help.

        TONE & STYLE:
        - INTRODUCTION: Greet with a touch of class. Mention 'Kring Answer' or the yacht name if applicable.
        - CONCISE: Max 3 sentences. Professional yet approachable.
        - LANGUAGE: Respond in the SAME language as the user. Prioritize Dutch or English.
        - SLOGAN: Sailing into Excellence.

        KNOWLEDGE BASE:
        ---
        CURRENT FLEET:
        $yachtData
        ---
        FAQS:
        $faqData
        ---
        MARKET UPDATES:
        $blogData
        ";

        // 4. PREPARE DATA FROM FRONTEND
        $userMessage = $request->input('message');
        $sessionId = $request->input('session_id', 'anon-' . uniqid());
        $clientHistory = json_decode($request->input('history', '[]'), true);

        // 5. HANDLE IMAGE UPLOAD
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('chat_attachments', 'public');
        }

        // 6. FORMAT CONTENTS FOR GEMINI
        $formattedContents = [];
        foreach ($clientHistory as $msg) {
            $role = ($msg['role'] === 'user') ? 'user' : 'model';
            $formattedContents[] = [
                "role" => $role, 
                "parts" => [["text" => $msg['content']]]
            ];
        }

        $currentUserParts = [];
        if ($userMessage) {
            $currentUserParts[] = ["text" => $userMessage];
        }

        if ($imagePath) {
            $currentUserParts[] = [
                "inline_data" => [
                    "mime_type" => $request->file('image')->getMimeType(),
                    "data" => base64_encode(Storage::disk('public')->get($imagePath))
                ]
            ];
            if (!$userMessage) {
                $currentUserParts[] = ["text" => "Analyze this vessel image for me based on Kring Answer's standards."];
            }
        }

        $formattedContents[] = [
            "role" => "user", 
            "parts" => $currentUserParts
        ];

        // 7. CALL API
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $apiKey;

        try {
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->post($url, [
                    "system_instruction" => [
                        "parts" => [["text" => $systemContext]]
                    ],
                    "contents" => $formattedContents,
                    "generationConfig" => [
                        "temperature" => 0.7,
                        "maxOutputTokens" => 400,
                    ]
                ]);

            $aiResponseText = $response->json('candidates.0.content.parts.0.text') ?? "I apologize, but my maritime uplink is currently unstable.";

            // 8. SAVE HISTORY
            ChatMessage::create([
                'session_id' => $sessionId,
                'role'       => 'user',
                'content'    => $userMessage ?? 'Sent an image',
                'image_path' => $imagePath,
            ]);

            ChatMessage::create([
                'session_id' => $sessionId,
                'role'       => 'assistant',
                'content'    => $aiResponseText,
            ]);

            return response()->json([
                'reply' => $aiResponseText,
                'voice_meta' => [
                    'voice_id' => $voiceId,
                    'profile' => $chosenProfile
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Concierge Offline: ' . $e->getMessage()], 500);
        }
    }
}
