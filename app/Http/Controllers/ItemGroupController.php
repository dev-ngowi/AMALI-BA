<?php

namespace App\Http\Controllers;

use App\Models\ItemGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ItemGroupController extends Controller
{
    public function index()
    {
        $data = ItemGroup::all();
        return response()->json([
            'data' => $data,
            'message' => 'success'
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:item_groups,name'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $itemGroup = ItemGroup::create([
            'name' => $request->name
        ]);

        return response()->json([
            'data' => $itemGroup,
            'message' => 'Item group created successfully'
        ], 201);
    }
}