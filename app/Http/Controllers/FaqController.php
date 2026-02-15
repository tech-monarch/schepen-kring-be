<?php
// app/Http/Controllers/FaqController.php

namespace App\Http\Controllers;

use App\Models\Faq;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FaqController extends Controller
{
    // Get all Faqs for display
    public function index(Request $request)
    {
        $query = Faq::query();
        
        if ($request->has('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }
        
        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('question', 'like', '%' . $request->search . '%')
                  ->orWhere('answer', 'like', '%' . $request->search . '%');
            });
        }
        
        $faqs = $query->orderBy('views', 'desc')
                     ->orderBy('created_at', 'desc')
                     ->paginate(20);
        
        $categories = Faq::select('category')
                        ->distinct()
                        ->pluck('category')
                        ->toArray();
        
        return response()->json([
            'faqs' => $faqs,
            'categories' => $categories,
            'total_count' => Faq::count()
        ]);
    }

    // Store new Faq (Admin only)
    public function store(Request $request)
    {
        $request->validate([
            'question' => 'required|string|max:500',
            'answer' => 'required|string',
            'category' => 'required|string|max:100'
        ]);
        
        $faq = Faq::create([
            'question' => $request->question,
            'answer' => $request->answer,
            'category' => $request->category
        ]);
        
        // Train Gemini with new Faq
        $this->trainGemini();
        
        return response()->json([
            'message' => 'Faq added successfully',
            'faq' => $faq
        ], 201);
    }

    // Update Faq
    public function update(Request $request, $id)
    {
        $faq = Faq::findOrFail($id);
        
        $request->validate([
            'question' => 'sometimes|string|max:500',
            'answer' => 'sometimes|string',
            'category' => 'sometimes|string|max:100'
        ]);
        
        $faq->update($request->all());
        
        // Retrain Gemini after update
        $this->trainGemini();
        
        return response()->json([
            'message' => 'Faq updated successfully',
            'faq' => $faq
        ]);
    }

    // Delete Faq
    public function destroy($id)
    {
        $faq = Faq::findOrFail($id);
        $faq->delete();
        
        // Retrain Gemini after deletion
        $this->trainGemini();
        
        return response()->json([
            'message' => 'Faq deleted successfully'
        ]);
    }

    // Get Faq by ID and increment views
    public function show($id)
    {
        $faq = Faq::findOrFail($id);
        $faq->increment('views');
        
        return response()->json($faq);
    }

    // Rate Faq helpfulness
    public function rateHelpful($id)
    {
        $faq = Faq::findOrFail($id);
        $faq->increment('helpful');
        
        return response()->json([
            'message' => 'Thank you for your feedback!',
            'helpful_count' => $faq->helpful
        ]);
    }
    
    public function rateNotHelpful($id)
    {
        $faq = Faq::findOrFail($id);
        $faq->increment('not_helpful');
        
        return response()->json([
            'message' => 'Thank you for your feedback!',
            'not_helpful_count' => $faq->not_helpful
        ]);
    }

public function askGemini(Request $request)
{
    $request->validate([
        'question' => 'required|string|max:500'
    ]);

    $apiKey = env('GOOGLE_API_KEY');
    $model  = "gemini-2.5-flash"; // or "gemini-2.5-flash-lite"

    try {
        // 1. FETCH ALL FAQS (no status column to filter)
        $faqs = Faq::all();
        $faqTexts = $faqs->map(function($faq) {
            return "Vraag: " . $faq->question . "\nAntwoord: " . $faq->answer;
        })->join("\n\n");

        // 2. BUILD SYSTEM CONTEXT for Schepen Kring
        $systemContext = "
ROLE: You are the 'Schepen Kring' AI assistant, specialized in maritime and yacht information. You are helpful, friendly, and extremely concise.

TONE & PERSONALITY: Professional, knowledgeable, and approachable. Use a calm and clear tone. Use emojis occasionally where appropriate ⚓.

WHAT IS Schepen Kring?
Schepen Kring is a platform for yacht and vessel enthusiasts, brokers, and buyers. We provide:
- Listings of sailing yachts, motor yachts, and luxury vessels.
- Detailed specifications, images, and broker contact information.
- Resources for buying, selling, and chartering vessels.

TONE & STYLE:
- CONCISE and FRIENDLY: Max 2 sentences. No paragraphs.
- HUMAN: Use friendly language. Don't sound like a manual. Use contractions (we're, it's) and occasional emojis.
- If the user asks about a specific type of yacht or feature, provide accurate information based on the FAQs.
- If you don't know the answer, politely suggest they contact support or a broker.

MANDATORY RULES:
1. LANGUAGE ADAPTATION: Detect the language the user is speaking. ALWAYS respond in the SAME language as the user. If they speak Dutch, you speak Dutch. If they speak English, you speak English. Prioritize Dutch over English if uncertain.
2. Use the following FAQs as your absolute source of truth for company facts and common questions.

KNOWLEDGE BASE:
---
FAQS:
$faqTexts
---
        ";

        // 3. PREPARE THE USER MESSAGE
        $contents = [
            [
                "role" => "user",
                "parts" => [
                    ["text" => $request->question]
                ]
            ]
        ];

        // 4. CALL GEMINI API WITH SYSTEM INSTRUCTION
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $apiKey;

        $response = Http::withHeaders(['Content-Type' => 'application/json'])
            ->timeout(30)
            ->post($url, [
                "system_instruction" => [
                    "parts" => [["text" => $systemContext]]
                ],
                "contents" => $contents,
                "generationConfig" => [
                    "temperature" => 0.7,
                    "maxOutputTokens" => 250,
                ]
            ]);

        if ($response->failed()) {
            Log::error("Gemini API failed: " . $response->body());
            throw new \Exception("Gemini request failed");
        }

        $answer = $response->json('candidates.0.content.parts.0.text');
        $answer = $answer ? trim($answer) : "I don't have specific information about that yet. Please contact support.";

        return response()->json([
            'answer' => $answer,
            'sources' => $faqs->count(),
            'timestamp' => now()
        ]);

    } catch (\Throwable $e) {
        Log::error("askGemini error: " . $e->getMessage());
        return response()->json([
            'answer' => "Our AI assistant is temporarily unavailable. Please contact support.",
            'sources' => 0,
            'timestamp' => now()
        ]);
    }
}




// Embedding function rewritten to use generateContent properly
private function createEmbedding($text)
{
    $apiKey = env('GOOGLE_API_KEY');

    $body = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $text]
                ]
            ]
        ],
        "generationConfig" => [
            "temperature" => 0,
            "maxOutputTokens" => 1
        ]
    ];

    $response = Http::timeout(15)->post(
        "https://generativelanguage.googleapis.com/v1beta/models/embedding-001:embedContent?key={$apiKey}",
        $body
    );

    if ($response->failed()) {
        Log::error("Embedding API failed: " . $response->body());
        return null;
    }

    return $response->json('embedding.values') ?? null;
}

public function storeDummy()
{
    $faq = Faq::create([
        'question' => 'What yachts are available?',
        'answer' => 'We have sailing yachts, motor yachts, and luxury yachts.',
        'category' => 'Yachts'
    ]);

    $this->trainGemini();

    return response()->json([
        'message' => 'Dummy FAQ added',
        'faq' => $faq
    ]);
}


    // Train Gemini with all Faqs (Admin function)
    public function trainGemini()
    {
        $faqs = Faq::all(['question', 'answer', 'category']);
        $faqCount = $faqs->count();
        
        Log::info("Gemini training initiated with {$faqCount} Faqs");
        
        // In a production system, you might:
        // 1. Create embeddings for each Faq
        // 2. Store them in a vector database
        // 3. Use similarity search for answers
        
        // For now, we'll just log and update a training timestamp
        cache()->put('gemini_last_trained', now()->toDateTimeString());
        cache()->put('gemini_faq_count', $faqCount);
        
        return response()->json([
            'message' => "Gemini training completed with {$faqCount} Faqs",
            'last_trained' => now()->toDateTimeString(),
            'faq_count' => $faqCount
        ]);
    }

    // Get training status
    public function getTrainingStatus()
    {
        return response()->json([
            'last_trained' => cache()->get('gemini_last_trained'),
            'faq_count' => cache()->get('gemini_faq_count', 0),
            'total_faqs' => Faq::count()
        ]);
    }

    // Get statistics
    public function stats()
    {
        $totalFaqs = Faq::count();
        $totalViews = Faq::sum('views');
        $totalHelpful = Faq::sum('helpful');
        $totalNotHelpful = Faq::sum('not_helpful');
        
        $categories = Faq::select('category', \DB::raw('count(*) as count'))
                        ->groupBy('category')
                        ->orderBy('count', 'desc')
                        ->get();
        
        $popularFaqs = Faq::orderBy('views', 'desc')
                          ->limit(10)
                          ->get(['id', 'question', 'views']);
        
        return response()->json([
            'total_faqs' => $totalFaqs,
            'total_views' => $totalViews,
            'total_helpful' => $totalHelpful,
            'total_not_helpful' => $totalNotHelpful,
            'categories' => $categories,
            'popular_faqs' => $popularFaqs
        ]);
    }
}