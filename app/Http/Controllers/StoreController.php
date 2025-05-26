<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StoreController extends Controller
{
    public function index()
    {
        $data = Store::all();
        return response()->json([
            'data' => $data,
            'message' => 'success'
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'manager_id' => 'nullable|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $store = Store::create([
            'name' => $request->name,
            'location' => $request->location, 
            'manager_id' => $request->manager_id
        ]);

        return response()->json([
            'data' => $store,
            'message' => 'Store created successfully'
        ], 201);
    }
}