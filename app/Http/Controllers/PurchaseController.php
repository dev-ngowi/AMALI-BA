<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\GoodReceiptNote;
use App\Models\GoodReceiveNoteItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PurchaseController extends Controller
{
    public function indexPurchaseOrders()
    {
        $data = PurchaseOrder::with('items')->get();
        return response()->json([
            'data' => $data,
            'message' => 'success'
        ], 200);
    }

    public function createPurchaseOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_number' => 'required|string|max:255|unique:purchase_orders,order_number',
            'supplier_id' => 'required|exists:vendors,id',
            'store_id' => 'required|exists:stores,id',
            'order_date' => 'required|date',
            'expected_delivery_date' => 'nullable|date|after_or_equal:order_date',
            'status' => 'nullable|in:Pending,Draft,Received,Paid,Cancelled',
            'currency' => 'nullable|string|in:TZS,USD,EUR',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.unit_id' => 'required|exists:units,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.selling_price' => 'required|numeric|min:0',
            'items.*.selling_unit_id' => 'required|exists:units,id',
            'items.*.tax_id' => 'nullable|exists:taxes,id',
            'items.*.total_price' => 'required|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Validate day status (assuming day_open and day_close tables exist)
        $dayOpen = DB::table('day_open')
            ->where('store_id', $request->store_id)
            ->where('working_date', Carbon::parse($request->order_date)->format('Y-m-d'))
            ->where('is_open', 1)
            ->first();

        if (!$dayOpen) {
            return response()->json([
                'message' => 'Cannot create purchase order for an unopened day'
            ], 422);
        }

        $dayClosed = DB::table('day_close')
            ->where('store_id', $request->store_id)
            ->where('working_date', Carbon::parse($request->order_date)->format('Y-m-d'))
            ->where('is_locked', 1)
            ->first();

        if ($dayClosed) {
            return response()->json([
                'message' => 'Cannot create purchase order for a closed day'
            ], 422);
        }

        return DB::transaction(function () use ($request) {
            // Calculate total amount
            $totalAmount = collect($request->items)->sum('total_price');

            // Create purchase order
            $purchaseOrder = PurchaseOrder::create([
                'order_number' => $request->order_number,
                'supplier_id' => $request->supplier_id,
                'store_id' => $request->store_id,
                'order_date' => $request->order_date,
                'expected_delivery_date' => $request->expected_delivery_date,
                'status' => $request->status ?? 'Pending',
                'total_amount' => $totalAmount,
                'currency' => $request->currency ?? 'TZS',
                'notes' => $request->notes
            ]);

            // Create purchase order items
            foreach ($request->items as $item) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'item_id' => $item['item_id'],
                    'unit_id' => $item['unit_id'],
                    'quantity' => $item['quantity'],
                    'discount' => $item['discount'] ?? 0.00,
                    'unit_price' => $item['unit_price'],
                    'selling_price' => $item['selling_price'],
                    'selling_unit_id' => $item['selling_unit_id'],
                    'tax_id' => $item['tax_id'] ?? null,
                    'total_price' => $item['total_price']
                ]);
            }

            // Save purchase transaction if status is Received
            if ($request->status === 'Received') {
                $this->savePurchaseTransaction($purchaseOrder);
            }

            return response()->json([
                'data' => $purchaseOrder->load('items'),
                'message' => 'Purchase order created successfully'
            ], 201);
        });
    }

    public function indexGoodReceiptNotes()
    {
        $data = GoodReceiptNote::with('items')->get();
        return response()->json([
            'data' => $data,
            'message' => 'success'
        ], 200);
    }

    public function createGoodReceiptNote(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'supplier_id' => 'required|exists:vendors,id',
            'store_id' => 'nullable|exists:stores,id',
            'received_by' => 'required|exists:users,id',
            'received_date' => 'required|date',
            'delivery_note_number' => 'nullable|string|max:255',
            'status' => 'nullable|in:Pending,Received',
            'remarks' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.purchase_order_item_id' => 'nullable|exists:purchase_order_items,id',
            'items.*.ordered_quantity' => 'required|numeric|min:0',
            'items.*.received_quantity' => 'required|numeric|min:0',
            'items.*.accepted_quantity' => 'required|numeric|min:0',
            'items.*.rejected_quantity' => 'nullable|numeric|min:0',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.selling_price' => 'required|numeric|min:0',
            'items.*.unit_id' => 'required|exists:units,id',
            'items.*.received_condition' => 'nullable|in:Good,Damaged'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Validate purchase order
        $purchaseOrder = PurchaseOrder::find($request->purchase_order_id);
        if ($purchaseOrder->status === 'Received') {
            return response()->json([
                'message' => 'Purchase order already received'
            ], 422);
        }

        // Validate store_id consistency
        if ($request->store_id && $request->store_id != $purchaseOrder->store_id) {
            return response()->json([
                'message' => 'Store ID does not match purchase order store ID'
            ], 422);
        }

        // Validate day status
        $dayOpen = DB::table('day_open')
            ->where('store_id', $request->store_id ?? $purchaseOrder->store_id)
            ->where('working_date', Carbon::parse($request->received_date)->format('Y-m-d'))
            ->where('is_open', 1)
            ->first();

        if (!$dayOpen) {
            return response()->json([
                'message' => 'Cannot create GRN for an unopened day'
            ], 422);
        }

        $dayClosed = DB::table('day_close')
            ->where('store_id', $request->store_id ?? $purchaseOrder->store_id)
            ->where('working_date', Carbon::parse($request->received_date)->format('Y-m-d'))
            ->where('is_locked', 1)
            ->first();

        if ($dayClosed) {
            return response()->json([
                'message' => 'Cannot create GRN for a closed day'
            ], 422);
        }

        return DB::transaction(function () use ($request, $purchaseOrder) {
            // Generate GRN number
            $grnNumber = 'GRN-' . $request->purchase_order_id . '-' . Carbon::now()->format('YmdHis');

            // Create GRN
            $grn = GoodReceiptNote::create([
                'grn_number' => $grnNumber,
                'purchase_order_id' => $request->purchase_order_id,
                'supplier_id' => $request->supplier_id,
                'store_id' => $request->store_id ?? $purchaseOrder->store_id,
                'received_by' => $request->received_by,
                'received_date' => $request->received_date,
                'delivery_note_number' => $request->delivery_note_number,
                'status' => $request->status ?? 'Pending',
                'remarks' => $request->remarks
            ]);

            // Create GRN items
            foreach ($request->items as $item) {
                GoodReceiveNoteItem::create([
                    'grn_id' => $grn->id,
                    'purchase_order_item_id' => $item['purchase_order_item_id'] ?? null,
                    'item_id' => $item['item_id'],
                    'ordered_quantity' => $item['ordered_quantity'],
                    'received_quantity' => $item['received_quantity'],
                    'accepted_quantity' => $item['accepted_quantity'],
                    'rejected_quantity' => $item['rejected_quantity'] ?? 0.00,
                    'unit_price' => $item['unit_price'],
                    'selling_price' => $item['selling_price'],
                    'unit_id' => $item['unit_id'],
                    'received_condition' => $item['received_condition'] ?? 'Good'
                ]);
            }

            // If status is Received, update purchase order, stock, and prices
            if ($request->status === 'Received') {
                $purchaseOrder->update(['status' => 'Received']);
                $this->savePurchaseTransaction($purchaseOrder);
                $this->updateStockFromGrn($grn);
                $this->updateItemPricesFromGrn($grn, $grn->store_id);
            }

            return response()->json([
                'data' => $grn->load('items'),
                'message' => 'Good Receipt Note created successfully'
            ], 201);
        });
    }

    private function savePurchaseTransaction(PurchaseOrder $purchaseOrder)
    {
        // Assuming chart_of_accounts, purchase_transactions, and general_ledger tables exist
        $inventoryAccount = DB::table('chart_of_accounts')
            ->where('account_name', 'Inventory')
            ->where('account_type', 'Asset')
            ->first();

        if (!$inventoryAccount) {
            throw new \Exception('Inventory account not found');
        }

        $ledgerEntry = DB::table('general_ledger')->insertGetId([
            'transaction_date' => Carbon::parse($purchaseOrder->order_date)->format('Y-m-d'),
            'account_id' => $inventoryAccount->id,
            'description' => "Inventory received for Purchase Order #{$purchaseOrder->id}",
            'debit_amount' => $purchaseOrder->total_amount,
            'credit_amount' => 0.00,
            'reference_type' => 'Purchase',
            'reference_id' => $purchaseOrder->id,
            'store_id' => $purchaseOrder->store_id,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);

        DB::table('purchase_transactions')->insert([
            'purchase_order_id' => $purchaseOrder->id,
            'account_id' => $inventoryAccount->id,
            'amount' => $purchaseOrder->total_amount,
            'transaction_type' => 'Purchase',
            'ledger_entry_id' => $ledgerEntry,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);
    }

    private function updateStockFromGrn(GoodReceiptNote $grn)
    {
        foreach ($grn->items as $item) {
            $stock = DB::table('item_stocks')
                ->where('item_id', $item->item_id)
                ->where('stock_id', $grn->store_id)
                ->first();

            if ($stock) {
                DB::table('item_stocks')
                    ->where('id', $stock->id)
                    ->update([
                        'stock_quantity' => $stock->stock_quantity + $item->accepted_quantity,
                        'updated_at' => Carbon::now()
                    ]);
            } else {
                DB::table('item_stocks')->insert([
                    'item_id' => $item->item_id,
                    'stock_id' => $grn->store_id,
                    'stock_quantity' => $item->accepted_quantity,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]);
            }
        }
    }

    private function updateItemPricesFromGrn(GoodReceiptNote $grn, $storeId)
    {
        foreach ($grn->items as $item) {
            $itemCost = DB::table('item_costs')
                ->where('item_id', $item->item_id)
                ->where('store_id', $storeId)
                ->where('unit_id', $item->unit_id)
                ->first();

            if ($itemCost) {
                DB::table('item_costs')
                    ->where('id', $itemCost->id)
                    ->update([
                        'amount' => $item->unit_price,
                        'updated_at' => Carbon::now()
                    ]);
            } else {
                DB::table('item_costs')->insert([
                    'item_id' => $item->item_id,
                    'store_id' => $storeId,
                    'unit_id' => $item->unit_id,
                    'amount' => $item->unit_price,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]);
            }

            $itemPrice = DB::table('item_prices')
                ->where('item_id', $item->item_id)
                ->where('store_id', $storeId)
                ->where('unit_id', $item->unit_id)
                ->first();

            if ($itemPrice) {
                DB::table('item_prices')
                    ->where('id', $itemPrice->id)
                    ->update([
                        'amount' => $item->selling_price,
                        'updated_at' => Carbon::now()
                    ]);
            } else {
                DB::table('item_prices')->insert([
                    'item_id' => $item->item_id,
                    'store_id' => $storeId,
                    'unit_id' => $item->unit_id,
                    'amount' => $item->selling_price,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]);
            }
        }
    }
}