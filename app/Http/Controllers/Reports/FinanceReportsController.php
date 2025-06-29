<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Expense;
use App\Models\Item;
use App\Models\Order;
use App\Models\PurchaseOrder;
use App\Models\DamageStock;
use App\Models\ItemCost;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class FinanceReportsController extends Controller
{
    protected function cleanUtf8($value)
    {
        if (is_string($value)) {
            $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);
            if (!mb_check_encoding($value, 'UTF-8')) {
                $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
            }
        }
        return $value;
    }

    public function previewExpensesReport(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'expense_type' => 'nullable|string',
            'store_id' => 'nullable|exists:stores,id',
        ]);

        $startDate = $validated['start_date'] ?? now()->subMonth()->format('Y-m-d');
        $endDate = $validated['end_date'] ?? now()->format('Y-m-d');
        $expenseType = $validated['expense_type'] ?? null;
        $storeId = $validated['store_id'] ?? null;

        \Log::info("Querying expenses report", [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'expense_type' => $expenseType,
            'store_id' => $storeId
        ]);

        $query = Expense::leftJoin('users', 'expenses.user_id', '=', 'users.id')
            ->leftJoin('items', 'expenses.linked_shop_item_id', '=', 'items.id')
            ->leftJoin('stocks', 'items.id', '=', 'stocks.item_id')
            ->leftJoin('stores', 'stocks.store_id', '=', 'stores.id')
            ->select(
                'expenses.expense_date',
                'expenses.expense_type',
                'expenses.amount',
                'expenses.description',
                'expenses.reference_number',
                DB::raw('COALESCE(users.fullname, "Unknown") as user_name'),
                DB::raw('COALESCE(items.name, "None") as linked_item_name'),
                DB::raw('COALESCE(stores.name, "N/A") as store_name')
            )
            ->whereBetween('expenses.expense_date', [$startDate, $endDate])
            ->when($expenseType, fn($q) => $q->where('expenses.expense_type', $expenseType))
            ->when($storeId, fn($q) => $q->where('stores.id', $storeId))
            ->orderByDesc('expenses.expense_date')
            ->take(1000);

        $expenses = $query->get()->map(function ($row) {
            return (object) [
                'expense_date' => $row->expense_date,
                'expense_type' => $this->cleanUtf8($row->expense_type),
                'amount' => (float) $row->amount,
                'description' => $this->cleanUtf8($row->description ?? 'No description'),
                'reference_number' => $this->cleanUtf8($row->reference_number ?? 'N/A'),
                'user_name' => $this->cleanUtf8($row->user_name),
                'linked_item_name' => $this->cleanUtf8($row->linked_item_name),
                'store_name' => $this->cleanUtf8($row->store_name),
            ];
        });

        \Log::info("Expenses records found: " . $expenses->count());

        return response()->json(['data' => $expenses]);
    }

    public function downloadExpensesReport(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'expense_type' => 'nullable|string',
            'store_id' => 'nullable|exists:stores,id',
        ]);

        $startDate = $validated['start_date'] ?? now()->subMonth()->format('Y-m-d');
        $endDate = $validated['end_date'] ?? now()->format('Y-m-d');
        $expenseType = $validated['expense_type'] ?? null;
        $storeId = $validated['store_id'] ?? null;

        ini_set('memory_limit', '256M');

        $response = $this->previewExpensesReport($request);
        $expenses = $response->getData()->data;

        $companyDetails = Company::first();
        if ($companyDetails) {
            $companyDetails->company_name = $this->cleanUtf8($companyDetails->company_name);
            $companyDetails->address = $this->cleanUtf8($companyDetails->address);
            $companyDetails->email = $this->cleanUtf8($companyDetails->email);
            $companyDetails->phone = $this->cleanUtf8($companyDetails->phone);
        }

        if (empty($expenses)) {
            \Log::warning("No expenses data found for period: $startDate to $endDate");
            return back()->with('error', 'No expenses data found for the selected period.');
        }

        try {
            $pdf = Pdf::loadView('reports.finance_reports.expenses_pdf', [
                'expenses' => $expenses,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'companyDetails' => $companyDetails,
            ])->setOptions([
                'isHtml5ParserEnabled' => true,
                'defaultFont' => 'dejavusans',
                'isRemoteEnabled' => true,
                'chroot' => public_path(),
                'dpi' => 96,
                'defaultPaperSize' => 'a4',
            ]);

            \Log::info("Generating PDF for expenses report: $startDate to $endDate");
            return $pdf->download("expenses-report-$startDate-to-$endDate.pdf");
        } catch (\Exception $e) {
            \Log::error('PDF generation failed: ' . $e->getMessage());
            return back()->with('error', 'PDF generation failed: ' . $e->getMessage());
        }
    }

    public function previewDailyFinancialReport(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $validated['start_date'] ?? now()->subMonth()->format('Y-m-d');
        $endDate = $validated['end_date'] ?? now()->format('Y-m-d');

        \Log::info("Querying daily financial report", ['start_date' => $startDate, 'end_date' => $endDate]);

        $datesQuery = DB::table('orders')
            ->selectRaw('DATE(created_at) as date')
            ->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])
            ->union(
                DB::table('purchase_orders')
                    ->selectRaw('DATE(order_date) as date')
                    ->whereBetween(DB::raw('DATE(order_date)'), [$startDate, $endDate])
            )
            ->union(
                DB::table('expenses')
                    ->selectRaw('DATE(expense_date) as date')
                    ->whereBetween(DB::raw('DATE(expense_date)'), [$startDate, $endDate])
            )
            ->distinct()
            ->orderByDesc('date')
            ->take(1000);

        $dates = $datesQuery->pluck('date');

        $financials = collect();
        foreach ($dates as $date) {
            $totalOrders = Order::whereDate('created_at', $date)
                ->where('status', 'completed')
                ->sum('ground_total') ?? 0.0;

            $totalPurchases = PurchaseOrder::whereDate('order_date', $date)
                ->whereIn('status', ['Received', 'Paid'])
                ->sum('total_amount') ?? 0.0;

            $totalExpenses = Expense::whereDate('expense_date', $date)
                ->sum('amount') ?? 0.0;

            $afterExpenses = $totalOrders - $totalPurchases - $totalExpenses;

            $financials->push((object) [
                'date' => $date,
                'total_orders' => (float) $totalOrders,
                'total_purchases' => (float) $totalPurchases,
                'total_expenses' => (float) $totalExpenses,
                'after_expenses' => (float) $afterExpenses,
            ]);
        }

        \Log::info("Daily financial records found: " . $financials->count());

        return response()->json(['data' => $financials]);
    }

    public function downloadDailyFinancialReport(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $validated['start_date'] ?? now()->subMonth()->format('Y-m-d');
        $endDate = $validated['end_date'] ?? now()->format('Y-m-d');

        ini_set('memory_limit', '256M');

        $response = $this->previewDailyFinancialReport($request);
        $financials = $response->getData()->data;

        $companyDetails = Company::first();
        if ($companyDetails) {
            $companyDetails->company_name = $this->cleanUtf8($companyDetails->company_name);
            $companyDetails->address = $this->cleanUtf8($companyDetails->address);
            $companyDetails->email = $this->cleanUtf8($companyDetails->email);
            $companyDetails->phone = $this->cleanUtf8($companyDetails->phone);
        }

        if (empty($financials)) {
            \Log::warning("No daily financial data found for period: $startDate to $endDate");
            return back()->with('error', 'No daily financial data found for the selected period.');
        }

        try {
            $pdf = Pdf::loadView('reports.finance_reports.daily_financial_pdf', [
                'financials' => $financials,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'companyDetails' => $companyDetails,
            ])->setOptions([
                'isHtml5ParserEnabled' => true,
                'defaultFont' => 'dejavusans',
                'isRemoteEnabled' => true,
                'chroot' => public_path(),
                'dpi' => 96,
                'defaultPaperSize' => 'a4',
            ]);

            \Log::info("Generating PDF for daily financial report: $startDate to $endDate");
            return $pdf->download("daily-financial-report-$startDate-to-$endDate.pdf");
        } catch (\Exception $e) {
            \Log::error('PDF generation failed: ' . $e->getMessage());
            return back()->with('error', 'PDF generation failed: ' . $e->getMessage());
        }
    }

    public function previewBusinessHealthReport(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'nullable|exists:stores,id',
            'expense_type' => 'nullable|string',
        ]);

        $storeId = $validated['store_id'] ?? null;
        $expenseType = $validated['expense_type'] ?? 'shop';

        \Log::info("Querying business health report", ['store_id' => $storeId, 'expense_type' => $expenseType]);

        $today = now()->format('Y-m-d');
        $weekStart = now()->startOfWeek()->format('Y-m-d');
        $monthStart = now()->startOfMonth()->format('Y-m-d');
        $yearStart = now()->startOfYear()->format('Y-m-d');

        $periods = [
            'Day' => [$today, $today],
            'Week' => [$weekStart, $today],
            'Month' => [$monthStart, $today],
            'Year' => [$yearStart, $today],
        ];

        $healthData = [];
        foreach ($periods as $periodName => [$startDate, $endDate]) {
            \Log::info("Calculating data for period: $periodName ($startDate - $endDate)");

            // Total Sales
            $totalSales = Order::whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])
                ->whereIn('status', ['completed', 'settled'])
                ->when($storeId, fn($q) => $q->where('store_id', $storeId))
                ->sum('ground_total') ?? 0.0;

            // Total Expenses
            $totalExpenses = Expense::whereBetween(DB::raw('DATE(expense_date)'), [$startDate, $endDate])
                ->whereRaw('LOWER(expense_type) = ?', [strtolower($expenseType)])
                ->when($storeId, fn($q) => $q->where('store_id', $storeId))
                ->sum('amount') ?? 0.0;

            // Total Purchases
            $totalPurchases = PurchaseOrder::whereBetween(DB::raw('DATE(order_date)'), [$startDate, $endDate])
                ->whereIn('status', ['Received', 'Paid'])
                ->when($storeId, fn($q) => $q->where('store_id', $storeId))
                ->sum('total_amount') ?? 0.0;

            // Damage Stock Loss
            $damageItems = DamageStock::whereBetween(DB::raw('DATE(damage_date)'), [$startDate, $endDate])
                ->when($storeId, fn($q) => $q->where('store_id', $storeId))
                ->select(
                    'item_id',
                    'quantity',
                    DB::raw('(SELECT amount FROM item_costs WHERE item_id = damage_stocks.item_id AND store_id = damage_stocks.store_id ORDER BY id DESC LIMIT 1) as cost_price')
                )
                ->get();

            $totalDamageLoss = $damageItems->reduce(function ($carry, $item) use ($periodName) {
                if ($item->cost_price && $item->quantity && $item->cost_price >= 0 && $item->quantity >= 0) {
                    $loss = $item->cost_price * $item->quantity;
                    \Log::debug("[DAMAGE] Period: $periodName, Item ID: {$item->item_id}, Quantity: {$item->quantity}, Cost: {$item->cost_price}, Loss: $loss");
                    return $carry + $loss;
                }
                \Log::warning("[DAMAGE] Period: $periodName, No cost price or invalid data for Item ID: {$item->item_id}");
                return $carry;
            }, 0.0);

            // Profit Calculation
            $orderItems = OrderItem::join('orders', 'order_items.order_id', '=', 'orders.id')
                ->whereBetween(DB::raw('DATE(orders.created_at)'), [$startDate, $endDate])
                ->whereIn('orders.status', ['completed', 'settled'])
                ->when($storeId, fn($q) => $q->where('orders.store_id', $storeId))
                ->select(
                    'order_items.item_id',
                    'order_items.order_id',
                    'order_items.quantity',
                    'order_items.price as selling_price',
                    DB::raw('(SELECT amount FROM item_costs WHERE item_id = order_items.item_id AND store_id = orders.store_id ORDER BY id DESC LIMIT 1) as cost_price')
                )
                ->get();

            $totalProfit = $orderItems->reduce(function ($carry, $item) use ($periodName) {
                if ($item->selling_price && $item->cost_price && $item->selling_price >= 0 && $item->cost_price >= 0) {
                    $profit = ($item->selling_price - $item->cost_price) * $item->quantity;
                    \Log::debug("[PROFIT] Period: $periodName, Item ID: {$item->item_id}, Order: {$item->order_id}, Qty: {$item->quantity}, Sell: {$item->selling_price}, Cost: {$item->cost_price}, Profit: $profit");
                    return $carry + $profit;
                }
                \Log::warning("[PROFIT] Period: $periodName, No cost data for Item ID: {$item->item_id} (Order ID: {$item->order_id})");
                return $carry;
            }, 0.0);

            // Current Balance
            $currentBalance = $totalSales - $totalExpenses;

            $healthData[$periodName] = (object) [
                'total_sales' => round((float) $totalSales, 2),
                'total_expenses' => round((float) $totalExpenses, 2),
                'purchases' => round((float) $totalPurchases, 2),
                'damage_loss' => round((float) $totalDamageLoss, 2),
                'profit' => round((float) $totalProfit, 2),
                'current_balance' => round((float) $currentBalance, 2),
            ];
        }

        \Log::info("Business health periods processed: " . count($healthData));

        return response()->json(['data' => $healthData]);
    }

    public function downloadBusinessHealthReport(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'nullable|exists:stores,id',
            'expense_type' => 'nullable|string',
        ]);

        $storeId = $validated['store_id'] ?? null;
        $expenseType = $validated['expense_type'] ?? 'shop';

        ini_set('memory_limit', '256M');

        $response = $this->previewBusinessHealthReport($request);
        $healthData = $response->getData()->data;

        $companyDetails = Company::first();
        if ($companyDetails) {
            $companyDetails->company_name = $this->cleanUtf8($companyDetails->company_name);
            $companyDetails->address = $this->cleanUtf8($companyDetails->address);
            $companyDetails->email = $this->cleanUtf8($companyDetails->email);
            $companyDetails->phone = $this->cleanUtf8($companyDetails->phone);
        }

        if (empty($healthData)) {
            \Log::warning("No business health data found.");
            return back()->with('error', 'No business health data found.');
        }

        try {
            $pdf = Pdf::loadView('reports.finance_reports.business_health_pdf', [
                'healthData' => $healthData,
                'companyDetails' => $companyDetails,
            ])->setOptions([
                'isHtml5ParserEnabled' => true,
                'defaultFont' => 'dejavusans',
                'isRemoteEnabled' => true,
                'chroot' => public_path(),
                'dpi' => 96,
                'defaultPaperSize' => 'a4',
            ]);

            \Log::info("Generating PDF for business health report");
            return $pdf->download("business-health-report.pdf");
        } catch (\Exception $e) {
            \Log::error('PDF generation failed: ' . $e->getMessage());
            return back()->with('error', 'PDF generation failed: ' . $e->getMessage());
        }
    }
}
