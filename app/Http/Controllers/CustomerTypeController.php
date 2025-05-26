<?php

namespace App\Http\Controllers;

use App\Models\CustomerType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CustomerTypeController extends Controller
{
    /**
     * Display a listing of the customer types.
     */
    public function index()
    {
        $customerTypes = CustomerType::all();
        return response()->json([
            'data' => $customerTypes,
            'message' => 'success'
        ], 200);
    }

    /**
     * Display the specified customer type.
     */
    public function show($id)
    {
        $customerType = CustomerType::find($id);
        if (!$customerType) {
            return response()->json([
                'message' => 'Customer type not found'
            ], 404);
        }

        return response()->json([
            'data' => $customerType,
            'message' => 'success'
        ], 200);
    }

    /**
     * Store a newly created customer type in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:customer_types,name',
            'is_active' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $customerType = CustomerType::create([
            'name' => $request->name,
            'is_active' => $request->is_active ?? true
        ]);

        return response()->json([
            'data' => $customerType,
            'message' => 'Customer type created successfully'
        ], 201);
    }

    /**
     * Update the specified customer type in storage.
     */
    public function update(Request $request, $id)
    {
        $customerType = CustomerType::find($id);
        if (!$customerType) {
            return response()->json([
                'message' => 'Customer type not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:customer_types,name,' . $id,
            'is_active' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $customerType->update([
            'name' => $request->name,
            'is_active' => $request->is_active ?? $customerType->is_active
        ]);

        return response()->json([
            'data' => $customerType,
            'message' => 'Customer type updated successfully'
        ], 200);
    }

    /**
     * Remove the specified customer type from storage.
     */
    public function destroy($id)
    {
        $customerType = CustomerType::find($id);
        if (!$customerType) {
            return response()->json([
                'message' => 'Customer type not found'
            ], 404);
        }

        $customerType->delete();

        return response()->json([
            'message' => 'Customer type deleted successfully'
        ], 200);
    }
}