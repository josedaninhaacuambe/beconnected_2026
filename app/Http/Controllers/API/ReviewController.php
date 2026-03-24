<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    // Listar avaliações de uma loja
    public function storeReviews(string $slug): JsonResponse
    {
        $store = Store::where('slug', $slug)->where('status', 'active')->firstOrFail();

        $reviews = $store->reviews()
            ->with('user:id,name,avatar')
            ->where('is_approved', true)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return response()->json([
            'reviews'       => $reviews,
            'rating'        => $store->rating,
            'total_reviews' => $store->reviews()->where('is_approved', true)->count(),
        ]);
    }

    // Cliente submete avaliação a uma loja
    public function submitStoreReview(Request $request, string $slug): JsonResponse
    {
        $store = Store::where('slug', $slug)->where('status', 'active')->firstOrFail();

        $validated = $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        // Um utilizador só pode avaliar uma loja uma vez
        $existing = Review::where('user_id', $request->user()->id)
            ->where('reviewable_type', Store::class)
            ->where('reviewable_id', $store->id)
            ->first();

        if ($existing) {
            $existing->update(['rating' => $validated['rating'], 'comment' => $validated['comment'] ?? null]);
        } else {
            $store->reviews()->create([
                'user_id' => $request->user()->id,
                'rating'  => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
            ]);
        }

        // Recalcular rating médio da loja
        $avg = $store->reviews()->where('is_approved', true)->avg('rating');
        $store->update(['rating' => round($avg, 1)]);

        return response()->json(['message' => 'Avaliação registada. Obrigado!', 'new_rating' => round($avg, 1)]);
    }
}
