<?php
// app/Http/Controllers/FaqController.php

namespace App\Http\Controllers;

use App\Models\Faq;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FaqController extends Controller
{
    // Get all FAQs for display
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

    // Store new FAQ (Admin only)
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
        
        // Train Gemini with new FAQ
        $this->trainGemini();
        
        return response()->json([
            'message' => 'FAQ added successfully',
            'faq' => $faq
        ], 201);
    }

    // Update FAQ
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
            'message' => 'FAQ updated successfully',
            'faq' => $faq
        ]);
    }

    // Delete FAQ
    public function destroy($id)
    {
        $faq = Faq::findOrFail($id);
        $faq->delete();
        
        // Retrain Gemini after deletion
        $this->trainGemini();
        
        return response()->json([
            'message' => 'FAQ deleted successfully'
        ]);
    }

    // Get FAQ by ID and increment views
    public function show($id)
    {
        $faq = Faq::findOrFail($id);
        $faq->increment('views');
        
        return response()->json($faq);
    }

    // Rate FAQ helpfulness
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

    // Ask Gemini AI based on trained knowledge
    public function askGemini(Request $request)
    {
        $request->validate([
            'question' => 'required|string|max:500'
        ]);
        
        // Get context from FAQ database
        $faqs = Faq::orderBy('views', 'desc')
                   ->limit(50) // Limit to avoid token overflow
                   ->get(['question', 'answer', 'category']);
        
        // Create context for Gemini
        $context = "You are a Maritime FAQ assistant for Schepen Kring. Here are the existing FAQs:\n\n";
        
        foreach ($faqs as $faq) {
            $context .= "Q: {$faq->question}\n";
            $context .= "A: {$faq->answer}\n";
            $context .= "Category: {$faq->category}\n\n";
        }
        
        $context .= "Now answer this new question based on the FAQs above. If the answer is not in the FAQs, say 'I don't have specific information about that, but here's what I know from the FAQs: [mention related FAQs]. For more details, please contact our support team.'\n\n";
        $context .= "Question: {$request->question}\nAnswer:";
        
        try {
            // Call Gemini API
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=' . env('GEMINI_API_KEY'), [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $context]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.7,
                    'maxOutputTokens' => 500,
                ]
            ]);
            
            if ($response->successful()) {
                $answer = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? 'Sorry, I could not generate an answer.';
                
                // Store the question in database for future training
                if (!Faq::where('question', 'like', '%' . $request->question . '%')->exists()) {
                    // You could auto-create FAQ or flag for review
                    Log::info('New FAQ question asked: ' . $request->question);
                }
                
                return response()->json([
                    'answer' => $answer,
                    'sources' => $faqs->count(),
                    'timestamp' => now()->toDateTimeString()
                ]);
            } else {
                throw new \Exception('Gemini API request failed');
            }
        } catch (\Exception $e) {
            Log::error('Gemini API error: ' . $e->getMessage());
            
            return response()->json([
                'answer' => 'I apologize, but I\'m having trouble accessing the knowledge base. Please try again later or browse our existing FAQs.',
                'error' => $e->getMessage(),
                'sources' => 0
            ], 500);
        }
    }

    // Train Gemini with all FAQs (Admin function)
    public function trainGemini()
    {
        $faqs = Faq::all(['question', 'answer', 'category']);
        $faqCount = $faqs->count();
        
        Log::info("Gemini training initiated with {$faqCount} FAQs");
        
        // In a production system, you might:
        // 1. Create embeddings for each FAQ
        // 2. Store them in a vector database
        // 3. Use similarity search for answers
        
        // For now, we'll just log and update a training timestamp
        cache()->put('gemini_last_trained', now()->toDateTimeString());
        cache()->put('gemini_faq_count', $faqCount);
        
        return response()->json([
            'message' => "Gemini training completed with {$faqCount} FAQs",
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