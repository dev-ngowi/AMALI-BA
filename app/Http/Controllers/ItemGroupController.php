<?php

namespace App\Http\Controllers;

use App\Models\ItemGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ItemGroupController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => ItemGroup::all(),
            'message' => 'Success'
        ], 200);
    }

    public function checkName(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'store_id' => 'required|integer|exists:stores,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $exists = ItemGroup::where('name', $request->name)
                          ->where('store_id', $request->store_id)
                          ->exists();
        return response()->json([
            'exists' => $exists,
            'message' => $exists ? 'Name already exists for this store' : 'Name is available'
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'store_id' => 'required|integer|exists:stores,id',
            'created_at' => 'sometimes|date',
            'updated_at' => 'sometimes|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Ensure name is unique for the store
        if (ItemGroup::where('name', $request->name)->where('store_id', $request->store_id)->exists()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => ['name' => ['The name has already been taken for this store.']]
            ], 422);
        }

        $itemGroup = ItemGroup::create([
            'name' => $request->name,
            'store_id' => $request->store_id,
            'created_at' => $request->created_at ?? now(),
            'updated_at' => $request->updated_at ?? now()
        ]);

        return response()->json([
            'data' => $itemGroup,
            'message' => 'Item group created successfully'
        ], 201);
    }
}