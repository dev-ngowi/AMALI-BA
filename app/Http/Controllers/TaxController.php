<?php

namespace App\Http\Controllers;

use App\Models\Tax;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TaxController extends Controller
{
    /**
     * Display a listing of the taxes.
     */
    public function index()
    {
        $taxes = Tax::all();
        return response()->json([
            'data' => $taxes,
            'message' => 'success'
        ], 200);
    }

    /**
     * Display the specified tax.
     */
    public function show($id)
    {
        $tax = Tax::find($id);
        if (!$tax) {
            return response()->json([
                'message' => 'Tax not found'
            ], 404);
        }

        return response()->json([
            'data' => $tax,
            'message' => 'success'
        ], 200);
    }

    /**
     * Store a newly created tax in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:taxes,name',
            'tax_type' => 'required|in:exclusive,inclusive',
            'tax_mode' => 'required|in:percentage,amount',
            'tax_percentage' => 'nullable|numeric|min:0|required_if:tax_mode,percentage',
            'tax_amount' => 'nullable|numeric|min:0|required_if:tax_mode,amount'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Ensure only one of tax_percentage or tax_amount is provided
        if ($request->tax_percentage !== null && $request->tax_amount !== null) {
            return response()->json([
                'message' => 'Only one of tax_percentage or tax_amount can be provided'
            ], 422);
        }

        $tax = Tax::create([
            'name' => $request->name,
            'tax_type' => $request->tax_type,
            'tax_mode' => $request->tax_mode,
            'tax_percentage' => $request->tax_percentage,
            'tax_amount' => $request->tax_amount
        ]);

        return response()->json([
            'data' => $tax,
            'message' => 'Tax created successfully'
        ], 201);
    }

    /**
     * Update the specified tax in storage.
     */
    public function update(Request $request, $id)
    {
        $tax = Tax::find($id);
        if (!$tax) {
            return response()->json([
                'message' => 'Tax not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:taxes,name,' . $id,
            'tax_type' => 'required|in:exclusive,inclusive',
            'tax_mode' => 'required|in:percentage,amount',
            'tax_percentage' => 'nullable|numeric|min:0|required_if:tax_mode,percentage',
            'tax_amount' => 'nullable|numeric|min:0|required_if:tax_mode,amount'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Ensure only one of tax_percentage or tax_amount is provided
        if ($request->tax_percentage !== null && $request->tax_amount !== null) {
            return response()->json([
                'message' => 'Only one of tax_percentage or tax_amount can be provided'
            ], 422);
        }

        $tax->update([
            'name' => $request->name,
            'tax_type' => $request->tax_type,
            'tax_mode' => $request->tax_mode,
            'tax_percentage' => $request->tax_percentage,
            'tax_amount' => $request->tax_amount
        ]);

        return response()->json([
            'data' => $tax,
            'message' => 'Tax updated successfully'
        ], 200);
    }

    /**
     * Remove the specified tax from storage.
     */
    public function destroy($id)
    {
        $tax = Tax::find($id);
        if (!$tax) {
            return response()->json([
                'message' => 'Tax not found'
            ], 404);
        }

        try {
            $tax->delete();
            return response()->json([
                'message' => 'Tax deleted successfully'
            ], 200);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'message' => 'Cannot delete tax due to existing references'
            ], 422);
        }
    }
}