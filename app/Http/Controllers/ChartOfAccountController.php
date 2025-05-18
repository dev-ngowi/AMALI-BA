<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ChartOfAccountController extends Controller
{
    /**
     * Display a listing of the chart of accounts.
     */
    public function index()
    {
        $accounts = ChartOfAccount::with(['parentAccount', 'childAccounts'])->get();
        return response()->json([
            'data' => $accounts,
            'message' => 'success'
        ], 200);
    }

    /**
     * Display the specified chart of account.
     */
    public function show($id)
    {
        $account = ChartOfAccount::with(['parentAccount', 'childAccounts'])->find($id);
        if (!$account) {
            return response()->json([
                'message' => 'Account not found'
            ], 404);
        }

        return response()->json([
            'data' => $account,
            'message' => 'success'
        ], 200);
    }

    /**
     * Store a newly created chart of account in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_code' => 'required|string|max:255|unique:chart_of_accounts,account_code',
            'account_name' => 'required|string|max:255',
            'account_type' => 'required|string|in:Asset,Liability,Equity,Revenue,Expense',
            'parent_account_id' => 'nullable|exists:chart_of_accounts,id',
            'is_active' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $account = ChartOfAccount::create([
            'account_code' => $request->account_code,
            'account_name' => $request->account_name,
            'account_type' => $request->account_type,
            'parent_account_id' => $request->parent_account_id,
            'is_active' => $request->is_active ?? true
        ]);

        return response()->json([
            'data' => $account->load(['parentAccount', 'childAccounts']),
            'message' => 'Account created successfully'
        ], 201);
    }

    /**
     * Update the specified chart of account in storage.
     */
    public function update(Request $request, $id)
    {
        $account = ChartOfAccount::find($id);
        if (!$account) {
            return response()->json([
                'message' => 'Account not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'account_code' => 'required|string|max:255|unique:chart_of_accounts,account_code,' . $id,
            'account_name' => 'required|string|max:255',
            'account_type' => 'required|string|in:Asset,Liability,Equity,Revenue,Expense',
            'parent_account_id' => 'nullable|exists:chart_of_accounts,id',
            'is_active' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Prevent self-referencing
        if ($request->parent_account_id == $id) {
            return response()->json([
                'message' => 'Account cannot be its own parent'
            ], 422);
        }

        $account->update([
            'account_code' => $request->account_code,
            'account_name' => $request->account_name,
            'account_type' => $request->account_type,
            'parent_account_id' => $request->parent_account_id,
            'is_active' => $request->is_active ?? $account->is_active
        ]);

        return response()->json([
            'data' => $account->load(['parentAccount', 'childAccounts']),
            'message' => 'Account updated successfully'
        ], 200);
    }

    /**
     * Remove the specified chart of account from storage.
     */
    public function destroy($id)
    {
        $account = ChartOfAccount::find($id);
        if (!$account) {
            return response()->json([
                'message' => 'Account not found'
            ], 404);
        }

        if ($account->childAccounts()->exists()) {
            return response()->json([
                'message' => 'Cannot delete account with child accounts'
            ], 422);
        }

        try {
            $account->delete();
            return response()->json([
                'message' => 'Account deleted successfully'
            ], 200);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'message' => 'Cannot delete account due to existing references'
            ], 422);
        }
    }
}