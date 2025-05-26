<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $filterDate = $request->query('filter_date');
        $query = Expense::with(['user', 'items'])->select('expenses.*');

        if ($filterDate) {
            $query->where('expense_date', $filterDate);
        }

        $expenses = $query->get()->map(function ($expense) {
            return [
                'id' => $expense->id,
                'expense_type' => $expense->expense_type,
                'user_id' => $expense->user_id,
                'store_id' => $expense->store_id,
                'expense_date' => $expense->expense_date,
                'amount' => $expense->amount,
                'description' => $expense->description,
                'reference_number' => $expense->reference_number,
                'receipt_path' => $expense->receipt_path,
                'linked_shop_item_id' => $expense->linked_shop_item_id,
                'created_at' => $expense->created_at,
                'updated_at' => $expense->updated_at,
                'username' => $expense->user ? $expense->user->username : null,
                'linked_item_names' => $expense->items->pluck('name')->join(', ')
            ];
        });

        return response()->json([
            'data' => $expenses,
            'message' => 'success'
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'expense_type' => 'required|in:home,shop',
            'user_id' => 'required|exists:users,id',
            'store_id' => 'nullable|exists:stores,id',
            'expense_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'reference_number' => 'nullable|string|max:255',
            'receipt_path' => 'nullable|string|max:255',
            'linked_shop_item_id' => 'nullable|exists:items,id',
            'linked_shop_item_ids' => 'nullable|array',
            'linked_shop_item_ids.*' => 'exists:items,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check total sales constraint
        $totalSales = DB::table('orders')
            ->whereDate('date', $request->expense_date)
            ->where('status', 'completed')
            ->sum('ground_total');

        if ($request->amount > $totalSales) {
            return response()->json([
                'message' => "Expense amount ({$request->amount}) exceeds total sales ({$totalSales}) for {$request->expense_date}"
            ], 422);
        }

        return DB::transaction(function () use ($request) {
            $expense = Expense::create([
                'expense_type' => $request->expense_type,
                'user_id' => $request->user_id,
                'store_id' => $request->store_id,
                'expense_date' => $request->expense_date,
                'amount' => $request->amount,
                'description' => $request->description,
                'reference_number' => $request->reference_number,
                'receipt_path' => $request->receipt_path,
                'linked_shop_item_id' => $request->linked_shop_item_id
            ]);

            // Attach linked items
            if ($request->linked_shop_item_ids) {
                $expense->items()->sync($request->linked_shop_item_ids);
            }

            // Update daily financials
            $this->updateDailyFinancials($request->expense_date);

            return response()->json([
                'data' => $expense->load(['user', 'items']),
                'message' => 'Expense created successfully'
            ], 201);
        });
    }

    public function update(Request $request, $id)
    {
        $expense = Expense::find($id);
        if (!$expense) {
            return response()->json([
                'message' => 'Expense not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'expense_type' => 'required|in:home,shop',
            'user_id' => 'required|exists:users,id',
            'store_id' => 'nullable|exists:stores,id',
            'expense_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'reference_number' => 'nullable|string|max:255',
            'receipt_path' => 'nullable|string|max:255',
            'linked_shop_item_id' => 'nullable|exists:items,id',
            'linked_shop_item_ids' => 'nullable|array',
            'linked_shop_item_ids.*' => 'exists:items,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check total sales constraint
        $totalSales = DB::table('orders')
            ->whereDate('date', $request->expense_date)
            ->where('status', 'completed')
            ->sum('ground_total');

        if ($request->amount > $totalSales) {
            return response()->json([
                'message' => "Expense amount ({$request->amount}) exceeds total sales ({$totalSales}) for {$request->expense_date}"
            ], 422);
        }

        return DB::transaction(function () use ($request, $expense) {
            $oldDate = $expense->expense_date;

            $expense->update([
                'expense_type' => $request->expense_type,
                'user_id' => $request->user_id,
                'store_id' => $request->store_id,
                'expense_date' => $request->expense_date,
                'amount' => $request->amount,
                'description' => $request->description,
                'reference_number' => $request->reference_number,
                'receipt_path' => $request->receipt_path,
                'linked_shop_item_id' => $request->linked_shop_item_id
            ]);

            // Update linked items
            $expense->items()->sync($request->linked_shop_item_ids ?? []);

            // Update daily financials for old and new dates if changed
            if ($oldDate != $request->expense_date) {
                $this->updateDailyFinancials($oldDate);
            }
            $this->updateDailyFinancials($request->expense_date);

            return response()->json([
                'data' => $expense->load(['user', 'items']),
                'message' => 'Expense updated successfully'
            ], 200);
        });
    }

    public function destroy($id)
    {
        $expense = Expense::find($id);
        if (!$expense) {
            return response()->json([
                'message' => 'Expense not found'
            ], 404);
        }

        return DB::transaction(function () use ($expense) {
            $expenseDate = $expense->expense_date;
            $expense->delete();

            // Update daily financials
            $this->updateDailyFinancials($expenseDate);

            return response()->json([
                'message' => 'Expense deleted successfully'
            ], 200);
        });
    }

    private function updateDailyFinancials($date)
    {
        $totalOrders = DB::table('orders')
            ->whereDate('date', $date)
            ->where('status', 'completed')
            ->sum('ground_total');

        $totalPurchases = DB::table('purchase_orders')
            ->where('order_date', $date)
            ->where('status', 'Received')
            ->sum('total_amount');

        $totalExpenses = DB::table('expenses')
            ->where('expense_date', $date)
            ->sum('amount');

        $afterExpenses = $totalOrders - $totalPurchases - $totalExpenses;

        DB::table('daily_financials')->updateOrInsert(
            ['date' => $date],
            [
                'total_orders' => $totalOrders,
                'total_purchases' => $totalPurchases,
                'total_expenses' => $totalExpenses,
                'after_expenses' => $afterExpenses,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]
        );
    }
}