<?php

namespace App\Http\Controllers;

use App\Models\CashReconciliation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CashReconciliationController extends Controller
{
    // Display a listing of cash reconciliations with pagination
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 10); // Default to 10 items per page
        $cashReconciliations = CashReconciliation::with(['order', 'user', 'shift', 'store'])->paginate($perPage);
        
        return response()->json([
            'success' => true,
            'data' => [
                'cash_reconciliations' => $cashReconciliations->items(),
                'pagination' => [
                    'current_page' => $cashReconciliations->currentPage(),
                    'total_pages' => $cashReconciliations->lastPage(),
                    'total_items' => $cashReconciliations->total(),
                    'per_page' => $cashReconciliations->perPage(),
                ]
            ]
        ], 200);
    }

    // Store a newly created cash reconciliation
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'nullable|exists:orders,id',
            'pos_sales_amount' => 'required|numeric|min:0',
            'actual_cash_amount' => 'required|numeric|min:0',
            'sales_date' => 'required|date',
            'reconciliation_date' => 'required|date',
            'user_id' => 'nullable|exists:users,id',
            'shift_id' => 'nullable|exists:shifts,id',
            'store_id' => 'nullable|exists:stores,id',
            'payment_method' => 'nullable|in:CASH,CARD,MOBILE,OTHER',
            'reconciliation_status' => 'nullable|in:PENDING,COMPLETED,DISCREPANCY',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->only([
            'order_id',
            'pos_sales_amount',
            'actual_cash_amount',
            'sales_date',
            'reconciliation_date',
            'user_id',
            'shift_id',
            'store_id',
            'payment_method',
            'reconciliation_status',
            'notes',
        ]);

        $cashReconciliation = CashReconciliation::create($data);
        $cashReconciliation->load(['order', 'user', 'shift', 'store']);
        return response()->json([
            'success' => true,
            'data' => ['cash_reconciliation' => $cashReconciliation]
        ], 201);
    }

    // Display a specific cash reconciliation
    public function show($id)
    {
        $cashReconciliation = CashReconciliation::with(['order', 'user', 'shift', 'store'])->find($id);
        if (!$cashReconciliation) {
            return response()->json([
                'success' => false,
                'message' => 'Cash reconciliation not found'
            ], 404);
        }
        return response()->json([
            'success' => true,
            'data' => ['cash_reconciliation' => $cashReconciliation]
        ], 200);
    }

    // Update a specific cash reconciliation
    public function update(Request $request, $id)
    {
        $cashReconciliation = CashReconciliation::find($id);
        if (!$cashReconciliation) {
            return response()->json([
                'success' => false,
                'message' => 'Cash reconciliation not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'order_id' => 'nullable|exists:orders,id',
            'pos_sales_amount' => 'required|numeric|min:0',
            'actual_cash_amount' => 'required|numeric|min:0',
            'sales_date' => 'required|date',
            'reconciliation_date' => 'required|date',
            'user_id' => 'nullable|exists:users,id',
            'shift_id' => 'nullable|exists:shifts,id',
            'store_id' => 'nullable|exists:stores,id',
            'payment_method' => 'nullable|in:CASH,CARD,MOBILE,OTHER',
            'reconciliation_status' => 'nullable|in:PENDING,COMPLETED,DISCREPANCY',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->only([
            'order_id',
            'pos_sales_amount',
            'actual_cash_amount',
            'sales_date',
            'reconciliation_date',
            'user_id',
            'shift_id',
            'store_id',
            'payment_method',
            'reconciliation_status',
            'notes',
        ]);

        $cashReconciliation->update($data);
        $cashReconciliation->load(['order', 'user', 'shift', 'store']);
        return response()->json([
            'success' => true,
            'data' => ['cash_reconciliation' => $cashReconciliation]
        ], 200);
    }

    // Delete a specific cash reconciliation (soft delete)
    public function destroy($id)
    {
        $cashReconciliation = CashReconciliation::find($id);
        if (!$cashReconciliation) {
            return response()->json([
                'success' => false,
                'message' => 'Cash reconciliation not found'
            ], 404);
        }

        $cashReconciliation->delete();
        return response()->json([
            'success' => true,
            'message' => 'Cash reconciliation soft deleted'
        ], 200);
    }

    // Restore a soft-deleted cash reconciliation
    public function restore($id)
    {
        $cashReconciliation = CashReconciliation::withTrashed()->find($id);
        if (!$cashReconciliation) {
            return response()->json([
                'success' => false,
                'message' => 'Cash reconciliation not found'
            ], 404);
        }

        if (!$cashReconciliation->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'Cash reconciliation is not deleted'
            ], 400);
        }

        $cashReconciliation->restore();
        return response()->json([
            'success' => true,
            'data' => ['cash_reconciliation' => $cashReconciliation],
            'message' => 'Cash reconciliation restored'
        ], 200);
    }
}