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
    
    // Get context from FAQ database
    $faqs = Faq::orderBy('views', 'desc')
               ->limit(30) // Reduced from 50 to avoid token limit
               ->get(['question', 'answer', 'category']);
    
    if ($faqs->isEmpty()) {
        return response()->json([
            'answer' => 'The knowledge base is currently empty. Please check back later or contact support.',
            'sources' => 0,
            'timestamp' => now()->toDateTimeString()
        ]);
    }
    
    // Create context for Gemini
    $context = "You are a helpful maritime assistant for Schepen Kring. Answer based on the following FAQs:\n\n";
    
    foreach ($faqs as $faq) {
        $context .= "Q: {$faq->question}\n";
        $context .= "A: {$faq->answer}\n";
        $context .= "Category: {$faq->category}\n\n";
    }
    
    $context .= "Now answer this question based only on the FAQs above. If the answer isn't in the FAQs, say: 'I don't have specific information about that. For more details, please contact our support team.'\n\n";
    $context .= "Question: {$request->question}\nAnswer:";
    
    $geminiApiKey = env('GEMINI_API_KEY');
    
    if (!$geminiApiKey) {
        Log::error('Gemini API key is not set');
        return response()->json([
            'answer' => 'AI service is currently unavailable. Please try again later.',
            'sources' => $faqs->count(),
            'timestamp' => now()->toDateTimeString()
        ], 503);
    }
    
    try {
        // Call Gemini API with better error handling
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->timeout(30) // 30 second timeout
          ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key={$geminiApiKey}", [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $context]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 1000,
                'topP' => 0.8,
                'topK' => 40
            ],
            'safetySettings' => [
                [
                    'category' => 'HARM_CATEGORY_HARASSMENT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ],
                [
                    'category' => 'HARM_CATEGORY_HATE_SPEECH',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ]
            ]
        ]);
        
        if ($response->failed()) {
            Log::error('Gemini API failed: ' . $response->status() . ' - ' . $response->body());
            throw new \Exception('Gemini API request failed with status: ' . $response->status());
        }
        
        $responseData = $response->json();
        
        if (!isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
            Log::error('Gemini API returned unexpected format: ' . json_encode($responseData));
            throw new \Exception('Invalid response format from Gemini API');
        }
        
        $answer = $responseData['candidates'][0]['content']['parts'][0]['text'];
        
        // Clean up the answer
        $answer = trim($answer);
        
        Log::info('Gemini question asked: ' . $request->question);
        
        return response()->json([
            'answer' => $answer,
            'sources' => $faqs->count(),
            'timestamp' => now()->toDateTimeString()
        ]);
        
    } catch (\Exception $e) {
        Log::error('Gemini API error: ' . $e->getMessage());
        
        // Fallback: return answer from FAQ search if available
        $relevantFaqs = Faq::where('question', 'like', '%' . $request->question . '%')
                          ->orWhere('answer', 'like', '%' . $request->question . '%')
                          ->limit(3)
                          ->get();
        
        if ($relevantFaqs->isNotEmpty()) {
            $fallbackAnswer = "Based on our FAQs, here's what might help:\n\n";
            foreach ($relevantFaqs as $faq) {
                $fallbackAnswer .= "Q: {$faq->question}\n";
                $fallbackAnswer .= "A: {$faq->answer}\n\n";
            }
            $fallbackAnswer .= "For more specific questions, please contact support.";
        } else {
            $fallbackAnswer = 'I apologize, but I cannot answer that question at the moment. Please browse our FAQs or contact our support team for assistance.';
        }
        
        return response()->json([
            'answer' => $fallbackAnswer,
            'sources' => $relevantFaqs->count(),
            'timestamp' => now()->toDateTimeString(),
            'error' => $e->getMessage()
        ], 200); // Still return 200 but with fallback
    }
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