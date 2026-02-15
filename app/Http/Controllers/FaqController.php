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

    $apiKey = "AIzaSyBti01fNKPd5w3YWwooz6b9FmDEczfHl5I";
    $model  = "gemini-2.5-flash";

    try {

        /** ================================
         * STEP 1 — TRY VECTOR SEARCH FIRST
         * ================================= */
        $queryEmbedding = $this->createEmbedding($request->question);

        $topFaqs = collect();

        if ($queryEmbedding) {
            $faqs = Faq::whereNotNull('embedding')->get();

            $topFaqs = $faqs->map(function ($faq) use ($queryEmbedding) {

                $faqEmbedding = json_decode($faq->embedding, true);

                if (!$faqEmbedding) return null;

                $dot = 0;
                $normA = 0;
                $normB = 0;

                foreach ($queryEmbedding as $i => $val) {
                    $dot += $val * ($faqEmbedding[$i] ?? 0);
                    $normA += $val * $val;
                    $normB += ($faqEmbedding[$i] ?? 0) * ($faqEmbedding[$i] ?? 0);
                }

                $similarity = $dot / (sqrt($normA) * sqrt($normB) + 1e-10);

                return [
                    "faq" => $faq,
                    "score" => $similarity
                ];
            })
            ->filter()
            ->sortByDesc("score")
            ->take(5);
        }

        /** =====================================
         * STEP 2 — FALLBACK TO DIRECT FAQ SEARCH
         * ====================================== */
        if ($topFaqs->isEmpty()) {

            $keywordFaqs = Faq::where('question', 'like', '%' . $request->question . '%')
                ->orWhere('answer', 'like', '%' . $request->question . '%')
                ->limit(5)
                ->get();

            if ($keywordFaqs->isNotEmpty()) {
                $topFaqs = $keywordFaqs->map(fn($faq) => ["faq" => $faq, "score" => 0.5]);
            }
        }

        /** =====================================
         * STEP 3 — IF STILL EMPTY → HARD FALLBACK
         * ====================================== */
        if ($topFaqs->isEmpty()) {
            return response()->json([
                "answer" => "I don’t have specific information about that yet. Please contact our support team for assistance.",
                "sources" => 0,
                "timestamp" => now()->toDateTimeString()
            ]);
        }

        /** =====================================
         * STEP 4 — BUILD CONTEXT FROM FOUND FAQS
         * ====================================== */
        $context = "You are a helpful maritime assistant for Schepen Kring.\n";
        $context .= "Answer ONLY using the FAQ information below.\n\n";

        foreach ($topFaqs as $item) {
            $faq = $item["faq"];
            $context .= "Q: {$faq->question}\nA: {$faq->answer}\n\n";
        }

        $context .= "User question: {$request->question}";

        /** =====================================
         * STEP 5 — ASK GEMINI
         * ====================================== */
        $response = Http::timeout(30)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
            [
                "contents" => [
                    [
                        "parts" => [
                            ["text" => $context]
                        ]
                    ]
                ],
                "generationConfig" => [
                    "temperature" => 0.3,
                    "maxOutputTokens" => 800
                ]
            ]
        );

        if ($response->failed()) {
            throw new \Exception("Gemini failed: " . $response->body());
        }

        $answer = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (!$answer) {
            throw new \Exception("Invalid Gemini response");
        }

        return response()->json([
            "answer" => trim($answer),
            "sources" => $topFaqs->count(),
            "timestamp" => now()->toDateTimeString()
        ]);

    } catch (\Throwable $e) {

        Log::error("RAG error: " . $e->getMessage());

        /** FINAL SAFETY FALLBACK **/
        $fallbackFaqs = Faq::where('question', 'like', '%' . $request->question . '%')
            ->orWhere('answer', 'like', '%' . $request->question . '%')
            ->limit(3)
            ->get();

        if ($fallbackFaqs->isNotEmpty()) {
            $text = "Here are some related FAQs:\n\n";
            foreach ($fallbackFaqs as $faq) {
                $text .= "Q: {$faq->question}\nA: {$faq->answer}\n\n";
            }

            return response()->json([
                "answer" => $text,
                "sources" => $fallbackFaqs->count(),
                "timestamp" => now()->toDateTimeString()
            ]);
        }

        return response()->json([
            "answer" => "Our AI assistant is temporarily unavailable. Please contact support.",
            "sources" => 0,
            "timestamp" => now()->toDateTimeString()
        ]);
    }
}



private function createEmbedding($text)
{
    $apiKey = "AIzaSyBti01fNKPd5w3YWwooz6b9FmDEczfHl5I";

    $response = Http::post(
        "https://generativelanguage.googleapis.com/v1beta/models/embedding-001:embedContent?key={$apiKey}",
        [
            "content" => [
                "parts" => [
                    ["text" => $text]
                ]
            ]
        ]
    );

    if ($response->failed()) {
        throw new \Exception("Embedding failed");
    }

    return $response->json()['embedding']['values'] ?? null;
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