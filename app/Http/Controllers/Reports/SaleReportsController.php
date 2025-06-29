<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Company;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

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

            return $pdf->download("sales-summary-{$startDate}-to-{$endDate}.pdf");
        } catch (\Exception $e) {
            return back()->with('error', 'PDF generation failed: ' . $e->getMessage());
        }
    }

    // Stubs for other reports
    public function previewSaleDetailedData(Request $request)
    {
        // Implement as needed
    }

    public function downloadSaleDetailedData()
    {
        // Implement as needed
    }

    public function previewPaymentSummaryData(Request $request)
    {
        // Implement as needed
    }

    public function downloadPaymentSummaryData()
    {
        // Implement as needed
    }

    public function previewTopSellingItems(Request $request)
    {
        // Implement as needed
    }

    public function downloadTopSellingItems()
    {
        // Implement as needed
    }
}
