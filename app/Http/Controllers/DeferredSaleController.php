<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponseTrait;
use App\Models\DeferredSale;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DeferredSaleController extends Controller
{
    use ApiResponseTrait;

    /**
     * Submit a new deferred sale request.
     * POST /api/v1/deferred-sales
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'requested_price' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
        ]);

        $product = Product::find($validated['product_id']);

        if (! $product) {
            return $this->notFoundResponse('Product not found');
        }

        $originalPrice = $product->price * $validated['quantity'];
        $requestedPrice = $validated['requested_price'] * $validated['quantity'];

        // Calculate profit
        $profitData = DeferredSale::calculateProfit($originalPrice, $requestedPrice);

        // Validate that requested price is less than original (user makes profit)
        if ($requestedPrice >= $originalPrice) {
            return $this->errorResponse(
                'Requested price must be less than original price for profit',
                'VALIDATION_ERROR',
                422
            );
        }

        $deferredSale = DeferredSale::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => $validated['quantity'],
            'original_price' => $originalPrice,
            'requested_price' => $requestedPrice,
            'profit_amount' => $profitData['profit_amount'],
            'profit_percentage' => $profitData['profit_percentage'],
            'notes' => $validated['notes'],
            'status' => 'pending',
        ]);

        return $this->createdResponse([
            'id' => $deferredSale->id,
            'product' => [
                'id' => $product->id,
                'title' => $product->title,
            ],
            'quantity' => $deferredSale->quantity,
            'original_price' => round($deferredSale->original_price, 2),
            'requested_price' => round($deferredSale->requested_price, 2),
            'profit_amount' => round($deferredSale->profit_amount, 2),
            'profit_percentage' => round($deferredSale->profit_percentage, 2),
            'status' => $deferredSale->status,
            'created_at' => $deferredSale->created_at->toISOString(),
        ], 'Deferred sale request submitted successfully');
    }

    /**
     * Get user's deferred sale requests.
     * GET /api/v1/deferred-sales
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = DeferredSale::with('product');

        // Non-admins only see their own requests
        if (! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $deferredSales = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 10));

        $formattedSales = $deferredSales->getCollection()->map(function ($sale) {
            return [
                'id' => $sale->id,
                'product' => [
                    'id' => $sale->product->id,
                    'title' => $sale->product->title,
                    'image' => $sale->product->main_image,
                ],
                'quantity' => $sale->quantity,
                'original_price' => round($sale->original_price, 2),
                'requested_price' => round($sale->requested_price, 2),
                'profit_amount' => round($sale->profit_amount, 2),
                'profit_percentage' => round($sale->profit_percentage, 2),
                'status' => $sale->status,
                'notes' => $sale->notes,
                'admin_notes' => $sale->admin_notes,
                'reviewed_at' => $sale->reviewed_at?->toISOString(),
                'completed_at' => $sale->completed_at?->toISOString(),
                'created_at' => $sale->created_at->toISOString(),
            ];
        });

        return $this->successResponse([
            'deferred_sales' => $formattedSales,
            'pagination' => [
                'current_page' => $deferredSales->currentPage(),
                'last_page' => $deferredSales->lastPage(),
                'per_page' => $deferredSales->perPage(),
                'total' => $deferredSales->total(),
            ],
        ], 'Deferred sales retrieved successfully');
    }

    /**
     * Get a specific deferred sale request.
     * GET /api/v1/deferred-sales/{id}
     */
    public function show(Request $request, int $id)
    {
        $user = $request->user();

        $sale = DeferredSale::with('product')->find($id);

        if (! $sale) {
            return $this->notFoundResponse('Deferred sale request not found');
        }

        // Check authorization
        if (! $user->isAdmin() && $sale->user_id !== $user->id) {
            return $this->forbiddenResponse('You are not authorized to view this request');
        }

        return $this->successResponse([
            'id' => $sale->id,
            'product' => [
                'id' => $sale->product->id,
                'title' => $sale->product->title,
                'image' => $sale->product->main_image,
                'current_price' => round($sale->product->price, 2),
            ],
            'quantity' => $sale->quantity,
            'original_price' => round($sale->original_price, 2),
            'requested_price' => round($sale->requested_price, 2),
            'profit_amount' => round($sale->profit_amount, 2),
            'profit_percentage' => round($sale->profit_percentage, 2),
            'status' => $sale->status,
            'notes' => $sale->notes,
            'admin_notes' => $sale->admin_notes,
            'reviewed_at' => $sale->reviewed_at?->toISOString(),
            'reviewed_by' => $sale->reviewer?->name,
            'completed_at' => $sale->completed_at?->toISOString(),
            'created_at' => $sale->created_at->toISOString(),
        ], 'Deferred sale retrieved successfully');
    }

    /**
     * Update deferred sale request status (Admin only).
     * PUT /api/v1/admin/deferred-sales/{id}
     */
    public function update(Request $request, int $id)
    {
        $user = $request->user();

        if (! $user->isAdmin()) {
            return $this->forbiddenResponse('Only admins can update deferred sale requests');
        }

        $sale = DeferredSale::find($id);

        if (! $sale) {
            return $this->notFoundResponse('Deferred sale request not found');
        }

        $validated = $request->validate([
            'status' => 'sometimes|required|in:pending,approved,rejected,completed',
            'admin_notes' => 'nullable|string|max:2000',
        ]);

        if (isset($validated['status'])) {
            $sale->status = $validated['status'];
            $sale->reviewed_at = Carbon::now();
            $sale->reviewed_by = $user->id;

            if ($validated['status'] === 'completed') {
                $sale->completed_at = Carbon::now();
            }
        }

        if (isset($validated['admin_notes'])) {
            $sale->admin_notes = $validated['admin_notes'];
        }

        $sale->save();

        return $this->successResponse([
            'id' => $sale->id,
            'status' => $sale->status,
            'admin_notes' => $sale->admin_notes,
            'reviewed_at' => $sale->reviewed_at?->toISOString(),
            'completed_at' => $sale->completed_at?->toISOString(),
            'reviewed_by' => $user->name,
        ], 'Deferred sale request updated successfully');
    }

    /**
     * Delete a deferred sale request (user can delete pending, admin can delete any).
     * DELETE /api/v1/deferred-sales/{id}
     */
    public function destroy(Request $request, int $id)
    {
        $user = $request->user();

        $sale = DeferredSale::find($id);

        if (! $sale) {
            return $this->notFoundResponse('Deferred sale request not found');
        }

        // Users can only delete their own pending requests
        if (! $user->isAdmin()) {
            if ($sale->user_id !== $user->id) {
                return $this->forbiddenResponse('You are not authorized to delete this request');
            }
            if ($sale->status !== 'pending') {
                return $this->errorResponse('Only pending requests can be deleted', 'FORBIDDEN', 403);
            }
        }

        $sale->delete();

        return $this->successResponse([], 'Deferred sale request deleted successfully');
    }

    /**
     * Get deferred sale statistics (Admin only).
     * GET /api/v1/admin/deferred-sales/stats
     */
    public function stats(Request $request)
    {
        $user = $request->user();

        if (! $user->isAdmin()) {
            return $this->forbiddenResponse('Only admins can view deferred sale stats');
        }

        $stats = [
            'total' => DeferredSale::count(),
            'pending' => DeferredSale::where('status', 'pending')->count(),
            'approved' => DeferredSale::where('status', 'approved')->count(),
            'rejected' => DeferredSale::where('status', 'rejected')->count(),
            'completed' => DeferredSale::where('status', 'completed')->count(),
            'total_profit' => round(DeferredSale::where('status', 'completed')->sum('profit_amount'), 2),
            'average_profit_percentage' => round(DeferredSale::where('status', 'completed')->avg('profit_percentage') ?? 0, 2),
        ];

        return $this->successResponse($stats, 'Deferred sale stats retrieved successfully');
    }
}
