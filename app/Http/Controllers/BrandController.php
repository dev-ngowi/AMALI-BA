<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Response;

class BrandController extends Controller
{
    /**
     * Display a listing of the item brands.
     */
    public function index(Request $request)
    {
        try {
            $query = DB::table('item_brands');

            // Filter by is_active if provided
            if ($request->has('is_active')) {
                $query->where('is_active', $request->input('is_active') === 'true' ? 1 : 0);
            }

            // Search by name if provided
            if ($request->has('search')) {
                $query->where('name', 'like', '%' . $request->input('search') . '%');
            }

            $brands = $query->get();

            return response()->json([
                'data' => $brands,
                'message' => 'Item brands retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve item brands',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Store a newly created item brand in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:item_brands,name',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $now = now()->toDateTimeString();
            $brand = DB::table('item_brands')->insertGetId([
                'name' => $request->input('name'),
                'description' => $request->input('description'),
                'is_active' => $request->input('is_active', true),
                'created_at' => $now,
                'updated_at' => $now,
                'last_modified' => $now,
                'synced' => 0,
            ]);

            $newBrand = DB::table('item_brands')->where('id', $brand)->first();

            return response()->json([
                'data' => $newBrand,
                'message' => 'Item brand created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create item brand',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified item brand.
     */
    public function show($id)
    {
        $brand = DB::table('item_brands')->where('id', $id)->first();

        if (!$brand) {
            return response()->json([
                'message' => 'Item brand not found'
            ], 404);
        }

        return response()->json([
            'data' => $brand,
            'message' => 'success'
        ], 200);
    }

    /**
     * Update the specified item brand in storage.
     */
    public function update(Request $request, $id)
    {
        $brand = DB::table('item_brands')->where('id', $id)->first();

        if (!$brand) {
            return response()->json([
                'message' => 'Item brand not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:item_brands,name,' . $id,
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $now = now()->toDateTimeString();
            DB::table('item_brands')->where('id', $id)->update([
                'name' => $request->input('name', $brand->name),
                'description' => $request->input('description', $brand->description),
                'is_active' => $request->input('is_active', $brand->is_active),
                'updated_at' => $now,
                'last_modified' => $now,
                'synced' => 0,
            ]);

            $updatedBrand = DB::table('item_brands')->where('id', $id)->first();

            return response()->json([
                'data' => $updatedBrand,
                'message' => 'Item brand updated successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update item brand',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified item brand from storage.
     */
    public function destroy($id)
    {
        $brand = DB::table('item_brands')->where('id', $id)->first();

        if (!$brand) {
            return response()->json([
                'message' => 'Item brand not found'
            ], 404);
        }

        try {
            DB::table('item_brands')->where('id', $id)->delete();
            return response()->json([
                'message' => 'Item brand deleted successfully'
            ], 200);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'message' => 'Cannot delete item brand due to existing references'
            ], 422);
        }
    }
}