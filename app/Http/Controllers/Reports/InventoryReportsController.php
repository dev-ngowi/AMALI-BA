<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\Stock;
use App\Models\Store;
use App\Models\GoodReceiveNote;
use App\Models\GoodReceiveNoteItem;
use App\Models\DamageStock;
use App\Models\Company;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class InventoryReportsController extends Controller
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

    public function previewStockLedgerReport(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'store_id' => 'nullable|exists:stores,id',
            'item_id' => 'nullable|exists:items,id',
        ]);

        $startDate = $validated['start_date'] ?? now()->subMonth()->format('Y-m-d');
        $endDate = $validated['end_date'] ?? now()->format('Y-m-d');
        $storeId = $validated['store_id'] ?? null;
        $itemId = $validated['item_id'] ?? null;

        \Log::info("Querying stock ledger for period: $startDate to $endDate, store_id: " . ($storeId ?? 'null'), ['item_id' => $itemId]);

        $query = Item::select('id', 'name');
        if ($itemId) {
            $query->where('id', $itemId);
        }
        $items = $query->take(1000)->get(); // Limit items

        $stockLedgerData = [];
        foreach ($items as $item) {
            // Calculate opening balance before start_date
            $inflowQuery = GoodReceiveNoteItem::join('good_receipt_notes', 'good_receive_note_items.grn_id', '=', 'good_receipt_notes.id')
                ->where('good_receive_note_items.item_id', $item->id)
                ->whereDate('good_receipt_notes.received_date', '<', $startDate);
            if ($storeId) {
                $inflowQuery->where('good_receipt_notes.store_id', $storeId);
            }
            $totalInflow = $inflowQuery->sum('accepted_quantity');

            $outflowQuery = \App\Models\OrderItem::join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('order_items.item_id', $item->id)
                ->whereDate('orders.created_at', '<', $startDate)
                ->where('orders.status', 'completed');
            if ($storeId) {
                $outflowQuery->where('orders.store_id', $storeId);
            }
            $totalOutflow = $outflowQuery->sum('quantity');

            $openingBalance = $totalInflow - $totalOutflow;

            // Fetch movements within date range
            $inflowMovements = GoodReceiveNoteItem::join('good_receipt_notes', 'good_receive_note_items.grn_id', '=', 'good_receipt_notes.id')
                ->where('good_receive_note_items.item_id', $item->id)
                ->whereBetween('good_receipt_notes.received_date', [$startDate, $endDate])
                ->when($storeId, fn($q) => $q->where('good_receipt_notes.store_id', $storeId))
                ->select(
                    'good_receipt_notes.received_date as movement_date',
                    DB::raw("'receipt' as movement_type"),
                    'good_receive_note_items.accepted_quantity as quantity',
                    'good_receipt_notes.grn_number as reference'
                )
                ->take(500) // Limit movements per item
                ->get();

            $outflowMovements = \App\Models\OrderItem::join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('order_items.item_id', $item->id)
                ->whereBetween('orders.created_at', [$startDate, $endDate])
                ->where('orders.status', 'completed')
                ->when($storeId, fn($q) => $q->where('orders.store_id', $storeId))
                ->select(
                    'orders.created_at as movement_date',
                    DB::raw("'sale' as movement_type"),
                    'order_items.quantity as quantity',
                    'orders.order_number as reference'
                )
                ->take(500) // Limit movements per item
                ->get();

            $allMovements = $inflowMovements->merge($outflowMovements)
                ->map(function ($movement) {
                    $movement->quantity = $movement->movement_type === 'sale' ? -$movement->quantity : $movement->quantity;
                    return $movement;
                })
                ->sortBy('movement_date')
                ->take(1000); // Overall limit

            $currentBalance = $openingBalance;
            $ledgerEntries = [];
            $ledgerEntries[] = (object) [
                'item_id' => $item->id,
                'item_name' => $this->cleanUtf8($item->name),
                'date' => $startDate,
                'reference' => 'Opening Balance',
                'inflow' => 0,
                'outflow' => 0,
                'balance' => $currentBalance,
            ];

            foreach ($allMovements as $movement) {
                $currentBalance += $movement->quantity;
                $ledgerEntries[] = (object) [
                    'item_id' => $item->id,
                    'item_name' => $this->cleanUtf8($item->name),
                    'date' => $movement->movement_date,
                    'reference' => $this->cleanUtf8($movement->reference),
                    'inflow' => $movement->quantity > 0 ? $movement->quantity : 0,
                    'outflow' => $movement->quantity < 0 ? -$movement->quantity : 0,
                    'balance' => $currentBalance,
                ];
            }

            $stockLedgerData = array_merge($stockLedgerData, $ledgerEntries);
        }

        \Log::info("Stock ledger records found: " . count($stockLedgerData));

        return response()->json(['data' => $stockLedgerData]);
    }

    public function downloadStockLedgerReport(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'store_id' => 'nullable|exists:stores,id',
            'item_id' => 'nullable|exists:items,id',
        ]);

        $startDate = $validated['start_date'] ?? now()->subMonth()->format('Y-m-d');
        $endDate = $validated['end_date'] ?? now()->format('Y-m-d');
        $storeId = $validated['store_id'] ?? null;
        $itemId = $validated['item_id'] ?? null;

        ini_set('memory_limit', '256M');

        $response = $this->previewStockLedgerReport($request);
        $stockLedgerData = collect($response->getData()->data);

        $companyDetails = Company::first();
        if ($companyDetails) {
            $companyDetails->company_name = $this->cleanUtf8($companyDetails->company_name);
            $companyDetails->address = $this->cleanUtf8($companyDetails->address);
            $companyDetails->email = $this->cleanUtf8($companyDetails->email);
            $companyDetails->phone = $this->cleanUtf8($companyDetails->phone);
        }

        if ($stockLedgerData->isEmpty()) {
            \Log::warning("No stock ledger data found for period: $startDate to $endDate");
            return back()->with('error', 'No stock ledger data found for the selected period.');
        }

        try {
            $pdf = Pdf::loadView('reports.inventory_reports.stock_ledger_pdf', [
                'stockLedgerData' => $stockLedgerData,
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

            \Log::info("Generating PDF for stock ledger: $startDate to $endDate");
            return $pdf->download("stock-ledger-$startDate-to-$endDate.pdf");
        } catch (\Exception $e) {
            \Log::error('PDF generation failed: ' . $e->getMessage());
            return back()->with('error', 'PDF generation failed: ' . $e->getMessage());
        }
    }

    public function previewStockLevelReport(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'nullable|exists:stores,id',
            'item_id' => 'nullable|exists:items,id',
        ]);

        $storeId = $validated['store_id'] ?? null;
        $itemId = $validated['item_id'] ?? null;

        \Log::info("Querying stock level report", ['store_id' => $storeId, 'item_id' => $itemId]);

        $query = ItemStock::join('items', 'item_stocks.item_id', '=', 'items.id')
            ->join('stocks', 'item_stocks.stock_id', '=', 'stocks.id')
            ->join('stores', 'stocks.store_id', '=', 'stores.id')
            ->select(
                'items.id as item_id',
                'items.name as item_name',
                'stores.id as store_id',
                'stores.name as store_name',
                'item_stocks.stock_quantity',
                'stocks.min_quantity',
                'stocks.max_quantity'
            )
            ->when($storeId, fn($q) => $q->where('stores.id', $storeId))
            ->when($itemId, fn($q) => $q->where('items.id', $itemId))
            ->orderBy('items.name')
            ->orderBy('stores.name')
            ->take(1000);

        $stockLevels = $query->get()->map(function ($row) {
            $stockQuantity = $row->stock_quantity ?? 0;
            $minQuantity = $row->min_quantity ?? 0;
            $maxQuantity = $row->max_quantity ?? 0;

            $status = $stockQuantity < $minQuantity ? 'Low' : ($stockQuantity > $maxQuantity ? 'High' : 'Normal');
            $statusColor = $stockQuantity < $minQuantity ? 'red' : ($stockQuantity > $maxQuantity ? 'green' : 'yellow');

            return (object) [
                'item_id' => $row->item_id,
                'item_name' => $this->cleanUtf8($row->item_name),
                'store_id' => $row->store_id,
                'store_name' => $this->cleanUtf8($row->store_name),
                'stock_quantity' => $stockQuantity,
                'min_quantity' => $minQuantity,
                'max_quantity' => $maxQuantity,
                'status' => $status,
                'status_color' => $statusColor,
            ];
        });

        \Log::info("Stock level records found: " . $stockLevels->count());

        return response()->json(['data' => $stockLevels]);
    }

    public function downloadStockLevelReport(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'nullable|exists:stores,id',
            'item_id' => 'nullable|exists:items,id',
        ]);

        ini_set('memory_limit', '256M');

        $response = $this->previewStockLevelReport($request);
        $stockLevels = $response->getData()->data;

        $companyDetails = Company::first();
        if ($companyDetails) {
            $companyDetails->company_name = $this->cleanUtf8($companyDetails->company_name);
            $companyDetails->address = $this->cleanUtf8($companyDetails->address);
            $companyDetails->email = $this->cleanUtf8($companyDetails->email);
            $companyDetails->phone = $this->cleanUtf8($companyDetails->phone);
        }

        if (empty($stockLevels)) {
            \Log::warning("No stock level data found.");
            return back()->with('error', 'No stock level data found.');
        }

        try {
            $pdf = Pdf::loadView('reports.inventory_reports.stock_level_pdf', [
                'stockLevels' => $stockLevels,
                'companyDetails' => $companyDetails,
            ])->setOptions([
                'isHtml5ParserEnabled' => true,
                'defaultFont' => 'dejavusans',
                'isRemoteEnabled' => true,
                'chroot' => public_path(),
                'dpi' => 96,
                'defaultPaperSize' => 'a4',
            ]);

            \Log::info("Generating PDF for stock level report");
            return $pdf->download("stock-level-report.pdf");
        } catch (\Exception $e) {
            \Log::error('PDF generation failed: ' . $e->getMessage());
            return back()->with('error', 'PDF generation failed: ' . $e->getMessage());
        }
    }

    public function previewStockValueReport(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'nullable|exists:stores,id',
            'item_id' => 'nullable|exists:items,id',
        ]);

        $storeId = $validated['store_id'] ?? null;
        $itemId = $validated['item_id'] ?? null;

        \Log::info("Querying stock value report", ['store_id' => $storeId, 'item_id' => $itemId]);

        $query = ItemStock::join('items', 'item_stocks.item_id', '=', 'items.id')
            ->join('stocks', 'item_stocks.stock_id', '=', 'stocks.id')
            ->join('stores', 'stocks.store_id', '=', 'stores.id')
            ->leftJoin('item_costs', function ($join) {
                $join->on('item_costs.item_id', '=', 'items.id')
                     ->on('item_costs.store_id', '=', 'stores.id')
                     ->whereRaw('item_costs.id = (SELECT MAX(id) FROM item_costs WHERE item_id = items.id AND store_id = stores.id)');
            })
            ->leftJoin('item_prices', function ($join) {
                $join->on('item_prices.item_id', '=', 'items.id')
                     ->on('item_prices.store_id', '=', 'stores.id')
                     ->whereRaw('item_prices.id = (SELECT MAX(id) FROM item_prices WHERE item_id = items.id AND store_id = stores.id)');
            })
            ->where('item_stocks.stock_quantity', '>', 0)
            ->when($storeId, fn($q) => $q->where('stores.id', $storeId))
            ->when($itemId, fn($q) => $q->where('items.id', $itemId))
            ->select(
                'items.id as item_id',
                'items.name as item_name',
                'stores.name as store_name',
                'stores.id as store_id',
                'item_stocks.stock_quantity',
                'item_costs.amount as cost_amount',
                'item_prices.amount as price_amount'
            )
            ->groupBy('items.id', 'items.name', 'stores.id', 'stores.name', 'item_stocks.stock_quantity', 'item_costs.amount', 'item_prices.amount')
            ->orderBy('items.name')
            ->orderBy('stores.name')
            ->take(1000);

        $stockValues = $query->get()->map(function ($row) {
            $stockQuantity = $row->stock_quantity ?? 0;
            $costAmount = $row->cost_amount ?? 0;
            $priceAmount = $row->price_amount ?? 0;
            $stockCostValue = $stockQuantity * $costAmount;
            $saleValue = $stockQuantity * $priceAmount;

            return (object) [
                'item_id' => $row->item_id,
                'item_name' => $this->cleanUtf8($row->item_name),
                'store_name' => $this->cleanUtf8($row->store_name),
                'store_id' => $row->store_id,
                'stock_quantity' => $stockQuantity,
                'stock_cost_value' => $stockCostValue,
                'sale_value' => $saleValue,
                'potential_profit' => $saleValue - $stockCostValue,
            ];
        });

        $totalStockCost = $stockValues->sum('stock_cost_value');
        $totalSaleValue = $stockValues->sum('sale_value');

        \Log::info("Stock value records found: " . $stockValues->count());

        return response()->json([
            'data' => $stockValues,
            'totals' => (object) [
                'total_stock_cost' => $totalStockCost,
                'total_sale_value' => $totalSaleValue,
                'potential_profit' => $totalSaleValue - $totalStockCost,
            ],
        ]);
    }

    public function downloadStockValueReport(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'nullable|exists:stores,id',
            'item_id' => 'nullable|exists:items,id',
        ]);

        ini_set('memory_limit', '256M');

        $response = $this->previewStockValueReport($request);
        $stockValues = $response->getData()->data;
        $totals = $response->getData()->totals;

        $companyDetails = Company::first();
        if ($companyDetails) {
            $companyDetails->company_name = $this->cleanUtf8($companyDetails->company_name);
            $companyDetails->address = $this->cleanUtf8($companyDetails->address);
            $companyDetails->email = $this->cleanUtf8($companyDetails->email);
            $companyDetails->phone = $this->cleanUtf8($companyDetails->phone);
        }

        if (empty($stockValues)) {
            \Log::warning("No stock value data found.");
            return back()->with('error', 'No stock value data found.');
        }

        try {
            $pdf = Pdf::loadView('reports.inventory_reports.stock_value_pdf', [
                'stockValues' => $stockValues,
                'totals' => $totals,
                'companyDetails' => $companyDetails,
            ])->setOptions([
                'isHtml5ParserEnabled' => true,
                'defaultFont' => 'dejavusans',
                'isRemoteEnabled' => true,
                'chroot' => public_path(),
                'dpi' => 96,
                'defaultPaperSize' => 'a4',
            ]);

            \Log::info("Generating PDF for stock value report");
            return $pdf->download("stock-value-report.pdf");
        } catch (\Exception $e) {
            \Log::error('PDF generation failed: ' . $e->getMessage());
            return back()->with('error', 'PDF generation failed: ' . $e->getMessage());
        }
    }

    public function previewDamageStockReport(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'store_id' => 'nullable|exists:stores,id',
        ]);

        $startDate = $validated['start_date'] ?? now()->subMonth()->format('Y-m-d');
        $endDate = $validated['end_date'] ?? now()->format('Y-m-d');
        $storeId = $validated['store_id'] ?? null;
        $daysThreshold = 90;

        $cutoffDate = now()->subDays($daysThreshold)->format('Y-m-d');

        \Log::info("Querying damage stock for period: $startDate to $endDate, store_id: " . ($storeId ?? 'null'));

        $damageStocks = DamageStock::join('items', 'damage_stocks.item_id', '=', 'items.id')
            ->join('stores', 'damage_stocks.store_id', '=', 'stores.id')
            ->leftJoin('order_items', 'order_items.item_id', '=', 'items.id')
            ->leftJoin('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('damage_stocks.quantity', '>', 0)
            ->whereBetween('damage_stocks.damage_date', [$startDate, $endDate])
            ->whereNull('damage_stocks.deleted_at')
            ->when($storeId, fn($q) => $q->where('damage_stocks.store_id', $storeId))
            ->groupBy('items.id', 'items.name', 'stores.id', 'stores.name', 'damage_stocks.quantity', 'damage_stocks.damage_date')
            ->select(
                'items.id as item_id',
                'items.name as item_name',
                'stores.id as store_id',
                'stores.name as store_name',
                'damage_stocks.quantity',
                DB::raw('MAX(orders.created_at) as last_sale_date')
            )
            ->havingRaw('MAX(orders.created_at) IS NULL OR MAX(orders.created_at) < ?', [$cutoffDate])
            ->orderBy('items.name')
            ->orderBy('stores.name')
            ->take(1000)
            ->get()
            ->map(function ($row) {
                return (object) [
                    'item_id' => $row->item_id,
                    'item_name' => $this->cleanUtf8($row->item_name),
                    'store_id' => $row->store_id,
                    'store_name' => $this->cleanUtf8($row->store_name),
                    'quantity' => $row->quantity,
                    'last_sale_date' => $row->last_sale_date ? $row->last_sale_date : 'Never Sold',
                ];
            });

        \Log::info("Damage stock records found: " . $damageStocks->count());

        return response()->json(['data' => $damageStocks]);
    }

    public function downloadDamageStockReport(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'store_id' => 'nullable|exists:stores,id',
        ]);

        $startDate = $validated['start_date'] ?? now()->subMonth()->format('Y-m-d');
        $endDate = $validated['end_date'] ?? now()->format('Y-m-d');
        $storeId = $validated['store_id'] ?? null;

        ini_set('memory_limit', '256M');

        $response = $this->previewDamageStockReport($request);
        $damageStocks = $response->getData()->data;

        $companyDetails = Company::first();
        if ($companyDetails) {
            $companyDetails->company_name = $this->cleanUtf8($companyDetails->company_name);
            $companyDetails->address = $this->cleanUtf8($companyDetails->address);
            $companyDetails->email = $this->cleanUtf8($companyDetails->email);
            $companyDetails->phone = $this->cleanUtf8($companyDetails->phone);
        }

        if (empty($damageStocks)) {
            \Log::warning("No damage stock data found for period: $startDate to $endDate");
            return back()->with('error', 'No damage stock data found for the selected period.');
        }

        try {
            $pdf = Pdf::loadView('reports.inventory_reports.damage_stock_pdf', [
                'damageStocks' => $damageStocks,
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

            \Log::info("Generating PDF for damage stock report: $startDate to $endDate");
            return $pdf->download("damage-stock-$startDate-to-$endDate.pdf");
        } catch (\Exception $e) {
            \Log::error('PDF generation failed: ' . $e->getMessage());
            return back()->with('error', 'PDF generation failed: ' . $e->getMessage());
        }
    }
}
