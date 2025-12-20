<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponseTrait;
use App\Models\Investment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * InvestmentPayoutController
 *
 * Manages investment payouts for admin users.
 * Lists matured investments and allows marking them as paid.
 */
class InvestmentPayoutController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get pending payouts (matured investments not yet paid).
     * GET /api/v1/admin/investment-payouts
     */
    public function pendingPayouts(Request $request): JsonResponse
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Only admins can view investment payouts.');
        }

        // First, auto-mature any active investments that have passed their maturity date
        Investment::shouldMature()->update(['status' => Investment::STATUS_MATURED]);

        // Get pending payouts with pagination
        $payouts = Investment::with(['user', 'product', 'order', 'orderItem'])
            ->pendingPayout()
            ->orderBy('maturity_date', 'asc')
            ->paginate($request->input('per_page', 15));

        return $this->successResponse([
            'payouts' => $payouts->map(function ($investment) {
                return [
                    'id' => $investment->id,
                    'user' => [
                        'id' => $investment->user->id,
                        'name' => $investment->user->name,
                        'email' => $investment->user->email,
                    ],
                    'product' => [
                        'id' => $investment->product?->id,
                        'title' => $investment->product?->title_en,
                        'title_ar' => $investment->product?->title_ar,
                    ],
                    'order' => [
                        'id' => $investment->order?->id,
                        'order_number' => $investment->order?->order_number,
                    ],
                    'order_item' => [
                        'id' => $investment->orderItem?->id,
                        'quantity' => $investment->orderItem?->quantity,
                        'unit_price' => (float) $investment->orderItem?->unit_price,
                        'total_price' => (float) $investment->orderItem?->total_price,
                    ],
                    'order_number' => $investment->order?->order_number,
                    'invested_amount' => (float) $investment->invested_amount,
                    'expected_return' => (float) $investment->expected_return,
                    'profit_amount' => (float) $investment->profit_amount,
                    'profit_percentage' => (float) $investment->plan_profit_percentage,
                    'plan_months' => $investment->plan_months,
                    'plan_label' => $investment->plan_label,
                    'investment_date' => $investment->investment_date?->format('Y-m-d'),
                    'maturity_date' => $investment->maturity_date?->format('Y-m-d'),
                    'days_since_matured' => Carbon::now()->diffInDays($investment->maturity_date, false) * -1,
                    'status' => $investment->status,
                ];
            }),
            'pagination' => [
                'current_page' => $payouts->currentPage(),
                'last_page' => $payouts->lastPage(),
                'per_page' => $payouts->perPage(),
                'total' => $payouts->total(),
            ],
            'summary' => [
                'total_pending' => Investment::pendingPayout()->count(),
                'total_amount_to_pay' => (float) Investment::pendingPayout()->sum('expected_return'),
            ],
        ]);
    }

    /**
     * Get paid payouts history.
     * GET /api/v1/admin/investment-payouts/history
     */
    public function paidHistory(Request $request): JsonResponse
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Only admins can view payout history.');
        }

        $payouts = Investment::with(['user', 'product', 'order', 'orderItem', 'paidByUser'])
            ->paidOut()
            ->orderBy('paid_out_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return $this->successResponse([
            'payouts' => $payouts->map(function ($investment) {
                return [
                    'id' => $investment->id,
                    'user' => [
                        'id' => $investment->user->id,
                        'name' => $investment->user->name,
                        'email' => $investment->user->email,
                    ],
                    'product' => [
                        'id' => $investment->product?->id,
                        'title' => $investment->product?->title_en,
                        'title_ar' => $investment->product?->title_ar,
                    ],
                    'order' => [
                        'id' => $investment->order?->id,
                        'order_number' => $investment->order?->order_number,
                    ],
                    'order_item' => [
                        'id' => $investment->orderItem?->id,
                        'quantity' => $investment->orderItem?->quantity,
                        'unit_price' => (float) $investment->orderItem?->unit_price,
                        'total_price' => (float) $investment->orderItem?->total_price,
                    ],
                    'order_number' => $investment->order?->order_number,
                    'invested_amount' => (float) $investment->invested_amount,
                    'expected_return' => (float) $investment->expected_return,
                    'profit_amount' => (float) $investment->profit_amount,
                    'profit_percentage' => (float) $investment->plan_profit_percentage,
                    'plan_months' => $investment->plan_months,
                    'investment_date' => $investment->investment_date?->format('Y-m-d'),
                    'maturity_date' => $investment->maturity_date?->format('Y-m-d'),
                    'paid_out_at' => $investment->paid_out_at?->format('Y-m-d H:i:s'),
                    'paid_by' => $investment->paidByUser ? [
                        'id' => $investment->paidByUser->id,
                        'name' => $investment->paidByUser->name,
                    ] : null,
                    'status' => $investment->status,
                ];
            }),
            'pagination' => [
                'current_page' => $payouts->currentPage(),
                'last_page' => $payouts->lastPage(),
                'per_page' => $payouts->perPage(),
                'total' => $payouts->total(),
            ],
        ]);
    }

    /**
     * Mark an investment as paid.
     * POST /api/v1/admin/investment-payouts/{id}/mark-paid
     */
    public function markAsPaid(Request $request, int $id): JsonResponse
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Only admins can mark payouts as paid.');
        }

        $investment = Investment::find($id);

        if (! $investment) {
            return $this->notFoundResponse('Investment not found.');
        }

        if ($investment->status !== Investment::STATUS_MATURED) {
            return $this->errorResponse('Investment is not ready for payout. Status: '.$investment->status, 422);
        }

        if ($investment->paid_out_at !== null) {
            return $this->errorResponse('Investment has already been paid out.', 422);
        }

        // Mark as paid
        $investment->markAsPaidOut($request->user()->id);

        return $this->successResponse([
            'investment' => [
                'id' => $investment->id,
                'status' => $investment->status,
                'paid_out_at' => $investment->paid_out_at->format('Y-m-d H:i:s'),
                'paid_by' => [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                ],
            ],
        ], 'Investment marked as paid successfully.');
    }

    /**
     * Get summary statistics for payouts.
     * GET /api/v1/admin/investment-payouts/summary
     */
    public function summary(Request $request): JsonResponse
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Only admins can view payout summary.');
        }

        // Auto-mature investments first
        Investment::shouldMature()->update(['status' => Investment::STATUS_MATURED]);

        return $this->successResponse([
            'pending_count' => Investment::pendingPayout()->count(),
            'pending_total_return' => (float) Investment::pendingPayout()->sum('expected_return'),
            'pending_total_profit' => (float) Investment::pendingPayout()->sum('profit_amount'),
            'paid_count' => Investment::paidOut()->count(),
            'paid_total_return' => (float) Investment::paidOut()->sum('expected_return'),
            'active_count' => Investment::active()->count(),
            'active_total_invested' => (float) Investment::active()->sum('invested_amount'),
        ]);
    }
}
