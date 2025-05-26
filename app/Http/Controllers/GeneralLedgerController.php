<?php

namespace App\Http\Controllers;

use App\Models\GeneralLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class GeneralLedgerController extends Controller
{
    /**
     * Display a listing of the general ledger entries.
     */
    public function index()
    {
        $ledgerEntries = GeneralLedger::with(['account', 'store'])->get();
        return response()->json([
            'data' => $ledgerEntries,
            'message' => 'success'
        ], 200);
    }

    /**
     * Display the specified general ledger entry.
     */
    public function show($id)
    {
        $ledgerEntry = GeneralLedger::with(['account', 'store'])->find($id);
        if (!$ledgerEntry) {
            return response()->json([
                'message' => 'Ledger entry not found'
            ], 404);
        }

        return response()->json([
            'data' => $ledgerEntry,
            'message' => 'success'
        ], 200);
    }

    /**
     * Store a newly created general ledger entry in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'transaction_date' => 'required|date',
            'account_id' => 'required|exists:chart_of_accounts,id',
            'description' => 'nullable|string',
            'debit_amount' => 'nullable|numeric|min:0',
            'credit_amount' => 'nullable|numeric|min:0',
            'reference_type' => 'nullable|string|in:Invoice,Payment,Expense,Cart',
            'reference_id' => 'nullable|integer|min:1',
            'store_id' => 'nullable|exists:stores,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Ensure only one of debit or credit is non-zero
        if (($request->debit_amount > 0 && $request->credit_amount > 0) || ($request->debit_amount == 0 && $request->credit_amount == 0)) {
            return response()->json([
                'message' => 'Exactly one of debit_amount or credit_amount must be non-zero'
            ], 422);
        }

        // Validate reference_id exists for the given reference_type
        if ($request->reference_type && $request->reference_id) {
            $tableMap = [
                'Invoice' => 'invoices',
                'Payment' => 'payments',
                'Expense' => 'expenses',
                'Cart' => 'carts'
            ];
            $table = $tableMap[$request->reference_type] ?? null;
            if ($table && !DB::table($table)->where('id', $request->reference_id)->exists()) {
                return response()->json([
                    'message' => "Invalid reference_id for {$request->reference_type}"
                ], 422);
            }
        }

        $ledgerEntry = GeneralLedger::create([
            'transaction_date' => $request->transaction_date,
            'account_id' => $request->account_id,
            'description' => $request->description,
            'debit_amount' => $request->debit_amount ?? 0.00,
            'credit_amount' => $request->credit_amount ?? 0.00,
            'reference_type' => $request->reference_type,
            'reference_id' => $request->reference_id,
            'store_id' => $request->store_id
        ]);

        return response()->json([
            'data' => $ledgerEntry->load(['account', 'store']),
            'message' => 'Ledger entry created successfully'
        ], 201);
    }

    /**
     * Update the specified general ledger entry in storage.
     */
    public function update(Request $request, $id)
    {
        $ledgerEntry = GeneralLedger::find($id);
        if (!$ledgerEntry) {
            return response()->json([
                'message' => 'Ledger entry not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'transaction_date' => 'required|date',
            'account_id' => 'required|exists:chart_of_accounts,id',
            'description' => 'nullable|string',
            'debit_amount' => 'nullable|numeric|min:0',
            'credit_amount' => 'nullable|numeric|min:0',
            'reference_type' => 'nullable|string|in:Invoice,Payment,Expense,Cart',
            'reference_id' => 'nullable|integer|min:1',
            'store_id' => 'nullable|exists:stores,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Ensure only one of debit or credit is non-zero
        if (($request->debit_amount > 0 && $request->credit_amount > 0) || ($request->debit_amount == 0 && $request->credit_amount == 0)) {
            return response()->json([
                'message' => 'Exactly one of debit_amount or credit_amount must be non-zero'
            ], 422);
        }

        // Validate reference_id exists for the given reference_type
        if ($request->reference_type && $request->reference_id) {
            $tableMap = [
                'Invoice' => 'invoices',
                'Payment' => 'payments',
                'Expense' => 'expenses',
                'Cart' => 'carts'
            ];
            $table = $tableMap[$request->reference_type] ?? null;
            if ($table && !DB::table($table)->where('id', $request->reference_id)->exists()) {
                return response()->json([
                    'message' => "Invalid reference_id for {$request->reference_type}"
                ], 422);
            }
        }

        $ledgerEntry->update([
            'transaction_date' => $request->transaction_date,
            'account_id' => $request->account_id,
            'description' => $request->description,
            'debit_amount' => $request->debit_amount ?? 0.00,
            'credit_amount' => $request->credit_amount ?? 0.00,
            'reference_type' => $request->reference_type,
            'reference_id' => $request->reference_id,
            'store_id' => $request->store_id
        ]);

        return response()->json([
            'data' => $ledgerEntry->load(['account', 'store']),
            'message' => 'Ledger entry updated successfully'
        ], 200);
    }

    /**
     * Remove the specified general ledger entry from storage.
     */
    public function destroy($id)
    {
        $ledgerEntry = GeneralLedger::find($id);
        if (!$ledgerEntry) {
            return response()->json([
                'message' => 'Ledger entry not found'
            ], 404);
        }

        try {
            $ledgerEntry->delete();
            return response()->json([
                'message' => 'Ledger entry deleted successfully'
            ], 200);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'message' => 'Cannot delete ledger entry due to existing references'
            ], 422);
        }
    }
}