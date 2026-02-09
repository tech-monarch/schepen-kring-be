<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BlogController extends Controller
{
    /**
     * Display a listing of blogs.
     */
    public function index(Request $request)
    {
        $query = Blog::with('user');
        
        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Filter by search term
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%")
                  ->orWhere('excerpt', 'like', "%{$search}%")
                  ->orWhere('author', 'like', "%{$search}%");
            });
        }
        
        // Sort options
        $sort = $request->get('sort', 'created_at');
        $order = $request->get('order', 'desc');
        $query->orderBy($sort, $order);
        
        // Pagination
        $perPage = $request->get('per_page', 10);
        $blogs = $query->paginate($perPage);
        
        return response()->json([
            'data' => $blogs->items(),
            'meta' => [
                'current_page' => $blogs->currentPage(),
                'last_page' => $blogs->lastPage(),
                'per_page' => $blogs->perPage(),
                'total' => $blogs->total(),
            ]
        ]);
    }

    /**
     * Store a newly created blog.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'author' => 'nullable|string|max:255',
            'excerpt' => 'nullable|string|max:500',
            'status' => 'required|in:draft,published',
            'featured_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $blogData = $request->only(['title', 'content', 'author', 'excerpt', 'status']);
        $blogData['slug'] = Str::slug($request->title);
        $blogData['user_id'] = auth()->id();
        
        if ($request->hasFile('featured_image')) {
            $path = $request->file('featured_image')->store('blog_images', 'public');
            $blogData['featured_image'] = $path;
        }

        $blog = Blog::create($blogData);

        return response()->json([
            'message' => 'Blog created successfully',
            'data' => $blog->load('user')
        ], 201);
    }

    /**
     * Display the specified blog.
     */
    public function show($id)
    {
        $blog = Blog::with('user')->find($id);
        
        if (!$blog) {
            return response()->json([
                'message' => 'Blog not found'
            ], 404);
        }
        
        // Increment view count
        $blog->incrementViews();
        
        return response()->json([
            'data' => $blog
        ]);
    }

    /**
     * Update the specified blog.
     */
    public function update(Request $request, $id)
    {
        $blog = Blog::find($id);
        
        if (!$blog) {
            return response()->json([
                'message' => 'Blog not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'author' => 'nullable|string|max:255',
            'excerpt' => 'nullable|string|max:500',
            'status' => 'sometimes|in:draft,published',
            'featured_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $blogData = $request->only(['title', 'content', 'author', 'excerpt', 'status']);
        
        if ($request->has('title')) {
            $blogData['slug'] = Str::slug($request->title);
        }
        
        if ($request->hasFile('featured_image')) {
            // Delete old image if exists
            if ($blog->featured_image) {
                Storage::disk('public')->delete($blog->featured_image);
            }
            
            $path = $request->file('featured_image')->store('blog_images', 'public');
            $blogData['featured_image'] = $path;
        }

        $blog->update($blogData);

        return response()->json([
            'message' => 'Blog updated successfully',
            'data' => $blog->load('user')
        ]);
    }

    /**
     * Remove the specified blog.
     */
    public function destroy($id)
    {
        $blog = Blog::find($id);
        
        if (!$blog) {
            return response()->json([
                'message' => 'Blog not found'
            ], 404);
        }
        
        // Delete featured image if exists
        if ($blog->featured_image) {
            Storage::disk('public')->delete($blog->featured_image);
        }
        
        $blog->delete();
        
        return response()->json([
            'message' => 'Blog deleted successfully'
        ]);
    }

    /**
     * Get blog by slug.
     */
    public function showBySlug($slug)
    {
        $blog = Blog::with('user')->where('slug', $slug)->first();
        
        if (!$blog) {
            return response()->json([
                'message' => 'Blog not found'
            ], 404);
        }
        
        // Increment view count
        $blog->incrementViews();
        
        return response()->json([
            'data' => $blog
        ]);
    }

    /**
     * Get featured/popular blogs.
     */
    public function featured()
    {
        $blogs = Blog::published()
            ->orderBy('views', 'desc')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();
        
        return response()->json([
            'data' => $blogs
        ]);
    }
}