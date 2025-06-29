<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ItemStock;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class InvoiceController extends Controller
{
    /**
     * Generate a unique invoice number.
     */
    private function generateInvoiceNumber()
    {
        $count = Invoice::count() + 1;
        return 'INV-' . Carbon::now()->format('Ymd') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Display a listing of the invoices with pagination.
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10); // Default to 10 items per page
        $invoices = Invoice::with(['order', 'supplier', 'customer', 'items'])
            ->paginate($perPage)
            ->through(function ($invoice) {
                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'order_id' => $invoice->order_id,
                    'customer_id' => $invoice->customer_id,
                    'supplier_id' => $invoice->supplier_id,
                    'total_amount' => $invoice->total_amount,
                    'tax_amount' => $invoice->tax_amount,
                    'discount' => $invoice->discount,
                    'grand_total' => $invoice->grand_total,
                    'status' => $invoice->status,
                    'issue_date' => $invoice->issue_date,
                    'due_date' => $invoice->due_date,
                    'customer_name' => $invoice->customer ? $invoice->customer->customer_name : ($invoice->supplier ? $invoice->supplier->name : null),
                    'customer_address' => $invoice->customer ? $invoice->customer->address : ($invoice->supplier ? $invoice->supplier->address : null),
                    'items' => $invoice->items->map(function ($item) {
                        return [
                            'description' => $item->item_description,
                            'quantity' => $item->quantity,
                            'rate' => $item->rate,
                            'amount' => $item->amount,
                            'item_id' => $item->item_id
                        ];
                    })
                ];
            });

        return response()->json([
            'data' => $invoices->items(),
            'pagination' => [
                'total' => $invoices->total(),
                'per_page' => $invoices->perPage(),
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'next_page_url' => $invoices->nextPageUrl(),
                'prev_page_url' => $invoices->previousPageUrl()
            ],
            'message' => 'success'
        ], 200);
    }

    /**
     * Display the specified invoice.
     */
    public function show($id)
    {
        $invoice = Invoice::with(['order', 'supplier', 'customer', 'items'])->find($id);
        if (!$invoice) {
            return response()->json([
                'message' => 'Invoice not found'
            ], 404);
        }

        $response = [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'order_id' => $invoice->order_id,
            'customer_id' => $invoice->customer_id,
            'supplier_id' => $invoice->supplier_id,
            'total_amount' => $invoice->total_amount,
            'tax_amount' => $invoice->tax_amount,
            'discount' => $invoice->discount,
            'grand_total' => $invoice->grand_total,
            'status' => $invoice->status,
            'issue_date' => $invoice->issue_date,
            'due_date' => $invoice->due_date,
            'customer_name' => $invoice->customer ? $invoice->customer->customer_name : ($invoice->supplier ? $invoice->supplier->name : null),
            'customer_address' => $invoice->customer ? $invoice->customer->address : ($invoice->supplier ? $invoice->supplier->address : null),
            'items' => $invoice->items->map(function ($item) {
                return [
                    'description' => $item->item_description,
                    'quantity' => $item->quantity,
                    'rate' => $item->rate,
                    'amount' => $item->amount,
                    'item_id' => $item->item_id
                ];
            })
        ];

        return response()->json([
            'data' => $response,
            'message' => 'success'
        ], 200);
    }

    /**
     * Store a newly created invoice in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'entity_id' => 'required|integer',
            'is_supplier' => 'required|boolean',
            'order_id' => 'nullable|exists:orders,id',
            'tax_rate' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.description' => 'required|string|max:255',
            'items.*.quantity' => 'required|numeric|min:0',
            'items.*.rate' => 'required|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        return DB::transaction(function () use ($request) {
            $total_amount = collect($request->items)->sum(function ($item) {
                return $item['quantity'] * $item['rate'];
            });
            $tax_amount = $total_amount * ($request->tax_rate ?? 0);
            $grand_total = $total_amount + $tax_amount - ($request->discount ?? 0);

            $invoiceData = [
                'invoice_number' => $this->generateInvoiceNumber(),
                'order_id' => $request->order_id,
                'total_amount' => $total_amount,
                'tax_amount' => $tax_amount,
                'discount' => $request->discount ?? 0,
                'grand_total' => $grand_total,
                'status' => 'draft',
                'issue_date' => Carbon::today()->toDateString(),
                'due_date' => Carbon::today()->toDateString()
            ];

            if ($request->is_supplier) {
                $invoiceData['supplier_id'] = $request->entity_id;
                Validator::make(['supplier_id' => $request->entity_id], [
                    'supplier_id' => 'exists:vendors,id'
                ])->validate();
            } else {
                $invoiceData['customer_id'] = $request->entity_id;
                Validator::make(['customer_id' => $request->entity_id], [
                    'customer_id' => 'exists:customers,id'
                ])->validate();
            }

            $invoice = Invoice::create($invoiceData);

            foreach ($request->items as $item) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'item_id' => $item['item_id'],
                    'item_description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'rate' => $item['rate'],
                    'amount' => $item['quantity'] * $item['rate']
                ]);
            }

            return response()->json([
                'data' => $invoice->load(['order', 'supplier', 'customer', 'items']),
                'message' => 'Invoice created successfully'
            ], 201);
        });
    }

    /**
     * Update the specified invoice in storage.
     */
    public function update(Request $request, $id)
    {
        $invoice = Invoice::find($id);
        if (!$invoice) {
            return response()->json([
                'message' => 'Invoice not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:draft,issued,paid,cancelled',
            'items' => 'nullable|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.description' => 'required|string|max:255',
            'items.*.quantity' => 'required|numeric|min:0',
            'items.*.rate' => 'required|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        return DB::transaction(function () use ($request, $invoice) {
            $invoice->update(['status' => $request->status]);

            if ($request->items) {
                $invoice->items()->delete();
                $total_amount = collect($request->items)->sum(function ($item) {
                    return $item['quantity'] * $item['rate'];
                });
                $tax_amount = $total_amount * ($invoice->tax_rate ?? 0);
                $grand_total = $total_amount + $tax_amount - $invoice->discount;

                $invoice->update([
                    'total_amount' => $total_amount,
                    'tax_amount' => $tax_amount,
                    'grand_total' => $grand_total
                ]);

                foreach ($request->items as $item) {
                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'item_id' => $item['item_id'],
                        'item_description' => $item['description'],
                        'quantity' => $item['quantity'],
                        'rate' => $item['rate'],
                        'amount' => $item['quantity'] * $item['rate']
                    ]);
                }
            }

            // Deduct stock for paid customer invoices
            if ($request->status === 'paid' && !$invoice->supplier_id) {
                foreach ($invoice->items as $item) {
                    $stock = ItemStock::where('item_id', $item->item_id)->first();
                    if (!$stock || $stock->stock_quantity < $item->quantity) {
                        throw new \Exception("Insufficient stock for item ID {$item->item_id}");
                    }

                    $stock->update([
                        'stock_quantity' => $stock->stock_quantity - $item->quantity,
                        'updated_at' => Carbon::now()
                    ]);

                    StockMovement::create([
                        'item_id' => $item->item_id,
                        'order_id' => $invoice->order_id,
                        'movement_type' => 'sale',
                        'quantity' => -$item->quantity,
                        'movement_date' => Carbon::now()
                    ]);
                }
            }

            return response()->json([
                'data' => $invoice->load(['order', 'supplier', 'customer', 'items']),
                'message' => 'Invoice updated successfully'
            ], 200);
        });
    }

    /**
     * Remove the specified invoice from storage.
     */
    public function destroy($id)
    {
        $invoice = Invoice::find($id);
        if (!$invoice) {
            return response()->json([
                'message' => 'Invoice not found'
            ], 404);
        }

        try {
            $invoice->delete();
            return response()->json([
                'message' => 'Invoice deleted successfully'
            ], 200);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'message' => 'Cannot delete invoice due to existing references'
            ], 422);
        }
    }
}