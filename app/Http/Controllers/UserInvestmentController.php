<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponseTrait;
use App\Models\Investment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * UserInvestmentController
 *
 * Allows regular users to view their own investments and track payout status.
 */
class UserInvestmentController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get authenticated user's investments.
     * GET /api/v1/user/investments
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->unauthorizedResponse('Please login to view investments.');
        }

        $status = $request->input('status'); // active, matured, paid_out, all
        $perPage = $request->input('per_page', 15);

        $query = Investment::with(['product', 'order', 'orderItem'])
            ->where('user_id', $user->id)
            ->whereNotIn('status', [Investment::STATUS_CANCELLED]); // Exclude cancelled

        // Filter by status if provided
        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        $investments = $query->orderBy('investment_date', 'desc')->paginate($perPage);

        return $this->successResponse([
            'investments' => $investments->map(function ($investment) {
                return [
                    'id' => $investment->id,
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
                        'quantity' => $investment->orderItem?->quantity,
                        'unit_price' => (float) $investment->orderItem?->unit_price,
                    ],
                    'invested_amount' => (float) $investment->invested_amount,
                    'expected_return' => (float) $investment->expected_return,
                    'profit_amount' => (float) $investment->profit_amount,
                    'profit_percentage' => (float) $investment->plan_profit_percentage,
                    'plan_months' => $investment->plan_months,
                    'plan_label' => $investment->plan_label,
                    'investment_date' => $investment->investment_date?->format('Y-m-d'),
                    'maturity_date' => $investment->maturity_date?->format('Y-m-d'),
                    'status' => $investment->status,
                    'status_display' => $this->getStatusDisplay($investment),
                    'paid_out_at' => $investment->paid_out_at?->format('Y-m-d H:i:s'),
                    'days_until_maturity' => $investment->daysUntilMaturity(),
                    'has_matured' => $investment->hasMatured(),
                ];
            }),
            'pagination' => [
                'current_page' => $investments->currentPage(),
                'last_page' => $investments->lastPage(),
                'per_page' => $investments->perPage(),
                'total' => $investments->total(),
            ],
            'summary' => $this->getSummary($user->id),
        ]);
    }

    /**
     * Get summary of user's investments.
     */
    private function getSummary(int $userId): array
    {
        $active = Investment::where('user_id', $userId)->active()->count();
        $matured = Investment::where('user_id', $userId)->where('status', Investment::STATUS_MATURED)->count();
        $paidOut = Investment::where('user_id', $userId)->paidOut()->count();

        $totalInvested = Investment::where('user_id', $userId)
            ->whereIn('status', [Investment::STATUS_ACTIVE, Investment::STATUS_MATURED, Investment::STATUS_PAID_OUT])
            ->sum('invested_amount');

        $totalExpectedReturn = Investment::where('user_id', $userId)
            ->whereIn('status', [Investment::STATUS_ACTIVE, Investment::STATUS_MATURED])
            ->sum('expected_return');

        $totalPaidOut = Investment::where('user_id', $userId)
            ->paidOut()
            ->sum('expected_return');

        $pendingPayout = Investment::where('user_id', $userId)
            ->where('status', Investment::STATUS_MATURED)
            ->sum('expected_return');

        return [
            'active_count' => $active,
            'matured_count' => $matured,
            'paid_out_count' => $paidOut,
            'total_invested' => (float) $totalInvested,
            'total_expected_return' => (float) $totalExpectedReturn,
            'total_paid_out' => (float) $totalPaidOut,
            'pending_payout' => (float) $pendingPayout,
            'total_profit' => (float) ($totalExpectedReturn - $totalInvested),
        ];
    }

    /**
     * Get user-friendly status display text.
     */
    private function getStatusDisplay(Investment $investment): string
    {
        switch ($investment->status) {
            case Investment::STATUS_ACTIVE:
                if ($investment->hasMatured()) {
                    return 'Matured - Pending Admin Payout';
                }

                return 'Active - Waiting for Maturity';
            case Investment::STATUS_MATURED:
                return 'Matured - Pending Admin Payout';
            case Investment::STATUS_PAID_OUT:
                return 'Completed - Paid Out';
            case Investment::STATUS_PENDING:
                return 'Pending - Order Confirmation';
            default:
                return ucfirst($investment->status);
        }
    }
}
