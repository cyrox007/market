<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Models\News\Article;
use App\Models\News\NewsCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ArticleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $query = Article::query()->active()->with(['category', 'author']);

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('category_slug')) {
            $category = NewsCategory::where('slug', $request->category_slug)->first();
            if ($category) {
                $query->where('category_id', $category->id);
            }
        }

        if ($request->has('published')) {
            $query->published();
        }

        $sortBy = $request->get('sort_by', 'published_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $allowedSorts = ['published_at', 'created_at', 'priority', 'title'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->ordered();
        }

        $perPage = min($request->get('per_page', 20), 100);
        $articles = $query->paginate($perPage);

        return ArticleResource::collection($articles);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $article = Article::cached("show_{$slug}", function () use ($slug) {
            return Article::where('slug', $slug)
                ->orWhere('id', $slug)
                ->with(['category', 'author'])
                ->active()
                ->firstOrFail();
        });

        return response()->json([
            'article' => new ArticleResource($article),
        ]);
    }
}

