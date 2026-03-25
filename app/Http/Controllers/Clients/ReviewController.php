<?php

namespace App\Http\Controllers\Clients;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    /**
     * Get paginated reviews for a product
     * GET /api/products/{product}/reviews
     */
    public function index(Product $product, Request $request)
    {
        $perPage = $request->per_page ?? 10;
        $sort = $request->sort ?? 'recent';
        $rating = $request->rating;

        $query = $product->reviews()->with('user');

        // Filter by rating
        if ($rating && in_array($rating, [1, 2, 3, 4, 5])) {
            $query->where('rating', $rating);
        }

        // Sort
        switch ($sort) {
            case 'helpful':
                $query->orderBy('helpful_count', 'desc');
                break;
            case 'rating_desc':
                $query->orderBy('rating', 'desc');
                break;
            case 'rating_asc':
                $query->orderBy('rating', 'asc');
                break;
            case 'recent':
            default:
                $query->latest();
                break;
        }

        $reviews = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'current_page' => $reviews->currentPage(),
                'data' => $reviews->items()->map(function ($review) {
                    return $this->formatReview($review);
                }),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
                'last_page' => $reviews->lastPage(),
                'has_more_pages' => $reviews->hasMorePages()
            ],
            'summary' => $this->getReviewsSummary($product)
        ]);
    }

    /**
     * Store a new review
     * POST /api/products/{product}/reviews
     */
    public function store(Product $product, Request $request)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'required|string|min:10|max:1000'
        ]);

        // Check if user already reviewed this product
        $existingReview = Review::where('product_id', $product->id)
            ->where('user_id', auth()->id())
            ->first();

        if ($existingReview) {
            return response()->json([
                'success' => false,
                'message' => 'Vous avez déjà laissé un avis pour ce produit.'
            ], 422);
        }

        // Check if user purchased this product
        $hasPurchased = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.user_id', auth()->id())
            ->where('order_items.product_id', $product->id)
            ->where('orders.payment_status', 'paid')
            ->exists();

        // En production, décommenter cette vérification
        // if (!$hasPurchased) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Vous devez acheter ce produit avant de laisser un avis.'
        //     ], 403);
        // }

        $review = Review::create([
            'product_id' => $product->id,
            'user_id' => auth()->id(),
            'rating' => $request->rating,
            'comment' => $request->comment,
            'helpful_count' => 0
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Votre avis a été publié avec succès.',
            'data' => $this->formatReview($review->load('user'))
        ], 201);
    }

    /**
     * Mark review as helpful
     * POST /api/reviews/{review}/helpful
     */
    public function markHelpful(Review $review)
    {
        // Check if user already marked this review as helpful
        $alreadyMarked = DB::table('review_helpful')
            ->where('review_id', $review->id)
            ->where('user_id', auth()->id())
            ->exists();

        if ($alreadyMarked) {
            return response()->json([
                'success' => false,
                'message' => 'Vous avez déjà marqué cet avis comme utile.'
            ], 422);
        }

        // Mark as helpful
        DB::table('review_helpful')->insert([
            'review_id' => $review->id,
            'user_id' => auth()->id(),
            'created_at' => now()
        ]);

        // Increment helpful count
        $review->increment('helpful_count');

        return response()->json([
            'success' => true,
            'message' => 'Merci pour votre retour !',
            'data' => [
                'helpful_count' => $review->helpful_count
            ]
        ]);
    }

    /**
     * Report a review
     * POST /api/reviews/{review}/report
     */
    public function report(Review $review, Request $request)
    {
        $request->validate([
            'reason' => 'required|string|in:spam,inappropriate,fake,other',
            'details' => 'nullable|string|max:500'
        ]);

        // Check if user already reported this review
        $alreadyReported = DB::table('review_reports')
            ->where('review_id', $review->id)
            ->where('user_id', auth()->id())
            ->exists();

        if ($alreadyReported) {
            return response()->json([
                'success' => false,
                'message' => 'Vous avez déjà signalé cet avis.'
            ], 422);
        }

        // Create report
        DB::table('review_reports')->insert([
            'review_id' => $review->id,
            'user_id' => auth()->id(),
            'reason' => $request->reason,
            'details' => $request->details,
            'created_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Merci pour votre signalement. Nous allons examiner cet avis.'
        ]);
    }

    /**
     * Format single review
     */
    private function formatReview($review)
    {
        $userName = $review->user->fullname ?? 'Utilisateur';
        $words = explode(' ', $userName);
        $initials = count($words) >= 2 
            ? strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1))
            : strtoupper(substr($userName, 0, 2));

        return [
            'id' => $review->id,
            'user_name' => $userName,
            'user_initials' => $initials,
            'rating' => $review->rating,
            'comment' => $review->comment,
            'created_at' => $review->created_at->format('Y-m-d'),
            'created_at_human' => $review->created_at->diffForHumans(),
            'helpful_count' => $review->helpful_count ?? 0,
            'is_verified_purchase' => $this->isVerifiedPurchase($review)
        ];
    }

    /**
     * Check if review is from verified purchase
     */
    private function isVerifiedPurchase($review)
    {
        return DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.user_id', $review->user_id)
            ->where('order_items.product_id', $review->product_id)
            ->where('orders.payment_status', 'paid')
            ->exists();
    }

    /**
     * Get reviews summary
     */
    private function getReviewsSummary($product)
    {
        // Calculate rating distribution
        $ratingDistribution = DB::table('reviews')
            ->select('rating', DB::raw('count(*) as count'))
            ->where('product_id', $product->id)
            ->groupBy('rating')
            ->pluck('count', 'rating')
            ->toArray();

        // Fill missing ratings with 0
        $distribution = [];
        for ($i = 5; $i >= 1; $i--) {
            $distribution[$i] = $ratingDistribution[$i] ?? 0;
        }

        $totalReviews = array_sum($distribution);
        $averageRating = $totalReviews > 0 
            ? round(array_sum(array_map(fn($rating, $count) => $rating * $count, array_keys($distribution), $distribution)) / $totalReviews, 1)
            : 0;

        return [
            'average_rating' => $averageRating,
            'total_reviews' => $totalReviews,
            'rating_distribution' => $distribution
        ];
    }
}
