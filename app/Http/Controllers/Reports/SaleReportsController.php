<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Company;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class SaleReportsController extends Controller
{
    protected function cleanUtf8($value)
    {
        if (is_string($value)) {
            // Remove non-printable ASCII (except common whitespace)
            $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);
            
            // Convert invalid UTF-8 sequences
            if (!mb_check_encoding($value, 'UTF-8')) {
                $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
            }
        }
        return $value;
    }

    public function previewSalesSummaryData(Request $request)
    {
        // Validate input
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $validated['start_date'] ?? now()->subMonth()->format('Y-m-d');
        $endDate = $validated['end_date'] ?? now()->format('Y-m-d');

        $salesData = Order::whereDate('created_at', '>=', $startDate)
                          ->whereDate('created_at', '<=', $endDate)
                          ->get()
                          ->map(function ($order) {
                              $order->order_number = $this->cleanUtf8($order->order_number);
                              $order->status = $this->cleanUtf8($order->status);
                              return $order;
                          });

        return response()->json([
            'data' => $salesData
        ]);
    }

    public function downloadSalesSummaryData(Request $request)
    {
        // Validate input
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $validated['start_date'] ?? now()->subMonth()->format('Y-m-d');
        $endDate = $validated['end_date'] ?? now()->format('Y-m-d');

        $salesData = Order::whereDate('created_at', '>=', $startDate)
                          ->whereDate('created_at', '<=', $endDate)
                          ->get()
                          ->map(function ($order) {
                              $order->order_number = $this->cleanUtf8($order->order_number);
                              $order->status = $this->cleanUtf8($order->status);
                              return $order;
                          });

        $companyDetails = Company::first();
        if ($companyDetails) {
            $companyDetails->company_name = $this->cleanUtf8($companyDetails->company_name);
            $companyDetails->address = $this->cleanUtf8($companyDetails->address);
            $companyDetails->email = $this->cleanUtf8($companyDetails->email);
            $companyDetails->phone = $this->cleanUtf8($companyDetails->phone);
        }

        $totalAmount = $salesData->sum('ground_total');

        if ($salesData->isEmpty()) {
            \Log::warning('No sales data found for period: ' . $startDate . ' to ' . $endDate);
            return back()->with('error', 'No sales data found for the selected period.');
        }

        try {
            $pdf = Pdf::loadView('reports.sales_reports.sales_summary_pdf', [
                'salesData' => $salesData,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'companyDetails' => $companyDetails,
                'totalAmount' => $totalAmount,
            ])->setOptions([
                'isHtml5ParserEnabled' => true,
                'defaultFont' => 'dejavusans',
                'isRemoteEnabled' => true,
                'chroot' => public_path(),
            ]);

            \Log::info('Generating PDF for sales summary: ' . $startDate . ' to ' . $endDate);
            return $pdf->download("sales-summary-{$startDate}-to-{$endDate}.pdf");
        } catch (\Exception $e) {
            \Log::error('PDF generation failed: ' . $e->getMessage());
            return back()->with('error', 'PDF generation failed: ' . $e->getMessage());
        }
    }

    public function previewSaleDetailedData(Request $request)
    {
        // Validate input
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $validated['start_date'] ?? now()->subMonth()->format('Y-m-d');
        $endDate = $validated['end_date'] ?? now()->format('Y-m-d');

        $salesData = Order::with(['orderItems.item'])
                          ->whereDate('created_at', '>=', $startDate)
                          ->whereDate('created_at', '<=', $nDate)
                          ->get()
                          ->map(function ($order) {
                              $order->order_number = $this->cleanUtf8($order->order_number);
                              $order->status = $this->cleanUtf8($order->status);
                              $order->orderItems->each(function ($item) {
                                  $item->item_name = $this->cleanUtf8($item->item->name ?? '');
                              });
                              return $order;
                          });

        return response()->json([
            'data' => $salesData->map(function ($order) {
                return [
                    'order_number' => $order->order_number,
                    'created_at' => $order->created_at->format('Y-m-d'),
                    'ground_total' => $order->ground_total,
                    'status' => $order->status,
                    'items' => $order->orderItems->map(function ($item) {
                        return [
                            'item_name' => $item->item_name,
                            'quantity' => $item->quantity,
                            'price' => $item->price,
                            'total' => $item->quantity * $item->price,
                        ];
                    }),
                ];
            }),
        ]);
    }

    public function downloadSaleDetailedData(Request $request)
    {
        // Validate input
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $validated['start_date'] ?? now()->subMonth()->format('Y-m-d');
        $endDate = $validated['end_date'] ?? now()->format('Y-m-d');

        $salesData = Order::with(['orderItems.item'])
                          ->whereDate('created_at', '>=', $startDate)
                          ->whereDate('created_at', '<=', $endDate)
                          ->get()
                          ->map(function ($order) {
                              $order->order_number = $this->cleanUtf8($order->order_number);
                              $order->status = $this->cleanUtf8($order->status);
                              $order->orderItems->each(function ($item) {
                                  $item->item_name = $this->cleanUtf8($item->item->name ?? '');
                              });
                              return $order;
                          });

        $companyDetails = Company::first();
        if ($companyDetails) {
            $companyDetails->company_name = $this->cleanUtf8($companyDetails->company_name);
            $companyDetails->address = $this->cleanUtf8($companyDetails->address);
            $companyDetails->email = $this->cleanUtf8($companyDetails->email);
            $companyDetails->phone = $this->cleanUtf8($companyDetails->phone);
        }

        $totalAmount = $salesData->sum('ground_total');

        if ($salesData->isEmpty()) {
            \Log::warning('No sales data found for period: ' . $startDate . ' to ' . $endDate);
            return back()->with('error', 'No sales data found for the selected period.');
        }

        try {
            $pdf = Pdf::loadView('reports.sales_reports.sales_detailed_pdf', [
                'salesData' => $salesData,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'companyDetails' => $companyDetails,
                'totalAmount' => $totalAmount,
            ])->setOptions([
                'isHtml5ParserEnabled' => true,
                'defaultFont' => 'dejavusans',
                'isRemoteEnabled' => true,
                'chroot' => public_path(),
            ]);

            \Log::info('Generating PDF for detailed sales report: ' . $startDate . ' to ' . $endDate);
            return $pdf->download("sales-detailed-{$startDate}-to-{$endDate}.pdf");
        } catch (\Exception $e) {
            \Log::error('PDF generation failed: ' . $e->getMessage());
            return back()->with('error', 'PDF generation failed: ' . $e->getMessage());
        }
    }

    public function previewPaymentSummaryData(Request $request)
    {
        // Validate input
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $validated['start_date'] ?? now()->subMonth()->format('Y-m-d');
        $endDate = $validated['end_date'] ?? now()->format('Y-m-d');

        $paymentData = Order::with(['orderPayments.payment.paymentType'])
                           ->whereDate('created_at', '>=', $startDate)
                           ->whereDate('created_at', '<=', $endDate)
                           ->get()
                           ->map(function ($order) {
                               $order->order_number = $this->cleanUtf8($order->order_number);
                               $order->status = $this->cleanUtf8($order->status);
                               $order->orderPayments->each(function ($orderPayment) {
                                   $orderPayment->payment_method = $this->cleanUtf8($orderPayment->payment->payment_method ?? '');
                                   $orderPayment->payment_type = $this->cleanUtf8($orderPayment->payment->paymentType->name ?? '');
                               });
                               return $order;
                           });

        return response()->json([
            'data' => $paymentData->map(function ($order) {
                return [
                    'order_number' => $order->order_number,
                    'created_at' => $order->created_at->format('Y-m-d'),
                    'ground_total' => $order->ground_total,
                    'status' => $order->status,
                    'payments' => $order->orderPayments->map(function ($orderPayment) {
                        return [
                            'short_code' => $orderPayment->payment->short_code,
                            'payment_method' => $orderPayment->payment_method,
                            'payment_type' => $orderPayment->payment_type,
                        ];
                    }),
                ];
            }),
            'total_amount' => $paymentData->sum('ground_total'),
        ]);
    }

    public function downloadPaymentSummaryData(Request $request)
    {
        // Validate input
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $validated['start_date'] ?? now()->subMonth()->format('Y-m-d');
        $endDate = $validated['end_date'] ?? now()->format('Y-m-d');

        $paymentData = Order::with(['orderPayments.payment.paymentType'])
                           ->whereDate('created_at', '>=', $startDate)
                           ->whereDate('created_at', '<=', $endDate)
                           ->get()
                           ->map(function ($order) {
                               $order->order_number = $this->cleanUtf8($order->order_number);
                               $order->status = $this->cleanUtf8($order->status);
                               $order->orderPayments->each(function ($orderPayment) {
                                   $orderPayment->payment_method = $this->cleanUtf8($orderPayment->payment->payment_method ?? '');
                                   $orderPayment->payment_type = $this->cleanUtf8($orderPayment->payment->paymentType->name ?? '');
                               });
                               return $order;
                           });

        $companyDetails = Company::first();
        if ($companyDetails) {
            $companyDetails->company_name = $this->cleanUtf8($companyDetails->company_name);
            $companyDetails->address = $this->cleanUtf8($companyDetails->address);
            $companyDetails->email = $this->cleanUtf8($companyDetails->email);
            $companyDetails->phone = $this->cleanUtf8($companyDetails->phone);
        }

        $totalAmount = $paymentData->sum('ground_total');

        if ($paymentData->isEmpty()) {
            \Log::warning('No payment data found for period: ' . $startDate . ' to ' . $endDate);
            return back()->with('error', 'No payment data found for the selected period.');
        }

        try {
            $pdf = Pdf::loadView('reports.sales_reports.payment_summary_pdf', [
                'paymentData' => $paymentData,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'companyDetails' => $companyDetails,
                'totalAmount' => $totalAmount,
            ])->setOptions([
                'isHtml5ParserEnabled' => true,
                'defaultFont' => 'dejavusans',
                'isRemoteEnabled' => true,
                'chroot' => public_path(),
            ]);

            \Log::info('Generating PDF for payment summary: ' . $startDate . ' to ' . $endDate);
            return $pdf->download("payment-summary-{$startDate}-to-{$endDate}.pdf");
        } catch (\Exception $e) {
            \Log::error('PDF generation failed: ' . $e->getMessage());
            return back()->with('error', 'PDF generation failed: ' . $e->getMessage());
        }
    }

    public function previewTopSellingItems(Request $request)
    {
        // Validate input
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $validated['start_date'] ?? now()->subMonth()->format('Y-m-d');
        $endDate = $validated['end_date'] ?? now()->format('Y-m-d');

        $topItems = OrderItem::join('orders', 'order_items.order_id', '=', 'orders.id')
                             ->join('items', 'order_items.item_id', '=', 'items.id')
                             ->whereDate('orders.created_at', '>=', $startDate)
                             ->whereDate('orders.created_at', '<=', $endDate)
                             ->groupBy('order_items.item_id', 'items.name')
                             ->select(
                                 'items.name as item_name',
                                 DB::raw('SUM(order_items.quantity) as total_quantity'),
                                 DB::raw('SUM(order_items.quantity * order_items.price) as total_revenue')
                             )
                             ->orderBy('total_quantity', 'desc')
                             ->take(10)
                             ->get()
                             ->map(function ($item) {
                                 $item->item_name = $this->cleanUtf8($item->item_name);
                                 return $item;
                             });

        return response()->json([
            'data' => $topItems->map(function ($item) {
                return [
                    'item_name' => $item->item_name,
                    'total_quantity' => $item->total_quantity,
                    'total_revenue' => $item->total_revenue,
                ];
            }),
            'total_revenue' => $topItems->sum('total_revenue'),
        ]);
    }

    public function downloadTopSellingItems(Request $request)
    {
        // Validate input
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $validated['start_date'] ?? now()->subMonth()->format('Y-m-d');
        $endDate = $validated['end_date'] ?? now()->format('Y-m-d');

        $topItems = OrderItem::join('orders', 'order_items.order_id', '=', 'orders.id')
                             ->join('items', 'order_items.item_id', '=', 'items.id')
                             ->whereDate('orders.created_at', '>=', $startDate)
                             ->whereDate('orders.created_at', '<=', $endDate)
                             ->groupBy('order_items.item_id', 'items.name')
                             ->select(
                                 'items.name as item_name',
                                 DB::raw('SUM(order_items.quantity) as total_quantity'),
                                 DB::raw('SUM(order_items.quantity * order_items.price) as total_revenue')
                             )
                             ->orderBy('total_quantity', 'desc')
                             ->take(10)
                             ->get()
                             ->map(function ($item) {
                                 $item->item_name = $this->cleanUtf8($item->item_name);
                                 return $item;
                             });

        $companyDetails = Company::first();
        if ($companyDetails) {
            $companyDetails->company_name = $this->cleanUtf8($companyDetails->company_name);
            $companyDetails->address = $this->cleanUtf8($companyDetails->address);
            $companyDetails->email = $this->cleanUtf8($companyDetails->email);
            $companyDetails->phone = $this->cleanUtf8($companyDetails->phone);
        }

        $totalRevenue = $topItems->sum('total_revenue');

        if ($topItems->isEmpty()) {
            \Log::warning('No top-selling items found for period: ' . $startDate . ' to ' . $endDate);
            return back()->with('error', 'No top-selling items found for the selected period.');
        }

        try {
            $pdf = Pdf::loadView('reports.sales_reports.top_selling_items_pdf', [
                'topItems' => $topItems,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'companyDetails' => $companyDetails,
                'totalRevenue' => $totalRevenue,
            ])->setOptions([
                'isHtml5ParserEnabled' => true,
                'defaultFont' => 'dejavusans',
                'isRemoteEnabled' => true,
                'chroot' => public_path(),
            ]);

            \Log::info('Generating PDF for top-selling items: ' . $startDate . ' to ' . $endDate);
            return $pdf->download("top-selling-items-{$startDate}-to-{$endDate}.pdf");
        } catch (\Exception $e) {
            \Log::error('PDF generation failed: ' . $e->getMessage());
            return back()->with('error', 'PDF generation failed: ' . $e->getMessage());
        }
    }
}