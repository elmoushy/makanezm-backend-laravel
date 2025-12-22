<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponseTrait;
use App\Models\Order;
use App\Models\PartnershipRequest;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get dashboard statistics for the authenticated user.
     * GET /api/v1/dashboard/stats
     */
    public function getStats(Request $request)
    {
        $user = $request->user();
        $isAdmin = $user->isAdmin();

        // Base query - admins see all orders, users see only their own
        $ordersQuery = Order::query();
        if (! $isAdmin) {
            $ordersQuery->where('user_id', $user->id);
        }

        // Calculate stats
        $totalOrders = (clone $ordersQuery)->count();
        $pendingOrders = (clone $ordersQuery)->where('status', 'pending')->count();
        $processingOrders = (clone $ordersQuery)->where('status', 'processing')->count();
        $shippedOrders = (clone $ordersQuery)->where('status', 'shipped')->count();
        $completedOrders = (clone $ordersQuery)->whereIn('status', ['delivered', 'completed'])->count();
        $cancelledOrders = (clone $ordersQuery)->where('status', 'cancelled')->count();

        // Revenue calculations (from all confirmed/paid orders, excluding cancelled)
        $paidOrdersData = (clone $ordersQuery)
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->get();

        $totalRevenue = $paidOrdersData->sum('total_amount');

        // Completed orders for profit calculation (only finalized orders)
        $completedOrdersData = $paidOrdersData->whereIn('status', ['delivered', 'completed']);

        // Calculate profit (20% margin assumption for sale orders)
        // For resale orders, profit comes from resale_expected_return - total_amount
        $totalProfit = 0;
        foreach ($completedOrdersData as $order) {
            if ($order->type === 'resale' && $order->resale_returned) {
                // Resale profit is the difference between expected return and investment
                $totalProfit += $order->resale_expected_return - $order->total_amount;
            } else {
                // Sale orders: assume 20% profit margin
                $totalProfit += $order->total_amount * 0.20;
            }
        }

        // Monthly growth calculation
        $currentMonthStart = Carbon::now()->startOfMonth();
        $lastMonthStart = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

        $currentMonthOrdersQuery = (clone $ordersQuery)->where('created_at', '>=', $currentMonthStart);
        $lastMonthOrdersQuery = Order::query();
        if (! $isAdmin) {
            $lastMonthOrdersQuery->where('user_id', $user->id);
        }
        $lastMonthOrdersQuery->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd]);

        $currentMonthOrders = $currentMonthOrdersQuery->count();
        $lastMonthOrders = $lastMonthOrdersQuery->count();

        $monthlyGrowth = 0;
        if ($lastMonthOrders > 0) {
            $monthlyGrowth = round((($currentMonthOrders - $lastMonthOrders) / $lastMonthOrders) * 100, 1);
        } elseif ($currentMonthOrders > 0) {
            $monthlyGrowth = 100;
        }

        // Revenue growth (all confirmed/paid orders, excluding cancelled)
        $currentMonthRevenue = (clone $ordersQuery)
            ->where('created_at', '>=', $currentMonthStart)
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->sum('total_amount');

        $lastMonthRevenueQuery = Order::query();
        if (! $isAdmin) {
            $lastMonthRevenueQuery->where('user_id', $user->id);
        }
        $lastMonthRevenue = $lastMonthRevenueQuery
            ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->sum('total_amount');

        $revenueGrowth = 0;
        if ($lastMonthRevenue > 0) {
            $revenueGrowth = round((($currentMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1);
        } elseif ($currentMonthRevenue > 0) {
            $revenueGrowth = 100;
        }

        // Order type breakdown
        $saleOrders = (clone $ordersQuery)->where('type', 'sale')->count();
        $resaleOrders = (clone $ordersQuery)->where('type', 'resale')->count();

        // Investment stats (for resale orders)
        $pendingInvestments = (clone $ordersQuery)
            ->where('type', 'resale')
            ->where('resale_returned', false)
            ->count();

        $expectedReturns = (clone $ordersQuery)
            ->where('type', 'resale')
            ->where('resale_returned', false)
            ->sum('resale_expected_return');

        $completedInvestments = (clone $ordersQuery)
            ->where('type', 'resale')
            ->where('resale_returned', true)
            ->count();

        // Calculate wallet (sale) and resale revenues separately (all paid orders)
        $walletOrdersData = (clone $ordersQuery)
            ->where('type', 'sale')
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->get();
        $walletRevenue = $walletOrdersData->sum('total_amount');
        $walletOrdersCount = $walletOrdersData->count();

        $investmentOrdersData = (clone $ordersQuery)
            ->where('type', 'resale')
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->get();
        $investmentRevenue = $investmentOrdersData->sum('total_amount');
        $investmentOrdersCount = $investmentOrdersData->count();

        // Admin-specific stats
        $adminStats = [];
        if ($isAdmin) {
            $adminStats = [
                'total_users' => User::where('role', 'USER')->count(),
                'total_admins' => User::where('role', 'ADMIN')->count(),
                'total_products' => Product::count(),
                'active_products' => Product::where('is_active', true)->count(),
                'low_stock_products' => Product::where('stock_quantity', '<', 10)->where('is_active', true)->count(),
                'out_of_stock_products' => Product::where('in_stock', false)->count(),
                'pending_partnerships' => class_exists(PartnershipRequest::class)
                    ? PartnershipRequest::where('status', 'pending')->count()
                    : 0,
            ];
        }

        return $this->successResponse([
            'orders' => [
                'total' => $totalOrders,
                'pending' => $pendingOrders,
                'processing' => $processingOrders,
                'shipped' => $shippedOrders,
                'completed' => $completedOrders,
                'cancelled' => $cancelledOrders,
                'sale_orders' => $saleOrders,
                'resale_orders' => $resaleOrders,
            ],
            'revenue' => [
                'total' => round($totalRevenue, 2),
                'profit' => round($totalProfit, 2),
                'current_month' => round($currentMonthRevenue, 2),
                'last_month' => round($lastMonthRevenue, 2),
                'growth' => $revenueGrowth,
                // New: Revenue breakdown by order type
                'wallet_revenue' => round($walletRevenue, 2),
                'investment_revenue' => round($investmentRevenue, 2),
            ],
            // New: Orders breakdown by type with revenue
            'wallet_orders' => [
                'total' => $saleOrders,
                'completed' => $walletOrdersCount,
                'revenue' => round($walletRevenue, 2),
            ],
            'investment_orders' => [
                'total' => $resaleOrders,
                'completed' => $investmentOrdersCount,
                'revenue' => round($investmentRevenue, 2),
            ],
            'growth' => [
                'orders' => $monthlyGrowth,
                'revenue' => $revenueGrowth,
            ],
            'investments' => [
                'pending' => $pendingInvestments,
                'expected_returns' => round($expectedReturns, 2),
                'completed' => $completedInvestments,
            ],
            'admin' => $adminStats,
        ], 'Dashboard stats retrieved successfully');
    }

    /**
     * Get recent orders for dashboard.
     * GET /api/v1/dashboard/recent-orders
     */
    public function getRecentOrders(Request $request)
    {
        $user = $request->user();
        $limit = $request->input('limit', 5);

        $query = Order::with('items.product')
            ->orderBy('created_at', 'desc');

        if (! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        $orders = $query->limit($limit)->get();

        $formattedOrders = $orders->map(function ($order) {
            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'type' => $order->type,
                'status' => $order->status,
                'total_amount' => round($order->total_amount, 2),
                'items_count' => $order->items->count(),
                'created_at' => $order->created_at->toISOString(),
                'resale_info' => $order->type === 'resale' ? [
                    'expected_return' => round($order->resale_expected_return, 2),
                    'return_date' => $order->resale_return_date?->toISOString(),
                    'returned' => $order->resale_returned,
                ] : null,
            ];
        });

        return $this->successResponse($formattedOrders, 'Recent orders retrieved successfully');
    }

    /**
     * Get sales chart data.
     * GET /api/v1/dashboard/chart/sales
     */
    public function getSalesChart(Request $request)
    {
        $user = $request->user();
        $period = $request->input('period', 'week'); // week, month, year

        $query = Order::whereIn('status', ['delivered', 'completed']);
        if (! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        $data = [];

        if ($period === 'week') {
            // Last 7 days
            for ($i = 6; $i >= 0; $i--) {
                $date = Carbon::now()->subDays($i);
                $dayStart = $date->copy()->startOfDay();
                $dayEnd = $date->copy()->endOfDay();

                $dayOrders = (clone $query)->whereBetween('created_at', [$dayStart, $dayEnd]);
                $data[] = [
                    'label' => $date->format('D'),
                    'date' => $date->toDateString(),
                    'orders' => $dayOrders->count(),
                    'revenue' => round($dayOrders->sum('total_amount'), 2),
                ];
            }
        } elseif ($period === 'month') {
            // Last 30 days, grouped by week
            for ($i = 3; $i >= 0; $i--) {
                $weekStart = Carbon::now()->subWeeks($i)->startOfWeek();
                $weekEnd = Carbon::now()->subWeeks($i)->endOfWeek();

                $weekOrders = (clone $query)->whereBetween('created_at', [$weekStart, $weekEnd]);
                $data[] = [
                    'label' => 'Week '.(4 - $i),
                    'date' => $weekStart->toDateString(),
                    'orders' => $weekOrders->count(),
                    'revenue' => round($weekOrders->sum('total_amount'), 2),
                ];
            }
        } elseif ($period === 'year') {
            // Last 12 months
            for ($i = 11; $i >= 0; $i--) {
                $monthStart = Carbon::now()->subMonths($i)->startOfMonth();
                $monthEnd = Carbon::now()->subMonths($i)->endOfMonth();

                $monthOrders = (clone $query)->whereBetween('created_at', [$monthStart, $monthEnd]);
                $data[] = [
                    'label' => $monthStart->format('M'),
                    'date' => $monthStart->toDateString(),
                    'orders' => $monthOrders->count(),
                    'revenue' => round($monthOrders->sum('total_amount'), 2),
                ];
            }
        }

        return $this->successResponse($data, 'Sales chart data retrieved successfully');
    }

    /**
     * Get alerts/notifications for dashboard.
     * GET /api/v1/dashboard/alerts
     */
    public function getAlerts(Request $request)
    {
        $user = $request->user();
        $alerts = [];

        // Orders query
        $ordersQuery = Order::query();
        if (! $user->isAdmin()) {
            $ordersQuery->where('user_id', $user->id);
        }

        // Pending orders alert
        $pendingOrders = (clone $ordersQuery)->where('status', 'pending')->count();
        if ($pendingOrders > 0) {
            $alerts[] = [
                'type' => 'warning',
                'icon' => 'clock',
                'title' => 'Pending Orders',
                'message' => "You have {$pendingOrders} pending order(s) awaiting action.",
                'count' => $pendingOrders,
            ];
        }

        // Upcoming investment returns (within 7 days)
        $upcomingReturns = (clone $ordersQuery)
            ->where('type', 'resale')
            ->where('resale_returned', false)
            ->whereBetween('resale_return_date', [Carbon::now(), Carbon::now()->addDays(7)])
            ->count();

        if ($upcomingReturns > 0) {
            $alerts[] = [
                'type' => 'info',
                'icon' => 'trending-up',
                'title' => 'Upcoming Returns',
                'message' => "You have {$upcomingReturns} investment(s) maturing within 7 days.",
                'count' => $upcomingReturns,
            ];
        }

        // Admin-specific alerts
        if ($user->isAdmin()) {
            // Low stock products
            $lowStock = Product::where('stock_quantity', '<', 10)
                ->where('stock_quantity', '>', 0)
                ->where('is_active', true)
                ->count();

            if ($lowStock > 0) {
                $alerts[] = [
                    'type' => 'warning',
                    'icon' => 'package',
                    'title' => 'Low Stock',
                    'message' => "{$lowStock} product(s) are running low on stock.",
                    'count' => $lowStock,
                ];
            }

            // Out of stock products
            $outOfStock = Product::where('in_stock', false)->where('is_active', true)->count();
            if ($outOfStock > 0) {
                $alerts[] = [
                    'type' => 'error',
                    'icon' => 'alert-circle',
                    'title' => 'Out of Stock',
                    'message' => "{$outOfStock} active product(s) are out of stock.",
                    'count' => $outOfStock,
                ];
            }

            // New users this week
            $newUsers = User::where('created_at', '>=', Carbon::now()->subDays(7))->count();
            if ($newUsers > 0) {
                $alerts[] = [
                    'type' => 'success',
                    'icon' => 'users',
                    'title' => 'New Users',
                    'message' => "{$newUsers} new user(s) registered this week.",
                    'count' => $newUsers,
                ];
            }
        }

        return $this->successResponse($alerts, 'Alerts retrieved successfully');
    }
}
