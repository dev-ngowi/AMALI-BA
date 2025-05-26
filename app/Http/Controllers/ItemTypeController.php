<?php

namespace App\Http\Controllers;

use App\Models\ItemType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ItemTypeController extends Controller
{
    /**
     * Display a listing of the item types.
     */
    public function index()
    {
        $item_types = ItemType::all();
        return response()->json([
            'data' => $item_types,
            'message' => 'success'
        ], 200);
    }

    /**
     * Store a newly created item type in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:item_types,name'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $itemType = ItemType::create([
            'name' => $request->name
        ]);

        return response()->json([
            'data' => $itemType,
            'message' => 'Item Type created successfully'
        ], 201);
    }

    /**
     * Display the specified item type.
     */
    public function show($id)
    {
        $itemType = ItemType::find($id);

        if (!$itemType) {
            return response()->json([
                'message' => 'Item Type not found'
            ], 404);
        }

        return response()->json([
            'data' => $itemType,
            'message' => 'success'
        ], 200);
    }

    /**
     * Update the specified item type in storage.
     */
    public function update(Request $request, $id)
    {
        $itemType = ItemType::find($id);

        if (!$itemType) {
            return response()->json([
                'message' => 'Item Type not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:item_types,name,' . $id
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $itemType->update([
            'name' => $request->name
        ]);

        return response()->json([
            'data' => $itemType,
            'message' => 'Item Type updated successfully'
        ], 200);
    }

    /**
     * Remove the specified item type from storage.
     */
    public function destroy($id)
    {
        $itemType = ItemType::find($id);

        if (!$itemType) {
            return response()->json([
                'message' => 'Item Type not found'
            ], 404);
        }

        try {
            $itemType->delete();
            return response()->json([
                'message' => 'Item Type deleted successfully'
            ], 200);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'message' => 'Cannot delete item type due to existing references'
            ], 422);
        }
    }
}