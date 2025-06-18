<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ShiftController extends Controller
{
    // Display a listing of shifts with pagination
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 10); // Default to 10 items per page
        $shifts = Shift::with(['user', 'store'])->paginate($perPage);
        
        return response()->json([
            'success' => true,
            'data' => [
                'shifts' => $shifts->items(),
                'pagination' => [
                    'current_page' => $shifts->currentPage(),
                    'total_pages' => $shifts->lastPage(),
                    'total_items' => $shifts->total(),
                    'per_page' => $shifts->perPage(),
                ]
            ]
        ], 200);
    }

    // Store a newly created shift
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'store_id' => 'nullable|exists:stores,id',
            'shift_start' => 'required|date',
            'shift_end' => 'nullable|date|after_or_equal:shift_start',
            'shift_status' => 'nullable|in:OPEN,CLOSED,CANCELLED',
            'total_cash_handled' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->only([
            'user_id',
            'store_id',
            'shift_start',
            'shift_end',
            'shift_status',
            'total_cash_handled',
            'notes',
        ]);

        $shift = Shift::create($data);
        $shift->load(['user', 'store']);
        return response()->json([
            'success' => true,
            'data' => ['shift' => $shift]
        ], 201);
    }

    // Display a specific shift
    public function show($id)
    {
        $shift = Shift::with(['user', 'store'])->find($id);
        if (!$shift) {
            return response()->json([
                'success' => false,
                'message' => 'Shift not found'
            ], 404);
        }
        return response()->json([
            'success' => true,
            'data' => ['shift' => $shift]
        ], 200);
    }

    // Update a specific shift
    public function update(Request $request, $id)
    {
        $shift = Shift::find($id);
        if (!$shift) {
            return response()->json([
                'success' => false,
                'message' => 'Shift not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'store_id' => 'nullable|exists:stores,id',
            'shift_start' => 'required|date',
            'shift_end' => 'nullable|date|after_or_equal:shift_start',
            'shift_status' => 'nullable|in:OPEN,CLOSED,CANCELLED',
            'total_cash_handled' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->only([
            'user_id',
            'store_id',
            'shift_start',
            'shift_end',
            'shift_status',
            'total_cash_handled',
            'notes',
        ]);

        $shift->update($data);
        $shift->load(['user', 'store']);
        return response()->json([
            'success' => true,
            'data' => ['shift' => $shift]
        ], 200);
    }

    // Delete a specific shift (soft delete)
    public function destroy($id)
    {
        $shift = Shift::find($id);
        if (!$shift) {
            return response()->json([
                'success' => false,
                'message' => 'Shift not found'
            ], 404);
        }

        $shift->delete();
        return response()->json([
            'success' => true,
            'message' => 'Shift soft deleted'
        ], 200);
    }

    // Restore a soft-deleted shift
    public function restore($id)
    {
        $shift = Shift::withTrashed()->find($id);
        if (!$shift) {
            return response()->json([
                'success' => false,
                'message' => 'Shift not found'
            ], 404);
        }

        if (!$shift->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'Shift is not deleted'
            ], 400);
        }

        $shift->restore();
        return response()->json([
            'success' => true,
            'data' => ['shift' => $shift],
            'message' => 'Shift restored'
        ], 200);
    }
}