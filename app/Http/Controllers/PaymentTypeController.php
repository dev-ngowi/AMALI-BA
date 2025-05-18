<?php

namespace App\Http\Controllers;

use App\Models\PaymentType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentTypeController extends Controller
{
    /**
     * Display a listing of the payment types.
     */
    public function index()
    {
        $paymentTypes = PaymentType::all();
        return response()->json([
            'data' => $paymentTypes,
            'message' => 'success'
        ], 200);
    }

    /**
     * Display the specified payment type.
     */
    public function show($id)
    {
        $paymentType = PaymentType::find($id);
        if (!$paymentType) {
            return response()->json([
                'message' => 'Payment type not found'
            ], 404);
        }

        return response()->json([
            'data' => $paymentType,
            'message' => 'success'
        ], 200);
    }

    /**
     * Store a newly created payment type in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:payment_types,name'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $paymentType = PaymentType::create([
            'name' => $request->name
        ]);

        return response()->json([
            'data' => $paymentType,
            'message' => 'Payment type created successfully'
        ], 201);
    }

    /**
     * Update the specified payment type in storage.
     */
    public function update(Request $request, $id)
    {
        $paymentType = PaymentType::find($id);
        if (!$paymentType) {
            return response()->json([
                'message' => 'Payment type not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:payment_types,name,' . $id
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $paymentType->update([
            'name' => $request->name
        ]);

        return response()->json([
            'data' => $paymentType,
            'message' => 'Payment type updated successfully'
        ], 200);
    }

    /**
     * Remove the specified payment type from storage.
     */
    public function destroy($id)
    {
        $paymentType = PaymentType::find($id);
        if (!$paymentType) {
            return response()->json([
                'message' => 'Payment type not found'
            ], 404);
        }

        try {
            $paymentType->delete();
            return response()->json([
                'message' => 'Payment type deleted successfully'
            ], 200);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'message' => 'Cannot delete payment type due to existing references'
            ], 422);
        }
    }
}