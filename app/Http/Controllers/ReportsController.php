<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponseTrait;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get comprehensive reports data.
     * GET /api/v1/reports
     *
     * Query params:
     * - period: 'week' | 'month' | 'year' (default: 'month')
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (! $user || ! $user->isAdmin()) {
            return $this->forbiddenResponse('Only admins can access reports.');
        }

        $period = $request->input('period', 'month');

        return $this->successResponse([
            'overview' => $this->getOverviewStats($period),
            'chart_data' => $this->getChartData($period),
            'order_status' => $this->getOrderStatusDistribution(),
            'order_types' => $this->getOrderTypesDistribution(),
            'top_products' => $this->getTopProducts(),
            'recent_activity' => $this->getRecentActivity(),
            'period' => $period,
            'generated_at' => now()->toISOString(),
        ], 'Reports data retrieved successfully');
    }

    /**
     * Get public sales report for homepage (Public - No auth required).
     * GET /api/v1/reports/public-sales
     *
     * Returns weekly sales data showing store sales vs merchant (resale) sales.
     * This is a public endpoint optimized for the homepage chart.
     */
    public function getPublicSalesReport(Request $request)
    {
        $now = Carbon::now();
        $data = [];

        // Get last 7 days data
        for ($i = 6; $i >= 0; $i--) {
            $date = $now->copy()->subDays($i);
            $dayStart = $date->copy()->startOfDay();
            $dayEnd = $date->copy()->endOfDay();

            // Get orders for this day
            $saleOrders = Order::whereBetween('created_at', [$dayStart, $dayEnd])
                ->where('type', 'sale')
                ->get();

            $resaleOrders = Order::whereBetween('created_at', [$dayStart, $dayEnd])
                ->where('type', 'resale')
                ->get();

            $data[] = [
                'day' => $date->format('l'),
                'day_ar' => $this->getArabicDayName($date->dayOfWeek),
                'date' => $date->toDateString(),
                'storeSales' => round((float) $saleOrders->sum('total_amount'), 2),
                'merchantSales' => round((float) $resaleOrders->sum('total_amount'), 2),
                'storeOrdersCount' => $saleOrders->count(),
                'merchantOrdersCount' => $resaleOrders->count(),
            ];
        }

        // Calculate totals
        $totalStoreSales = collect($data)->sum('storeSales');
        $totalMerchantSales = collect($data)->sum('merchantSales');

        return $this->successResponse([
            'sales_data' => $data,
            'summary' => [
                'total_store_sales' => round($totalStoreSales, 2),
                'total_merchant_sales' => round($totalMerchantSales, 2),
                'total_sales' => round($totalStoreSales + $totalMerchantSales, 2),
            ],
            'period' => 'week',
            'generated_at' => now()->toISOString(),
        ], 'Sales report retrieved successfully');
    }

    /**
     * Get overview statistics.
     */
    private function getOverviewStats(string $period): array
    {
        // Define date ranges based on period
        $now = Carbon::now();
        $currentStart = $this->getPeriodStart($now, $period);
        $previousStart = $this->getPeriodStart($now->copy()->sub($this->getPeriodInterval($period)), $period);
        $previousEnd = $currentStart->copy()->subSecond();

        // Current period orders
        $currentOrders = Order::where('created_at', '>=', $currentStart)->get();
        $previousOrders = Order::whereBetween('created_at', [$previousStart, $previousEnd])->get();

        // Revenue calculations (from all orders, not just completed)
        $currentRevenue = $currentOrders->sum('total_amount');
        $previousRevenue = $previousOrders->sum('total_amount');

        // Completed revenue (for accurate profit calculation)
        $currentCompletedOrders = Order::where('created_at', '>=', $currentStart)
            ->whereIn('status', ['delivered', 'completed'])
            ->get();

        $currentCompletedRevenue = $currentCompletedOrders->sum('total_amount');

        // All-time stats
        $allTimeOrders = Order::all();
        $totalRevenue = $allTimeOrders->sum('total_amount');
        $totalOrders = $allTimeOrders->count();

        // Calculate profit (completed orders only)
        $completedOrders = Order::whereIn('status', ['delivered', 'completed'])->get();
        $totalProfit = $this->calculateProfit($completedOrders);

        // Average order value
        $avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;
        $currentAvgOrderValue = $currentOrders->count() > 0
            ? $currentRevenue / $currentOrders->count()
            : 0;
        $previousAvgOrderValue = $previousOrders->count() > 0
            ? $previousRevenue / $previousOrders->count()
            : 0;

        // Products stats
        $totalProducts = Product::count();
        $activeProducts = Product::where('is_active', true)->count();

        // Growth calculations
        $revenueGrowth = $this->calculateGrowth($currentRevenue, $previousRevenue);
        $ordersGrowth = $this->calculateGrowth($currentOrders->count(), $previousOrders->count());
        $avgOrderGrowth = $this->calculateGrowth($currentAvgOrderValue, $previousAvgOrderValue);

        return [
            'total_revenue' => round((float) $totalRevenue, 2),
            'current_period_revenue' => round((float) $currentRevenue, 2),
            'previous_period_revenue' => round((float) $previousRevenue, 2),
            'revenue_growth' => round($revenueGrowth, 1),
            'total_orders' => $totalOrders,
            'current_period_orders' => $currentOrders->count(),
            'previous_period_orders' => $previousOrders->count(),
            'orders_growth' => round($ordersGrowth, 1),
            'avg_order_value' => round($avgOrderValue, 2),
            'current_avg_order_value' => round($currentAvgOrderValue, 2),
            'avg_order_growth' => round($avgOrderGrowth, 1),
            'total_products' => $totalProducts,
            'active_products' => $activeProducts,
            'total_profit' => round((float) $totalProfit, 2),
            'completed_revenue' => round((float) $currentCompletedRevenue, 2),
        ];
    }

    /**
     * Get chart data for the selected period.
     */
    private function getChartData(string $period): array
    {
        $data = [];
        $now = Carbon::now();

        if ($period === 'week') {
            // Last 7 days
            for ($i = 6; $i >= 0; $i--) {
                $date = $now->copy()->subDays($i);
                $dayStart = $date->copy()->startOfDay();
                $dayEnd = $date->copy()->endOfDay();

                $orders = Order::whereBetween('created_at', [$dayStart, $dayEnd])->get();

                $data[] = [
                    'label' => $date->format('D'),
                    'label_ar' => $this->getArabicDayName($date->dayOfWeek),
                    'date' => $date->toDateString(),
                    'orders' => $orders->count(),
                    'revenue' => round((float) $orders->sum('total_amount'), 2),
                    'sale_orders' => $orders->where('type', 'sale')->count(),
                    'resale_orders' => $orders->where('type', 'resale')->count(),
                ];
            }
        } elseif ($period === 'month') {
            // Last 30 days, daily
            for ($i = 29; $i >= 0; $i--) {
                $date = $now->copy()->subDays($i);
                $dayStart = $date->copy()->startOfDay();
                $dayEnd = $date->copy()->endOfDay();

                $orders = Order::whereBetween('created_at', [$dayStart, $dayEnd])->get();

                $data[] = [
                    'label' => $date->format('M d'),
                    'label_ar' => $date->format('d').' '.$this->getArabicMonthName($date->month),
                    'date' => $date->toDateString(),
                    'orders' => $orders->count(),
                    'revenue' => round((float) $orders->sum('total_amount'), 2),
                    'sale_orders' => $orders->where('type', 'sale')->count(),
                    'resale_orders' => $orders->where('type', 'resale')->count(),
                ];
            }
        } elseif ($period === 'year') {
            // Last 12 months
            for ($i = 11; $i >= 0; $i--) {
                $monthStart = $now->copy()->subMonths($i)->startOfMonth();
                $monthEnd = $now->copy()->subMonths($i)->endOfMonth();

                $orders = Order::whereBetween('created_at', [$monthStart, $monthEnd])->get();

                $data[] = [
                    'label' => $monthStart->format('M Y'),
                    'label_ar' => $this->getArabicMonthName($monthStart->month).' '.$monthStart->year,
                    'date' => $monthStart->toDateString(),
                    'orders' => $orders->count(),
                    'revenue' => round((float) $orders->sum('total_amount'), 2),
                    'sale_orders' => $orders->where('type', 'sale')->count(),
                    'resale_orders' => $orders->where('type', 'resale')->count(),
                ];
            }
        }

        return $data;
    }

    /**
     * Get order status distribution.
     */
    private function getOrderStatusDistribution(): array
    {
        $statuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'completed', 'cancelled'];
        $distribution = [];

        $totalOrders = Order::count();

        foreach ($statuses as $status) {
            $count = Order::where('status', $status)->count();
            $distribution[] = [
                'status' => $status,
                'count' => $count,
                'percentage' => $totalOrders > 0 ? round(($count / $totalOrders) * 100, 1) : 0,
            ];
        }

        return [
            'total' => $totalOrders,
            'distribution' => $distribution,
        ];
    }

    /**
     * Get order types distribution (sale vs resale).
     */
    private function getOrderTypesDistribution(): array
    {
        $totalOrders = Order::count();
        $saleOrders = Order::where('type', 'sale')->get();
        $resaleOrders = Order::where('type', 'resale')->get();

        $saleRevenue = $saleOrders->sum('total_amount');
        $resaleRevenue = $resaleOrders->sum('total_amount');
        $totalRevenue = $saleRevenue + $resaleRevenue;

        // Resale profit calculation
        $resaleProfit = $resaleOrders->where('resale_returned', true)
            ->sum(function ($order) {
                return $order->resale_expected_return - $order->total_amount;
            });

        return [
            'sale' => [
                'count' => $saleOrders->count(),
                'percentage' => $totalOrders > 0 ? round(($saleOrders->count() / $totalOrders) * 100, 1) : 0,
                'revenue' => round((float) $saleRevenue, 2),
                'revenue_percentage' => $totalRevenue > 0 ? round(($saleRevenue / $totalRevenue) * 100, 1) : 0,
            ],
            'resale' => [
                'count' => $resaleOrders->count(),
                'percentage' => $totalOrders > 0 ? round(($resaleOrders->count() / $totalOrders) * 100, 1) : 0,
                'revenue' => round((float) $resaleRevenue, 2),
                'revenue_percentage' => $totalRevenue > 0 ? round(($resaleRevenue / $totalRevenue) * 100, 1) : 0,
                'profit' => round((float) $resaleProfit, 2),
                'pending_returns' => $resaleOrders->where('resale_returned', false)->count(),
                'completed_returns' => $resaleOrders->where('resale_returned', true)->count(),
            ],
            'total_revenue' => round((float) $totalRevenue, 2),
        ];
    }

    /**
     * Get top selling products.
     */
    private function getTopProducts(int $limit = 5): array
    {
        $topProducts = DB::table('order_items')
            ->select(
                'product_id',
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('SUM(total_price) as total_revenue'),
                DB::raw('COUNT(DISTINCT order_id) as order_count')
            )
            ->groupBy('product_id')
            ->orderByDesc('total_revenue')
            ->limit($limit)
            ->get();

        $products = [];
        foreach ($topProducts as $item) {
            $product = Product::find($item->product_id);
            if ($product) {
                $products[] = [
                    'id' => $product->id,
                    'title' => $product->title_en ?? $product->title_ar ?? 'Unknown',
                    'title_ar' => $product->title_ar,
                    'title_en' => $product->title_en,
                    'price' => round((float) $product->price, 2),
                    'total_quantity_sold' => (int) $item->total_quantity,
                    'total_revenue' => round((float) $item->total_revenue, 2),
                    'order_count' => (int) $item->order_count,
                ];
            }
        }

        return $products;
    }

    /**
     * Get recent activity (orders).
     */
    private function getRecentActivity(int $limit = 10): array
    {
        $recentOrders = Order::with('user')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return $recentOrders->map(function ($order) {
            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'type' => $order->type,
                'status' => $order->status,
                'total_amount' => round((float) $order->total_amount, 2),
                'user_name' => $order->user?->name ?? 'Unknown',
                'user_email' => $order->user?->email ?? '',
                'created_at' => $order->created_at->toISOString(),
                'created_at_human' => $order->created_at->diffForHumans(),
            ];
        })->toArray();
    }

    /**
     * Calculate profit from orders.
     */
    private function calculateProfit($orders): float
    {
        $profit = 0;

        foreach ($orders as $order) {
            if ($order->type === 'resale' && $order->resale_returned) {
                // Resale profit: expected return - total amount
                $profit += (float) $order->resale_expected_return - (float) $order->total_amount;
            } else {
                // Sale orders: assume 20% profit margin
                $profit += (float) $order->total_amount * 0.20;
            }
        }

        return $profit;
    }

    /**
     * Calculate growth percentage.
     */
    private function calculateGrowth($current, $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }

        return (($current - $previous) / $previous) * 100;
    }

    /**
     * Get start of period.
     */
    private function getPeriodStart(Carbon $date, string $period): Carbon
    {
        return match ($period) {
            'week' => $date->copy()->startOfWeek(),
            'month' => $date->copy()->startOfMonth(),
            'year' => $date->copy()->startOfYear(),
            default => $date->copy()->startOfMonth(),
        };
    }

    /**
     * Get period interval string.
     */
    private function getPeriodInterval(string $period): string
    {
        return match ($period) {
            'week' => '1 week',
            'month' => '1 month',
            'year' => '1 year',
            default => '1 month',
        };
    }

    /**
     * Get Arabic day name.
     */
    private function getArabicDayName(int $dayOfWeek): string
    {
        $days = ['أحد', 'إثنين', 'ثلاثاء', 'أربعاء', 'خميس', 'جمعة', 'سبت'];

        return $days[$dayOfWeek] ?? '';
    }

    /**
     * Get Arabic month name.
     */
    private function getArabicMonthName(int $month): string
    {
        $months = [
            1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
            5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
            9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
        ];

        return $months[$month] ?? '';
    }

    /**
     * Export reports data.
     * GET /api/v1/reports/export
     */
    public function export(Request $request)
    {
        $user = $request->user();

        if (! $user || ! $user->isAdmin()) {
            return $this->forbiddenResponse('Only admins can export reports.');
        }

        $period = $request->input('period', 'month');
        $format = $request->input('format', 'json');

        $data = [
            'overview' => $this->getOverviewStats($period),
            'chart_data' => $this->getChartData($period),
            'order_status' => $this->getOrderStatusDistribution(),
            'order_types' => $this->getOrderTypesDistribution(),
            'top_products' => $this->getTopProducts(10),
            'period' => $period,
            'exported_at' => now()->toISOString(),
            'exported_by' => $user->name,
        ];

        if ($format === 'csv') {
            // Return CSV-friendly data structure
            return $this->successResponse([
                'data' => $data,
                'csv_ready' => $this->prepareCsvData($data),
            ], 'Export data prepared successfully');
        }

        return $this->successResponse($data, 'Export data retrieved successfully');
    }

    /**
     * Prepare data for CSV export.
     */
    private function prepareCsvData(array $data): array
    {
        $csvData = [];

        // Overview summary
        $csvData['overview'] = [
            ['Metric', 'Value'],
            ['Total Revenue', $data['overview']['total_revenue']],
            ['Total Orders', $data['overview']['total_orders']],
            ['Average Order Value', $data['overview']['avg_order_value']],
            ['Revenue Growth', $data['overview']['revenue_growth'].'%'],
            ['Orders Growth', $data['overview']['orders_growth'].'%'],
            ['Active Products', $data['overview']['active_products']],
        ];

        // Chart data
        $chartHeaders = ['Date', 'Label', 'Orders', 'Revenue', 'Sale Orders', 'Resale Orders'];
        $chartRows = array_map(function ($item) {
            return [
                $item['date'],
                $item['label'],
                $item['orders'],
                $item['revenue'],
                $item['sale_orders'],
                $item['resale_orders'],
            ];
        }, $data['chart_data']);

        $csvData['chart_data'] = array_merge([$chartHeaders], $chartRows);

        return $csvData;
    }
}
