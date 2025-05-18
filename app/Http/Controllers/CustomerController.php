<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CustomerController extends Controller
{
    /**
     * Display a listing of the customers.
     */
    public function index()
    {
        $customers = Customer::with(['customerType', 'city'])->get();
        return response()->json([
            'data' => $customers,
            'message' => 'success'
        ], 200);
    }

    /**
     * Display the specified customer.
     */
    public function show($id)
    {
        $customer = Customer::with(['customerType', 'city'])->find($id);
        if (!$customer) {
            return response()->json([
                'message' => 'Customer not found'
            ], 404);
        }

        return response()->json([
            'data' => $customer,
            'message' => 'success'
        ], 200);
    }

    /**
     * Store a newly created customer in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_name' => 'required|string|max:255',
            'customer_type_id' => 'required|exists:customer_types,id',
            'city_id' => 'required|exists:cities,id',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'active' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $customer = Customer::create([
            'customer_name' => $request->customer_name,
            'customer_type_id' => $request->customer_type_id,
            'city_id' => $request->city_id,
            'phone' => $request->phone,
            'email' => $request->email,
            'address' => $request->address,
            'active' => $request->active ?? true
        ]);

        return response()->json([
            'data' => $customer->load(['customerType', 'city']),
            'message' => 'Customer created successfully'
        ], 201);
    }

    /**
     * Update the specified customer in storage.
     */
    public function update(Request $request, $id)
    {
        $customer = Customer::find($id);
        if (!$customer) {
            return response()->json([
                'message' => 'Customer not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'customer_name' => 'required|string|max:255',
            'customer_type_id' => 'required|exists:customer_types,id',
            'city_id' => 'required|exists:cities,id',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'active' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $customer->update([
            'customer_name' => $request->customer_name,
            'customer_type_id' => $request->customer_type_id,
            'city_id' => $request->city_id,
            'phone' => $request->phone,
            'email' => $request->email,
            'address' => $request->address,
            'active' => $request->active ?? $customer->active
        ]);

        return response()->json([
            'data' => $customer->load(['customerType', 'city']),
            'message' => 'Customer updated successfully'
        ], 200);
    }

    /**
     * Remove the specified customer from storage.
     */
    public function destroy($id)
    {
        $customer = Customer::find($id);
        if (!$customer) {
            return response()->json([
                'message' => 'Customer not found'
            ], 404);
        }

        try {
            $customer->delete();
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'message' => 'Cannot delete customer due to existing references'
            ], 422);
        }

        return response()->json([
            'message' => 'Customer deleted successfully'
        ], 200);
    }
}