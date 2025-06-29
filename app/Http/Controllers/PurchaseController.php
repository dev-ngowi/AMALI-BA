<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\GoodReceiptNote;
use App\Models\GoodReceiveNoteItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PurchaseController extends Controller
{
    /**
     * Retrieve all purchase orders with their items, with pagination.
     */
    public function indexPurchaseOrders(Request $request)
    {
        $perPage = $request->input('per_page', 10); // Default to 10 items per page
        $query = PurchaseOrder::with('items', 'supplier', 'store');

        if ($request->has('order_number')) {
            $query->where('order_number', $request->input('order_number'));
        }

        $data = $query->paginate($perPage);

        return response()->json([
            'data' => $data->items(),
            'pagination' => [
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'next_page_url' => $data->nextPageUrl(),
                'prev_page_url' => $data->previousPageUrl(),
            ],
            'message' => 'success'
        ], 200);
    }

    /**
     * Create a new purchase order.
     */
    public function createPurchaseOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_number' => 'required|string|max:255|unique:purchase_orders,order_number',
            'supplier_id' => 'required|exists:vendors,id',
            'store_id' => 'required|exists:stores,id',
            'order_date' => 'required|date',
            'expected_delivery_date' => 'nullable|date|after_or_equal:order_date',
            'status' => 'nullable|in:Draft,Pending,Approved,Completed,Cancelled',
            'currency' => 'nullable|string|in:TZS,USD,EUR',
            'notes' => 'nullable|string',
            'total_amount' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.unit_id' => 'required|exists:units,id',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.selling_price' => 'required|numeric|min:0',
            // 'items.*.selling_unit_id' => 'required|exists:units,id',
            'items.*.tax_id' => 'nullable|exists:taxes,id',
            'items.*.total_price' => 'required|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Validate day status
        $dayOpen = DB::table('day_open')
            ->where('store_id', $request->store_id)
            ->where('working_date', Carbon::parse($request->order_date)->format('Y-m-d'))
            ->where('is_open', 1)
            ->first();

        if (!$dayOpen) {
            return response()->json([
                'message' => 'Cannot create purchase order: Day is not open'
            ], 422);
        }

        $dayClosed = DB::table('day_close')
            ->where('store_id', $request->store_id)
            ->where('working_date', Carbon::parse($request->order_date)->format('Y-m-d'))
            ->where('is_locked', 1)
            ->first();

        if ($dayClosed) {
            return response()->json([
                'message' => 'Cannot create purchase order: Day is closed'
            ], 422);
        }

        return DB::transaction(function () use ($request) {
            // Calculate total amount if not provided
            $totalAmount = $request->total_amount ?? collect($request->items)->sum('total_price');

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
                'notes' => $request->notes,
                'created_at' => now(),
                'updated_at' => now()
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
                    // 'selling_unit_id' => $item['selling_unit_id'],
                    'tax_id' => $item['tax_id'] ?? null,
                    'total_price' => $item['total_price'],
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            return response()->json([
                'data' => $purchaseOrder->load('items'),
                'message' => 'Purchase order created successfully'
            ], 201);
        });
    }

    /**
     * Update an existing purchase order.
     */
    public function updatePurchaseOrder(Request $request, $id)
    {
        $purchaseOrder = PurchaseOrder::findOrFail($id);

        // Prevent updating if status is Completed or Cancelled
        if (in_array($purchaseOrder->status, ['Completed', 'Cancelled'])) {
            return response()->json([
                'message' => 'Cannot update purchase order: Status is ' . $purchaseOrder->status
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'order_number' => 'required|string|max:255|unique:purchase_orders,order_number,' . $id,
            'supplier_id' => 'required|exists:vendors,id',
            'store_id' => 'required|exists:stores,id',
            'order_date' => 'required|date',
            'expected_delivery_date' => 'nullable|date|after_or_equal:order_date',
            'status' => 'nullable|in:Draft,Pending,Approved,Completed,Cancelled',
            'currency' => 'nullable|string|in:TZS,USD,EUR',
            'notes' => 'nullable|string',
            'total_amount' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.unit_id' => 'required|exists:units,id',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.selling_price' => 'required|numeric|min:0',
            // 'items.*.selling_unit_id' => 'required|exists:units,id',
            'items.*.tax_id' => 'nullable|exists:taxes,id',
            'items.*.total_price' => 'required|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Validate day status
        $dayOpen = DB::table('day_open')
            ->where('store_id', $request->store_id)
            ->where('working_date', Carbon::parse($request->order_date)->format('Y-m-d'))
            ->where('is_open', 1)
            ->first();

        if (!$dayOpen) {
            return response()->json([
                'message' => 'Cannot update purchase order: Day is not open'
            ], 422);
        }

        $dayClosed = DB::table('day_close')
            ->where('store_id', $request->store_id)
            ->where('working_date', Carbon::parse($request->order_date)->format('Y-m-d'))
            ->where('is_locked', 1)
            ->first();

        if ($dayClosed) {
            return response()->json([
                'message' => 'Cannot update purchase order: Day is closed'
            ], 422);
        }

        return DB::transaction(function () use ($request, $purchaseOrder) {
            // Calculate total amount if not provided
            $totalAmount = $request->total_amount ?? collect($request->items)->sum('total_price');

            // Update purchase order
            $purchaseOrder->update([
                'order_number' => $request->order_number,
                'supplier_id' => $request->supplier_id,
                'store_id' => $request->store_id,
                'order_date' => $request->order_date,
                'expected_delivery_date' => $request->expected_delivery_date,
                'status' => $request->status ?? $purchaseOrder->status,
                'total_amount' => $totalAmount,
                'currency' => $request->currency ?? $purchaseOrder->currency,
                'notes' => $request->notes,
                'updated_at' => now()
            ]);

            // Delete existing items
            $purchaseOrder->items()->delete();

            // Create new items
            foreach ($request->items as $item) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'item_id' => $item['item_id'],
                    'unit_id' => $item['unit_id'],
                    'quantity' => $item['quantity'],
                    'discount' => $item['discount'] ?? 0.00,
                    'unit_price' => $item['unit_price'],
                    'selling_price' => $item['selling_price'],
                    // 'selling_unit_id' => $item['selling_unit_id'],
                    'tax_id' => $item['tax_id'] ?? null,
                    'total_price' => $item['total_price'],
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            return response()->json([
                'data' => $purchaseOrder->load('items'),
                'message' => 'Purchase order updated successfully'
            ], 200);
        });
    }

    /**
     * Delete a purchase order.
     */
    public function deletePurchaseOrder($id)
    {
        $purchaseOrder = PurchaseOrder::findOrFail($id);

        // Prevent deletion if status is Completed or has associated GRN
        if ($purchaseOrder->status === 'Completed') {
            return response()->json([
                'message' => 'Cannot delete purchase order: Status is Completed'
            ], 422);
        }

        if ($purchaseOrder->goodReceiptNotes()->exists()) {
            return response()->json([
                'message' => 'Cannot delete purchase order: Associated GRN exists'
            ], 422);
        }

        // Validate day status
        $dayOpen = DB::table('day_open')
            ->where('store_id', $purchaseOrder->store_id)
            ->where('working_date', Carbon::parse($purchaseOrder->order_date)->format('Y-m-d'))
            ->where('is_open', 1)
            ->first();

        if (!$dayOpen) {
            return response()->json([
                'message' => 'Cannot delete purchase order: Day is not open'
            ], 422);
        }

        $dayClosed = DB::table('day_close')
            ->where('store_id', $purchaseOrder->store_id)
            ->where('working_date', Carbon::parse($purchaseOrder->order_date)->format('Y-m-d'))
            ->where('is_locked', 1)
            ->first();

        if ($dayClosed) {
            return response()->json([
                'message' => 'Cannot delete purchase order: Day is closed'
            ], 422);
        }

        return DB::transaction(function () use ($purchaseOrder) {
            // Delete items
            $purchaseOrder->items()->delete();
            // Delete purchase order
            $purchaseOrder->delete();

            return response()->json([
                'message' => 'Purchase order deleted successfully'
            ], 200);
        });
    }

    /**
     * Retrieve all good receipt notes with their items, with pagination.
     */
    public function indexGoodReceiptNotes(Request $request)
    {
        $perPage = $request->input('per_page', 10); // Default to 10 items per page
        $data = GoodReceiptNote::with('items')->paginate($perPage);

        return response()->json([
            'data' => $data->items(),
            'pagination' => [
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'next_page_url' => $data->nextPageUrl(),
                'prev_page_url' => $data->previousPageUrl(),
            ],
            'message' => 'success'
        ], 200);
    }

    /**
     * Create a new good receipt note.
     */
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
        $purchaseOrder = PurchaseOrder::findOrFail($request->purchase_order_id);
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
        $storeId = $request->store_id ?? $purchaseOrder->store_id;
        $dayOpen = DB::table('day_open')
            ->where('store_id', $storeId)
            ->where('working_date', Carbon::parse($request->received_date)->format('Y-m-d'))
            ->where('is_open', 1)
            ->first();

        if (!$dayOpen) {
            return response()->json([
                'message' => 'Cannot create GRN: Day is not open'
            ], 422);
        }

        $dayClosed = DB::table('day_close')
            ->where('store_id', $storeId)
            ->where('working_date', Carbon::parse($request->received_date)->format('Y-m-d'))
            ->where('is_locked', 1)
            ->first();

        if ($dayClosed) {
            return response()->json([
                'message' => 'Cannot create GRN: Day is closed'
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
                'remarks' => $request->remarks,
                'created_at' => now(),
                'updated_at' => now()
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
                    'received_condition' => $item['received_condition'] ?? 'Good',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            // Update purchase order status if GRN is Received
            if ($request->status === 'Received') {
                $purchaseOrder->update(['status' => 'Received']);
            }

            return response()->json([
                'data' => $grn->load('items'),
                'message' => 'Good Receipt Note created successfully'
            ], 201);
        });
    }

    /**
     * Update an existing good receipt note.
     */
    public function updateGoodReceiptNote(Request $request, $id)
    {
        $grn = GoodReceiptNote::findOrFail($id);

        // Prevent updating if status is Received
        if ($grn->status === 'Received') {
            return response()->json([
                'message' => 'Cannot update GRN: Status is Received'
            ], 422);
        }

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
        $purchaseOrder = PurchaseOrder::findOrFail($request->purchase_order_id);

        // Validate store_id consistency
        if ($request->store_id && $request->store_id != $purchaseOrder->store_id) {
            return response()->json([
                'message' => 'Store ID does not match purchase order store ID'
            ], 422);
        }

        // Validate day status
        $storeId = $request->store_id ?? $purchaseOrder->store_id;
        $dayOpen = DB::table('day_open')
            ->where('store_id', $storeId)
            ->where('working_date', Carbon::parse($request->received_date)->format('Y-m-d'))
            ->where('is_open', 1)
            ->first();

        if (!$dayOpen) {
            return response()->json([
                'message' => 'Cannot update GRN: Day is not open'
            ], 422);
        }

        $dayClosed = DB::table('day_close')
            ->where('store_id', $storeId)
            ->where('working_date', Carbon::parse($request->received_date)->format('Y-m-d'))
            ->where('is_locked', 1)
            ->first();

        if ($dayClosed) {
            return response()->json([
                'message' => 'Cannot update GRN: Day is closed'
            ], 422);
        }

        return DB::transaction(function () use ($request, $grn, $purchaseOrder) {
            // Update GRN
            $grn->update([
                'purchase_order_id' => $request->purchase_order_id,
                'supplier_id' => $request->supplier_id,
                'store_id' => $request->store_id ?? $purchaseOrder->store_id,
                'received_by' => $request->received_by,
                'received_date' => $request->received_date,
                'delivery_note_number' => $request->delivery_note_number,
                'status' => $request->status ?? $grn->status,
                'remarks' => $request->remarks,
                'updated_at' => now()
            ]);

            // Delete existing items
            $grn->items()->delete();

            // Create new items
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
                    'received_condition' => $item['received_condition'] ?? 'Good',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            // Update purchase order status if GRN is Received
            if ($request->status === 'Received' && $purchaseOrder->status !== 'Received') {
                $purchaseOrder->update(['status' => 'Received']);
            } elseif ($grn->status === 'Received' && $request->status === 'Pending') {
                // Revert purchase order status if GRN status changes back to Pending
                $purchaseOrder->update(['status' => 'Approved']);
            }

            return response()->json([
                'data' => $grn->load('items'),
                'message' => 'Good Receipt Note updated successfully'
            ], 200);
        });
    }

    /**
     * Delete a good receipt note.
     */
    public function deleteGoodReceiptNote($id)
    {
        $grn = GoodReceiptNote::findOrFail($id);

        // Prevent deletion if status is Received
        if ($grn->status === 'Received') {
            return response()->json([
                'message' => 'Cannot delete GRN: Status is Received'
            ], 422);
        }

        // Validate day status
        $storeId = $grn->store_id;
        $dayOpen = DB::table('day_open')
            ->where('store_id', $storeId)
            ->where('working_date', Carbon::parse($grn->received_date)->format('Y-m-d'))
            ->where('is_open', 1)
            ->first();

        if (!$dayOpen) {
            return response()->json([
                'message' => 'Cannot delete GRN: Day is not open'
            ], 422);
        }

        $dayClosed = DB::table('day_close')
            ->where('store_id', $storeId)
            ->where('working_date', Carbon::parse($grn->received_date)->format('Y-m-d'))
            ->where('is_locked', 1)
            ->first();

        if ($dayClosed) {
            return response()->json([
                'message' => 'Cannot delete GRN: Day is closed'
            ], 422);
        }

        return DB::transaction(function () use ($grn) {
            // Delete items
            $grn->items()->delete();
            // Delete GRN
            $grn->delete();

            return response()->json([
                'message' => 'Good Receipt Note deleted successfully'
            ], 200);
        });
    }

    /**
     * Check day status for a store on a specific date.
     */
    public function checkDayStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'store_id' => 'required|exists:stores,id',
            'working_date' => 'required|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $dayOpen = DB::table('day_open')
            ->where('store_id', $request->store_id)
            ->where('working_date', Carbon::parse($request->working_date)->format('Y-m-d'))
            ->where('is_open', 1)
            ->first();

        $dayClosed = DB::table('day_close')
            ->where('store_id', $request->store_id)
            ->where('working_date', Carbon::parse($request->working_date)->format('Y-m-d'))
            ->where('is_locked', 1)
            ->first();

        return response()->json([
            'is_open' => !!$dayOpen,
            'is_closed' => !!$dayClosed,
            'message' => 'success'
        ], 200);
    }
}