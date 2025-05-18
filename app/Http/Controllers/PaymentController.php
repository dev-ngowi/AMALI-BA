<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    /**
     * Display a listing of the payments.
     */
    public function index()
    {
        $payments = Payment::with('paymentType')->get();
        return response()->json([
            'data' => $payments,
            'message' => 'success'
        ], 200);
    }

    /**
     * Display the specified payment.
     */
    public function show($id)
    {
        $payment = Payment::with('paymentType')->find($id);
        if (!$payment) {
            return response()->json([
                'message' => 'Payment not found'
            ], 404);
        }

        return response()->json([
            'data' => $payment,
            'message' => 'success'
        ], 200);
    }

    /**
     * Store a newly created payment in storage.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'short_code' => 'required|string|max:255|unique:payments,short_code',
                'payment_method' => 'nullable|string|max:255',
                'payment_type_id' => 'nullable|exists:payment_types,id',
                'created_at' => 'nullable|date',
                'updated_at' => 'nullable|date'
            ]);

            if ($validator->fails()) {
                Log::warning('Payment validation failed', ['errors' => $validator->errors()]);
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $payment = Payment::create([
                'short_code' => $request->short_code,
                'payment_method' => $request->payment_method,
                'payment_type_id' => $request->payment_type_id,
                'created_at' => $request->created_at ?? now(),
                'updated_at' => $request->updated_at ?? now()
            ]);

            return response()->json([
                'data' => $payment->load('paymentType'),
                'message' => 'Payment created successfully'
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create payment', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Internal server error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified payment in storage.
     */
    public function update(Request $request, $id)
    {
        $payment = Payment::find($id);
        if (!$payment) {
            return response()->json([
                'message' => 'Payment not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'short_code' => 'required|string|max:255|unique:payments,short_code,' . $id,
            'payment_method' => 'nullable|string|max:255',
            'payment_type_id' => 'nullable|exists:payment_types,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $payment->update([
            'short_code' => $request->short_code,
            'payment_method' => $request->payment_method,
            'payment_type_id' => $request->payment_type_id
        ]);

        return response()->json([
            'data' => $payment->load('paymentType'),
            'message' => 'Payment updated successfully'
        ], 200);
    }

    /**
     * Remove the specified payment from storage.
     */
    public function destroy($id)
    {
        $payment = Payment::find($id);
        if (!$payment) {
            return response()->json([
                'message' => 'Payment not found'
            ], 404);
        }

        try {
            $payment->delete();
            return response()->json([
                'message' => 'Payment deleted successfully'
            ], 200);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'message' => 'Cannot delete payment due to existing references'
            ], 422);
        }
    }
}