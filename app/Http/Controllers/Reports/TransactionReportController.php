<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\GoodReceiptNote;
use App\Models\GoodReceiptNoteItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentType;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class TransactionReportController extends Controller
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

    public function previewPurchaseOrderSummary(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'store_id' => 'nullable|exists:stores,id',
        ]);

        $startDate = $validated['start_date'] ?? now()->subMonth()->format('Y-m-d');
        $endDate = $validated['end_date'] ?? now()->format('Y-m-d');
        $storeId = $validated['store_id'] ?? null;

        \Log::info("Querying purchase order summary report", [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'store_id' => $storeId
        ]);

        $query = PurchaseOrder::select(
            DB::raw('DATE(order_date) as date'),
            'status',
            DB::raw('COUNT(*) as order_count'),
            DB::raw('COALESCE(SUM(total_amount), 0) as total_amount')
        )
            ->whereBetween(DB::raw('DATE(order_date)'), [$startDate, $endDate])
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->groupBy(DB::raw('DATE(order_date)'), 'status')
            ->orderBy('date')
            ->take(1000);

        $summary = $query->get()->map(function ($row) {
            return (object) [
                'date' => $row->date,
                'status' => $this->cleanUtf8($row->status),
                'order_count' => (int) $row->order_count,
                'total_amount' => (float) $row->total_amount,
            ];
        });

        \Log::info("Purchase order summary records found: " . $summary->count());

        return response()->json(['data' => $summary]);
    }

    public function downloadPurchaseOrderSummary(Request $request)
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

        $response = $this->previewPurchaseOrderSummary($request);
        $summary = $response->getData()->data;

        $companyDetails = Company::first();
        if ($companyDetails) {
            $companyDetails->company_name = $this->cleanUtf8($companyDetails->company_name);
            $companyDetails->address = $this->cleanUtf8($companyDetails->address);
            $companyDetails->email = $this->cleanUtf8($companyDetails->email);
            $companyDetails->phone = $this->cleanUtf8($companyDetails->phone);
        }

        if (empty($summary)) {
            \Log::warning("No purchase order summary data found for period: $startDate to $endDate");
            return back()->with('error', 'No purchase order summary data found for the selected period.');
        }

        try {
            $pdf = Pdf::loadView('reports.transaction_reports.purchase_order_summary_pdf', [
                'summary' => $summary,
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

            \Log::info("Generating PDF for purchase order summary report: $startDate to $endDate");
            return $pdf->download("purchase-order-summary-$startDate-to-$endDate.pdf");
        } catch (\Exception $e) {
            \Log::error('PDF generation failed: ' . $e->getMessage());
            return back()->with('error', 'PDF generation failed: ' . $e->getMessage());
        }
    }

    public function previewPurchaseOrderDetailed(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'store_id' => 'nullable|exists:stores,id',
        ]);

        $startDate = $validated['start_date'] ?? now()->subMonth()->format('Y-m-d');
        $endDate = $validated['end_date'] ?? now()->format('Y-m-d');
        $storeId = $validated['store_id'] ?? null;

        \Log::info("Querying purchase order detailed report", [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'store_id' => $storeId
        ]);

        $query = PurchaseOrder::with(['items.item', 'items.unit', 'supplier', 'store'])
            ->whereBetween(DB::raw('DATE(order_date)'), [$startDate, $endDate])
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->orderBy('order_date')
            ->take(1000);

        $orders = $query->get()->flatMap(function ($order) {
            return $order->items->map(function ($item) use ($order) {
                return (object) [
                    'order_number' => $this->cleanUtf8($order->order_number),
                    'order_date' => $order->order_date->format('Y-m-d'),
                    'status' => $this->cleanUtf8($order->status),
                    'supplier_name' => $this->cleanUtf8($order->supplier->name ?? 'N/A'),
                    'store_name' => $this->cleanUtf8($order->store->name ?? 'N/A'),
                    'item_name' => $this->cleanUtf8($item->item->name ?? 'N/A'),
                    'quantity' => (float) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'total_price' => (float) $item->total_price,
                    'unit' => $this->cleanUtf8($item->unit->name ?? 'N/A'),
                ];
            });
        });

        \Log::info("Purchase order detailed records found: " . $orders->count());

        return response()->json(['data' => $orders]);
    }

    public function downloadPurchaseOrderDetailed(Request $request)
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

        $response = $this->previewPurchaseOrderDetailed($request);
        $orders = $response->getData()->data;

        $companyDetails = Company::first();
        if ($companyDetails) {
            $companyDetails->company_name = $this->cleanUtf8($companyDetails->company_name);
            $companyDetails->address = $this->cleanUtf8($companyDetails->address);
            $companyDetails->email = $this->cleanUtf8($companyDetails->email);
            $companyDetails->phone = $this->cleanUtf8($companyDetails->phone);
        }

        if (empty($orders)) {
            \Log::warning("No purchase order detailed data found for period: $startDate to $endDate");
            return back()->with('error', 'No purchase order detailed data found for the selected period.');
        }

        try {
            $pdf = Pdf::loadView('reports.transaction_reports.purchase_order_detailed_pdf', [
                'orders' => $orders,
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

            \Log::info("Generating PDF for purchase order detailed report: $startDate to $endDate");
            return $pdf->download("purchase-order-detailed-$startDate-to-$endDate.pdf");
        } catch (\Exception $e) {
            \Log::error('PDF generation failed: ' . $e->getMessage());
            return back()->with('error', 'PDF generation failed: ' . $e->getMessage());
        }
    }

    public function previewGoodReceiptNote(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'store_id' => 'nullable|exists:stores,id',
        ]);

        $startDate = $validated['start_date'] ?? now()->subMonth()->format('Y-m-d');
        $endDate = $validated['end_date'] ?? now()->format('Y-m-d');
        $storeId = $validated['store_id'] ?? null;

        \Log::info("Querying good receipt note report", [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'store_id' => $storeId
        ]);

        $query = GoodReceiptNote::with(['items.item', 'items.unit', 'purchaseOrder', 'store'])
            ->whereBetween(DB::raw('DATE(received_date)'), [$startDate, $endDate])
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->orderBy('received_date')
            ->take(1000);

        $grns = $query->get()->flatMap(function ($grn) {
            return $grn->items->map(function ($item) use ($grn) {
                return (object) [
                    'grn_number' => $this->cleanUtf8($grn->grn_number),
                    'received_date' => $grn->received_date->format('Y-m-d'),
                    'purchase_order_number' => $this->cleanUtf8($grn->purchaseOrder->order_number ?? 'N/A'),
                    'store_name' => $this->cleanUtf8($grn->store->name ?? 'N/A'),
                    'item_name' => $this->cleanUtf8($item->item->name ?? 'N/A'),
                    'accepted_quantity' => (float) $item->accepted_quantity,
                    'unit' => $this->cleanUtf8($item->unit->name ?? 'N/A'),
                ];
            });
        });

        \Log::info("Good receipt note records found: " . $grns->count());

        return response()->json(['data' => $grns]);
    }

    public function downloadGoodReceiptNote(Request $request)
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

        $response = $this->previewGoodReceiptNote($request);
        $grns = $response->getData()->data;

        $companyDetails = Company::first();
        if ($companyDetails) {
            $companyDetails->company_name = $this->cleanUtf8($companyDetails->company_name);
            $companyDetails->address = $this->cleanUtf8($companyDetails->address);
            $companyDetails->email = $this->cleanUtf8($companyDetails->email);
            $companyDetails->phone = $this->cleanUtf8($companyDetails->phone);
        }

        if (empty($grns)) {
            \Log::warning("No good receipt note data found for period: $startDate to $endDate");
            return back()->with('error', 'No good receipt note data found for the selected period.');
        }

        try {
            $pdf = Pdf::loadView('reports.transaction_reports.good_receipt_note_pdf', [
                'grns' => $grns,
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

            \Log::info("Generating PDF for good receipt note report: $startDate to $endDate");
            return $pdf->download("good-receipt-note-$startDate-to-$endDate.pdf");
        } catch (\Exception $e) {
            \Log::error('PDF generation failed: ' . $e->getMessage());
            return back()->with('error', 'PDF generation failed: ' . $e->getMessage());
        }
    }

    public function previewGoodReceiptNoteSummary(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'store_id' => 'nullable|exists:stores,id',
        ]);

        $startDate = $validated['start_date'] ?? now()->subMonth()->format('Y-m-d');
        $endDate = $validated['end_date'] ?? now()->format('Y-m-d');
        $storeId = $validated['store_id'] ?? null;

        \Log::info("Querying good receipt note summary report", [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'store_id' => $storeId
        ]);

        $query = GoodReceiptNote::join('good_receipt_note_items', 'good_receipt_notes.id', '=', 'good_receipt_note_items.grn_id')
            ->join('items', 'good_receipt_note_items.item_id', '=', 'items.id')
            ->join('stores', 'good_receipt_notes.store_id', '=', 'stores.id')
            ->select(
                DB::raw('DATE(good_receipt_notes.received_date) as date'),
                'stores.name as store_name',
                DB::raw('COUNT(DISTINCT good_receipt_notes.id) as grn_count'),
                DB::raw('SUM(good_receipt_note_items.accepted_quantity) as total_quantity')
            )
            ->whereBetween(DB::raw('DATE(good_receipt_notes.received_date)'), [$startDate, $endDate])
            ->when($storeId, fn($q) => $q->where('good_receipt_notes.store_id', $storeId))
            ->groupBy(DB::raw('DATE(good_receipt_notes.received_date)'), 'stores.name')
            ->orderBy('date')
            ->take(1000);

        $summary = $query->get()->map(function ($row) {
            return (object) [
                'date' => $row->date,
                'store_name' => $this->cleanUtf8($row->store_name),
                'grn_count' => (int) $row->grn_count,
                'total_quantity' => (float) $row->total_quantity,
            ];
        });

        \Log::info("Good receipt note summary records found: " . $summary->count());

        return response()->json(['data' => $summary]);
    }

    public function downloadGoodReceiptNoteSummary(Request $request)
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

        $response = $this->previewGoodReceiptNoteSummary($request);
        $summary = $response->getData()->data;

        $companyDetails = Company::first();
        if ($companyDetails) {
            $companyDetails->company_name = $this->cleanUtf8($companyDetails->company_name);
            $companyDetails->address = $this->cleanUtf8($companyDetails->address);
            $companyDetails->email = $this->cleanUtf8($companyDetails->email);
            $companyDetails->phone = $this->cleanUtf8($companyDetails->phone);
        }

        if (empty($summary)) {
            \Log::warning("No good receipt note summary data found for period: $startDate to $endDate");
            return back()->with('error', 'No good receipt note summary data found for the selected period.');
        }

        try {
            $pdf = Pdf::loadView('reports.transaction_reports.good_receipt_note_summary_pdf', [
                'summary' => $summary,
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

            \Log::info("Generating PDF for good receipt note summary report: $startDate to $endDate");
            return $pdf->download("good-receipt-note-summary-$startDate-to-$endDate.pdf");
        } catch (\Exception $e) {
            \Log::error('PDF generation failed: ' . $e->getMessage());
            return back()->with('error', 'PDF generation failed: ' . $e->getMessage());
        }
    }

    public function previewPaymentSummary(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'store_id' => 'nullable|exists:stores,id',
        ]);

        $startDate = $validated['start_date'] ?? now()->subMonth()->format('Y-m-d');
        $endDate = $validated['end_date'] ?? now()->format('Y-m-d');
        $storeId = $validated['store_id'] ?? null;

        \Log::info("Querying payment summary report", [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'store_id' => $storeId
        ]);

        $query = Payment::join('order_payments', 'payments.id', '=', 'order_payments.payment_id')
            ->join('orders', 'order_payments.order_id', '=', 'orders.id')
            ->leftJoin('payment_types', 'payments.payment_type_id', '=', 'payment_types.id')
            ->select(
                DB::raw('DATE(orders.created_at) as payment_date'),
                DB::raw('COALESCE(payments.short_code, "N/A") as short_code'),
                DB::raw('COALESCE(payment_types.name, "Unknown") as payment_type'),
                DB::raw('COALESCE(payments.payment_method, "Unknown") as payment_method'),
                DB::raw('SUM(orders.ground_total) as amount')
            )
            ->whereBetween(DB::raw('DATE(orders.created_at)'), [$startDate, $endDate])
            ->where('orders.status', 'completed')
            ->when($storeId, fn($q) => $q->where('orders.store_id', $storeId))
            ->groupBy(DB::raw('DATE(orders.created_at)'), 'payments.short_code', 'payment_types.name', 'payments.payment_method')
            ->orderBy('payment_date')
            ->take(1000);

        $payments = $query->get()->map(function ($row) {
            return (object) [
                'payment_date' => $row->payment_date,
                'short_code' => $this->cleanUtf8($row->short_code),
                'payment_type' => $this->cleanUtf8($row->payment_type),
                'payment_method' => $this->cleanUtf8($row->payment_method),
                'amount' => (float) $row->amount,
            ];
        });

        \Log::info("Payment summary records found: " . $payments->count());

        return response()->json(['data' => $payments]);
    }

    public function downloadPaymentSummary(Request $request)
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

        $response = $this->previewPaymentSummary($request);
        $payments = $response->getData()->data;

        $companyDetails = Company::first();
        if ($companyDetails) {
            $companyDetails->company_name = $this->cleanUtf8($companyDetails->company_name);
            $companyDetails->address = $this->cleanUtf8($companyDetails->address);
            $companyDetails->email = $this->cleanUtf8($companyDetails->email);
            $companyDetails->phone = $this->cleanUtf8($companyDetails->phone);
        }

        if (empty($payments)) {
            \Log::warning("No payment summary data found for period: $startDate to $endDate");
            return back()->with('error', 'No payment summary data found for the selected period.');
        }

        try {
            $pdf = Pdf::loadView('reports.transaction_reports.payment_summary_pdf', [
                'payments' => $payments,
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

            \Log::info("Generating PDF for payment summary report: $startDate to $endDate");
            return $pdf->download("payment-summary-$startDate-to-$endDate.pdf");
        } catch (\Exception $e) {
            \Log::error('PDF generation failed: ' . $e->getMessage());
            return back()->with('error', 'PDF generation failed: ' . $e->getMessage());
        }
    }

    public function previewOrderItemReport(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'store_id' => 'nullable|exists:stores,id',
        ]);

        $startDate = $validated['start_date'] ?? now()->subMonth()->format('Y-m-d');
        $endDate = $validated['end_date'] ?? now()->format('Y-m-d');
        $storeId = $validated['store_id'] ?? null;

        \Log::info("Querying order item report", [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'store_id' => $storeId
        ]);

        $query = Order::join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->join('items', 'order_items.item_id', '=', 'items.id')
            ->select(
                'orders.order_number',
                'orders.created_at as date',
                'orders.discount',
                'orders.ground_total',
                'items.name as item_name',
                'order_items.quantity',
                'order_items.price',
                DB::raw('order_items.quantity * order_items.price as total')
            )
            ->whereBetween(DB::raw('DATE(orders.created_at)'), [$startDate, $endDate])
            ->where('orders.status', 'completed')
            ->when($storeId, fn($q) => $q->where('orders.store_id', $storeId))
            ->orderBy('orders.created_at')
            ->orderBy('orders.order_number')
            ->take(1000);

        $orders = collect();
        $rows = $query->get();
        $ordersDict = [];
        foreach ($rows as $row) {
            $orderNumber = $row->order_number;
            if (!isset($ordersDict[$orderNumber])) {
                $ordersDict[$orderNumber] = (object) [
                    'order_number' => $this->cleanUtf8($row->order_number),
                    'date' => \Carbon\Carbon::parse($row->date)->format('Y-m-d'),
                    'discount' => (float) ($row->discount ?? 0),
                    'ground_total' => (float) ($row->ground_total ?? 0),
                    'items' => [],
                ];
            }
            if ($row->item_name) {
                $ordersDict[$orderNumber]->items[] = (object) [
                    'item_name' => $this->cleanUtf8($row->item_name),
                    'quantity' => (int) $row->quantity,
                    'price' => (float) $row->price,
                    'total' => (float) $row->total,
                ];
            }
        }
        $orders = collect(array_values($ordersDict));

        \Log::info("Order item records found: " . $orders->count());

        return response()->json(['data' => $orders]);
    }

    public function downloadOrderItemReport(Request $request)
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

        $response = $this->previewOrderItemReport($request);
        $orders = $response->getData()->data;

        $companyDetails = Company::first();
        if ($companyDetails) {
            $companyDetails->company_name = $this->cleanUtf8($companyDetails->company_name);
            $companyDetails->address = $this->cleanUtf8($companyDetails->address);
            $companyDetails->email = $this->cleanUtf8($companyDetails->email);
            $companyDetails->phone = $this->cleanUtf8($companyDetails->phone);
        }

        if (empty($orders)) {
            \Log::warning("No order item data found for period: $startDate to $endDate");
            return back()->with('error', 'No order item data found for the selected period.');
        }

        try {
            $pdf = Pdf::loadView('reports.transaction_reports.order_item_pdf', [
                'orders' => $orders,
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

            \Log::info("Generating PDF for order item report: $startDate to $endDate");
            return $pdf->download("order-item-report-$startDate-to-$endDate.pdf");
        } catch (\Exception $e) {
            \Log::error('PDF generation failed: ' . $e->getMessage());
            return back()->with('error', 'PDF generation failed: ' . $e->getMessage());
        }
    }
}
