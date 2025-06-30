<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Company;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Mpdf\Mpdf;

class SaleReportsController extends Controller
{
    protected function cleanUtf8($value)
    {
        // Convert to string, handling null or non-string values
        $value = is_null($value) ? '' : (string) $value;

        // Remove non-printable ASCII characters (except common whitespace)
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);

        // Check and fix UTF-8 encoding
        if (!mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'auto');
        }

        // Use iconv to remove any remaining invalid characters
        $value = iconv('UTF-8', 'UTF-8//IGNORE', $value);

        // Replace any remaining invalid sequences with a placeholder
        $value = str_replace("\xEF\xBF\xBD", '?', $value);

        return $value;
    }

    public function previewSalesSummaryData(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $this->cleanUtf8($validated['start_date'] ?? now()->subMonth()->format('Y-m-d'));
        $endDate = $this->cleanUtf8($validated['end_date'] ?? now()->format('Y-m-d'));

        $salesData = Order::whereDate('created_at', '>=', $startDate)
                          ->whereDate('created_at', '<=', $endDate)
                          ->get()
                          ->map(function ($order) {
                              \Log::debug('Raw Order Data (Preview):', $order->toArray());
                              $order->order_number = $this->cleanUtf8($order->order_number ?? '');
                              $order->status = $this->cleanUtf8($order->status ?? '');
                              $order->ground_total = $this->cleanUtf8($order->ground_total ?? 0);
                              $order->created_at = $this->cleanUtf8($order->created_at ? $order->created_at->format('Y-m-d') : '');
                              return $order;
                          });

        return response()->json([
            'data' => $salesData
        ]);
    }

   public function downloadSalesSummaryData(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);
    
        // Set default dates if not provided
        $startDate = $this->sanitizeString($validated['start_date'] ?? now()->subMonth()->format('Y-m-d'));
        $endDate = $this->sanitizeString($validated['end_date'] ?? now()->format('Y-m-d'));
    
        // Fetch sales data
        $salesData = Order::whereDate('created_at', '>=', $startDate)
                          ->whereDate('created_at', '<=', $endDate)
                          ->get()
                          ->map(function ($order) {
                              \Log::debug('Raw Order Data (Download):', $order->toArray());
                              return [
                                  'order_number' => $this->sanitizeString($order->order_number ?? ''),
                                  'status' => $this->sanitizeString($order->status ?? ''),
                                  'ground_total' => (float) ($order->ground_total ?? 0),
                                  'created_at' => $this->sanitizeString($order->created_at ? $order->created_at->format('Y-m-d') : ''),
                              ];
                          });
    
        // Fetch company details
        $companyDetails = Company::first();
        $companyDetails = $companyDetails ? [
            'company_name' => $this->sanitizeString($companyDetails->company_name ?? ''),
            'address' => $this->sanitizeString($companyDetails->address ?? ''),
            'email' => $this->sanitizeString($companyDetails->email ?? ''),
            'phone' => $this->sanitizeString($companyDetails->phone ?? ''),
        ] : null;
    
        $totalAmount = number_format($salesData->sum('ground_total'), 2);
    
        if ($salesData->isEmpty()) {
            \Log::warning('No sales data found for period: ' . $startDate . ' to ' . $endDate);
            return response()->json(['error' => 'No sales data found for the selected period.'], 404);
        }
    
        try {
            \Log::debug('Cleaned Sales Data:', $salesData->toArray());
            \Log::debug('Cleaned Company Details:', $companyDetails ?? []);
            \Log::debug('Start Date:', [$startDate]);
            \Log::debug('End Date:', [$endDate]);
            \Log::debug('Total Amount:', [$totalAmount]);
    
            // Initialize mPDF with UTF-8 settings
            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'default_font' => 'dejavusans',
                'tempDir' => sys_get_temp_dir(),
            ]);
    
            // Load the Blade view
            $html = view('reports.sales_reports.sales_summary_pdf', [
                'salesData' => $salesData,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'companyDetails' => $companyDetails,
                'totalAmount' => $totalAmount,
            ])->render();
    
            // Write HTML to PDF
            $mpdf->WriteHTML($html);
    
            \Log::info('Generating PDF for sales summary: ' . $startDate . ' to ' . $endDate);
            return response($mpdf->Output("sales-summary-{$startDate}-to-{$endDate}.pdf", 'D'), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "attachment; filename=sales-summary-{$startDate}-to-{$endDate}.pdf",
            ]);
        } catch (\Exception $e) {
            \Log::error('PDF generation failed: ' . $e->getMessage());
            return response()->json(['error' => 'PDF generation failed: ' . $e->getMessage()], 500);
        }
    }
    
    // Enhanced sanitization function
    protected function sanitizeString($value)
    {
        // Convert to string, handling null or non-string values
        $value = is_null($value) ? '' : (string) $value;
    
        // Normalize to UTF-8
        if (!mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'auto');
        }
    
        // Remove control characters, keeping common whitespace
        $value = preg_replace('/[\x00-\x1F\x7F-\x9F]/u', '', $value);
    
        // Ensure valid UTF-8 by encoding and decoding
        $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    
        // Replace any remaining invalid characters with a placeholder
        $value = str_replace("\xEF\xBF\xBD", '?', $value);
    
        return trim($value);
    }

    public function previewSaleDetailedData(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $this->cleanUtf8($validated['start_date'] ?? now()->subMonth()->format('Y-m-d'));
        $endDate = $this->cleanUtf8($validated['end_date'] ?? now()->format('Y-m-d'));

        $salesData = Order::with(['orderItems.item'])
                          ->whereDate('created_at', '>=', $startDate)
                          ->whereDate('created_at', '<=', $endDate)
                          ->get()
                          ->map(function ($order) {
                              \Log::debug('Raw Order Data (Detailed Preview):', $order->toArray());
                              $order->order_number = $this->cleanUtf8($order->order_number ?? '');
                              $order->status = $this->cleanUtf8($order->status ?? '');
                              $order->ground_total = $this->cleanUtf8($order->ground_total ?? 0);
                              $order->created_at = $this->cleanUtf8($order->created_at ? $order->created_at->format('Y-m-d') : '');
                              $order->orderItems->each(function ($item) {
                                  $item->item_name = $this->cleanUtf8($item->item->name ?? '');
                                  $item->quantity = $this->cleanUtf8($item->quantity ?? 0);
                                  $item->price = $this->cleanUtf8($item->price ?? 0);
                              });
                              return $order;
                          });

        return response()->json([
            'data' => $salesData->map(function ($order) {
                return [
                    'order_number' => $order->order_number,
                    'created_at' => $order->created_at,
                    'ground_total' => $order->ground_total,
                    'status' => $order->status,
                    'items' => $order->orderItems->map(function ($item) {
                        return [
                            'item_name' => $item->item_name,
                            'quantity' => $item->quantity,
                            'price' => $item->price,
                            'total' => $this->cleanUtf8($item->quantity * $item->price),
                        ];
                    }),
                ];
            }),
        ]);
    }

    public function downloadSaleDetailedData(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $this->cleanUtf8($validated['start_date'] ?? now()->subMonth()->format('Y-m-d'));
        $endDate = $this->cleanUtf8($validated['end_date'] ?? now()->format('Y-m-d'));

        $salesData = Order::with(['orderItems.item'])
                          ->whereDate('created_at', '>=', $startDate)
                          ->whereDate('created_at', '<=', $endDate)
                          ->get()
                          ->map(function ($order) {
                              \Log::debug('Raw Order Data (Detailed Download):', $order->toArray());
                              $order->order_number = $this->cleanUtf8($order->order_number ?? '');
                              $order->status = $this->cleanUtf8($order->status ?? '');
                              $order->ground_total = $this->cleanUtf8($order->ground_total ?? 0);
                              $order->created_at = $this->cleanUtf8($order->created_at ? $order->created_at->format('Y-m-d') : '');
                              $order->orderItems->each(function ($item) {
                                  $item->item_name = $this->cleanUtf8($item->item->name ?? '');
                                  $item->quantity = $this->cleanUtf8($item->quantity ?? 0);
                                  $item->price = $this->cleanUtf8($item->price ?? 0);
                              });
                              return $order;
                          });

        $companyDetails = Company::first();
        if ($companyDetails) {
            \Log::debug('Raw Company Data:', $companyDetails->toArray());
            $companyDetails->company_name = $this->cleanUtf8($companyDetails->company_name ?? '');
            $companyDetails->address = $this->cleanUtf8($companyDetails->address ?? '');
            $companyDetails->email = $this->cleanUtf8($companyDetails->email ?? '');
            $companyDetails->phone = $this->cleanUtf8($companyDetails->phone ?? '');
        }

        $totalAmount = $this->cleanUtf8($salesData->sum('ground_total'));

        if ($salesData->isEmpty()) {
            \Log::warning('No sales data found for period: ' . $startDate . ' to ' . $endDate);
            return response()->json(['error' => 'No sales data found for the selected period.'], 404);
        }

        try {
            \Log::debug('Cleaned Sales Data:', $salesData->toArray());
            \Log::debug('Cleaned Company Details:', $companyDetails ? $companyDetails->toArray() : []);
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
                'encoding' => 'UTF-8',
            ]);

            \Log::info('Generating PDF for detailed sales report: ' . $startDate . ' to ' . $endDate);
            return $pdf->download("sales-detailed-{$startDate}-to-{$endDate}.pdf");
        } catch (\Exception $e) {
            \Log::error('PDF generation failed: ' . $e->getMessage());
            return response()->json(['error' => 'PDF generation failed: ' . $e->getMessage()], 500);
        }
    }

    public function previewPaymentSummaryData(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $this->cleanUtf8($validated['start_date'] ?? now()->subMonth()->format('Y-m-d'));
        $endDate = $this->cleanUtf8($validated['end_date'] ?? now()->format('Y-m-d'));

        $paymentData = Order::with(['orderPayments.payment.paymentType'])
                           ->whereDate('created_at', '>=', $startDate)
                           ->whereDate('created_at', '<=', $endDate)
                           ->get()
                           ->map(function ($order) {
                               \Log::debug('Raw Order Data (Payment Preview):', $order->toArray());
                               $order->order_number = $this->cleanUtf8($order->order_number ?? '');
                               $order->status = $this->cleanUtf8($order->status ?? '');
                               $order->ground_total = $this->cleanUtf8($order->ground_total ?? 0);
                               $order->created_at = $this->cleanUtf8($order->created_at ? $order->created_at->format('Y-m-d') : '');
                               $order->orderPayments->each(function ($orderPayment) {
                                   $orderPayment->payment_method = $this->cleanUtf8($orderPayment->payment->payment_method ?? '');
                                   $orderPayment->payment_type = $this->cleanUtf8($orderPayment->payment->paymentType->name ?? '');
                                   $orderPayment->short_code = $this->cleanUtf8($orderPayment->payment->short_code ?? '');
                               });
                               return $order;
                           });

        return response()->json([
            'data' => $paymentData->map(function ($order) {
                return [
                    'order_number' => $order->order_number,
                    'created_at' => $order->created_at,
                    'ground_total' => $order->ground_total,
                    'status' => $order->status,
                    'payments' => $order->orderPayments->map(function ($orderPayment) {
                        return [
                            'short_code' => $orderPayment->short_code,
                            'payment_method' => $orderPayment->payment_method,
                            'payment_type' => $orderPayment->payment_type,
                        ];
                    }),
                ];
            }),
            'total_amount' => $this->cleanUtf8($paymentData->sum('ground_total')),
        ]);
    }

    public function downloadPaymentSummaryData(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $this->cleanUtf8($validated['start_date'] ?? now()->subMonth()->format('Y-m-d'));
        $endDate = $this->cleanUtf8($validated['end_date'] ?? now()->format('Y-m-d'));

        $paymentData = Order::with(['orderPayments.payment.paymentType'])
                           ->whereDate('created_at', '>=', $startDate)
                           ->whereDate('created_at', '<=', $endDate)
                           ->get()
                           ->map(function ($order) {
                               \Log::debug('Raw Order Data (Payment Download):', $order->toArray());
                               $order->order_number = $this->cleanUtf8($order->order_number ?? '');
                               $order->status = $this->cleanUtf8($order->status ?? '');
                               $order->ground_total = $this->cleanUtf8($order->ground_total ?? 0);
                               $order->created_at = $this->cleanUtf8($order->created_at ? $order->created_at->format('Y-m-d') : '');
                               $order->orderPayments->each(function ($orderPayment) {
                                   $orderPayment->payment_method = $this->cleanUtf8($orderPayment->payment->payment_method ?? '');
                                   $orderPayment->payment_type = $this->cleanUtf8($orderPayment->payment->paymentType->name ?? '');
                                   $orderPayment->short_code = $this->cleanUtf8($orderPayment->payment->short_code ?? '');
                               });
                               return $order;
                           });

        $companyDetails = Company::first();
        if ($companyDetails) {
            \Log::debug('Raw Company Data:', $companyDetails->toArray());
            $companyDetails->company_name = $this->cleanUtf8($companyDetails->company_name ?? '');
            $companyDetails->address = $this->cleanUtf8($companyDetails->address ?? '');
            $companyDetails->email = $this->cleanUtf8($companyDetails->email ?? '');
            $companyDetails->phone = $this->cleanUtf8($companyDetails->phone ?? '');
        }

        $totalAmount = $this->cleanUtf8($paymentData->sum('ground_total'));

        if ($paymentData->isEmpty()) {
            \Log::warning('No payment data found for period: ' . $startDate . ' to ' . $endDate);
            return response()->json(['error' => 'No payment data found for the selected period.'], 404);
        }

        try {
            \Log::debug('Cleaned Payment Data:', $paymentData->toArray());
            \Log::debug('Cleaned Company Details:', $companyDetails ? $companyDetails->toArray() : []);
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
                'encoding' => 'UTF-8',
            ]);

            \Log::info('Generating PDF for payment summary: ' . $startDate . ' to ' . $endDate);
            return $pdf->download("payment-summary-{$startDate}-to-{$endDate}.pdf");
        } catch (\Exception $e) {
            \Log::error('PDF generation failed: ' . $e->getMessage());
            return response()->json(['error' => 'PDF generation failed: ' . $e->getMessage()], 500);
        }
    }

    public function previewTopSellingItems(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $this->cleanUtf8($validated['start_date'] ?? now()->subMonth()->format('Y-m-d'));
        $endDate = $this->cleanUtf8($validated['end_date'] ?? now()->format('Y-m-d'));

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
                                 \Log::Debug('Raw Top Item Data:', $item->toArray());
                                 $item->item_name = $this->cleanUtf8($item->item_name ?? '');
                                 $item->total_quantity = $this->cleanUtf8($item->total_quantity ?? 0);
                                 $item->total_revenue = $this->cleanUtf8($item->total_revenue ?? 0);
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
            'total_revenue' => $this->cleanUtf8($topItems->sum('total_revenue')),
        ]);
    }

    public function downloadTopSellingItems(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $this->cleanUtf8($validated['start_date'] ?? now()->subMonth()->format('Y-m-d'));
        $endDate = $this->cleanUtf8($validated['end_date'] ?? now()->format('Y-m-d'));

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
                                 \Log::debug('Raw Top Item Data (Download):', $item->toArray());
                                 $item->item_name = $this->cleanUtf8($item->item_name ?? '');
                                 $item->total_quantity = $this->cleanUtf8($item->total_quantity ?? 0);
                                 $item->total_revenue = $this->cleanUtf8($item->total_revenue ?? 0);
                                 return $item;
                             });

        $companyDetails = Company::first();
        if ($companyDetails) {
            \Log::debug('Raw Company Data:', $companyDetails->toArray());
            $companyDetails->company_name = $this->cleanUtf8($companyDetails->company_name ?? '');
            $companyDetails->address = $this->cleanUtf8($companyDetails->address ?? '');
            $companyDetails->email = $this->cleanUtf8($companyDetails->email ?? '');
            $companyDetails->phone = $this->cleanUtf8($companyDetails->phone ?? '');
        }

        $totalRevenue = $this->cleanUtf8($topItems->sum('total_revenue'));

        if ($topItems->isEmpty()) {
            \Log::warning('No top-selling items found for period: ' . $startDate . ' to ' . $endDate);
            return response()->json(['error' => 'No top-selling items found for the selected period.'], 404);
        }

        try {
            \Log::debug('Cleaned Top Items Data:', $topItems->toArray());
            \Log::debug('Cleaned Company Details:', $companyDetails ? $companyDetails->toArray() : []);
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
                'encoding' => 'UTF-8',
            ]);

            \Log::info('Generating PDF for top-selling items: ' . $startDate . ' to ' . $endDate);
            return $pdf->download("top-selling-items-{$startDate}-to-{$endDate}.pdf");
        } catch (\Exception $e) {
            \Log::error('PDF generation failed: ' . $e->getMessage());
            return response()->json(['error' => 'PDF generation failed: ' . $e->getMessage()], 500);
        }
    }
}