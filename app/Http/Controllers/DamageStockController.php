<?php

namespace App\Http\Controllers;

use App\Models\DamageStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DamageStockController extends Controller
{
    // Display a listing of damage stocks with pagination
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 10); // Default to 10 items per page
        $damageStocks = DamageStock::with('item')->paginate($perPage);
        
        return response()->json([
            'success' => true,
            'data' => [
                'damage_stocks' => $damageStocks->items(),
                'pagination' => [
                    'current_page' => $damageStocks->currentPage(),
                    'total_pages' => $damageStocks->lastPage(),
                    'total_items' => $damageStocks->total(),
                    'per_page' => $damageStocks->perPage(),
                ]
            ]
        ], 200);
    }

    // Store a newly created damage stock
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'required|exists:items,id',
            'quantity' => 'required|integer|min:0',
            'damage_date' => 'nullable|date',
            'reason' => 'nullable|string',
            'status' => 'nullable|in:pending,returned,discarded,repaired',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->only(['item_id', 'quantity', 'damage_date', 'reason', 'status', 'notes']);
        if (empty($data['damage_date'])) {
            $data['damage_date'] = now();
        }
        $damageStock = DamageStock::create($data);
        $damageStock->load('item');
        return response()->json([
            'success' => true,
            'data' => ['damage_stock' => $damageStock]
        ], 201);
    }

    // Display a specific damage stock
    public function show($id)
    {
        $damageStock = DamageStock::with('item')->find($id);
        if (!$damageStock) {
            return response()->json([
                'success' => false,
                'message' => 'Damage stock not found'
            ], 404);
        }
        return response()->json([
            'success' => true,
            'data' => ['damage_stock' => $damageStock]
        ], 200);
    }

    // Update a specific damage stock
    public function update(Request $request, $id)
    {
        $damageStock = DamageStock::find($id);
        if (!$damageStock) {
            return response()->json([
                'success' => false,
                'message' => 'Damage stock not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'item_id' => 'required|exists:items,id',
            'quantity' => 'required|integer|min:0',
            'damage_date' => 'nullable|date',
            'reason' => 'nullable|string',
            'status' => 'nullable|in:pending,returned,discarded,repaired',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->only(['item_id', 'quantity', 'damage_date', 'reason', 'status', 'notes']);
        $damageStock->update($data);
        $damageStock->load('item');
        return response()->json([
            'success' => true,
            'data' => ['damage_stock' => $damageStock]
        ], 200);
    }

    // Delete a specific damage stock (soft delete)
    public function destroy($id)
    {
        $damageStock = DamageStock::find($id);
        if (!$damageStock) {
            return response()->json([
                'success' => false,
                'message' => 'Damage stock not found'
            ], 404);
        }

        $damageStock->delete();
        return response()->json([
            'success' => true,
            'message' => 'Damage stock soft deleted'
        ], 200);
    }

    // Restore a soft-deleted damage stock
    public function restore($id)
    {
        $damageStock = DamageStock::withTrashed()->find($id);
        if (!$damageStock) {
            return response()->json([
                'success' => false,
                'message' => 'Damage stock not found'
            ], 404);
        }

        if (!$damageStock->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'Damage stock is not deleted'
            ], 400);
        }

        $damageStock->restore();
        return response()->json([
            'success' => true,
            'data' => ['damage_stock' => $damageStock],
            'message' => 'Damage stock restored'
        ], 200);
    }
}
