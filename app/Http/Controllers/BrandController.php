<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class BrandController extends Controller
{
    /**
     * Display a paginated listing of the item brands.
     */
        public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 10);
            $page = $request->input('page', 1); // This line is crucial
            $search = $request->input('search');
            $isActive = $request->input('is_active');
    
            Log::info('DEBUG: Page requested by frontend', ['requested_page' => $page]); // ADD THIS LINE
    
            $query = DB::table('item_brands');
    
            if ($request->has('is_active') && in_array($isActive, ['true', 'false'])) {
                $query->where('is_active', $isActive === 'true' ? 1 : 0);
            }
    
            if ($search) {
                $query->where('name', 'like', '%' . $search . '%');
            }
    
            $brands = $query->paginate($perPage);
    
            Log::info('Fetched item brands', [
                'query' => $query->toSql(),
                'bindings' => $query->getBindings(),
                'count' => $brands->total(),
                'requested_page_variable' => $page, // The $page variable
                'paginator_current_page' => $brands->currentPage(), // The actual current page from paginator
                'per_page' => $perPage
            ]);
    
            return response()->json([
                'data' => $brands->items(),
                'current_page' => $brands->currentPage(),
                'per_page' => $brands->perPage(),
                'total' => $brands->total(),
                'last_page' => $brands->lastPage(),
                'message' => 'Item brands retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve item brands', ['error' => $e->getMessage()]);
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
            Log::error('Validation failed for item brand creation', [
                'errors' => $validator->errors(),
                'request' => $request->all()
            ]);
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
            Log::info('Item brand created successfully', ['brand' => $newBrand]);

            return response()->json([
                'data' => $newBrand,
                'message' => 'Item brand created successfully'
            ], 201);
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Database error creating item brand', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            return response()->json([
                'message' => 'Failed to create item brand due to database error',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Exception $e) {
            Log::error('Unexpected error creating item brand', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
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
        try {
            $brand = DB::table('item_brands')->where('id', $id)->first();

            if (!$brand) {
                Log::warning('Item brand not found', ['id' => $id]);
                return response()->json([
                    'message' => 'Item brand not found'
                ], 404);
            }

            return response()->json([
                'data' => $brand,
                'message' => 'success'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve item brand', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'message' => 'Failed to retrieve item brand',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update the specified item brand in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            $brand = DB::table('item_brands')->where('id', $id)->first();

            if (!$brand) {
                Log::warning('Item brand not found', ['id' => $id]);
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
                Log::error('Validation failed for item brand update', [
                    'id' => $id,
                    'errors' => $validator->errors(),
                    'request' => $request->all()
                ]);
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

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
            Log::info('Item brand updated successfully', ['brand' => $updatedBrand]);

            return response()->json([
                'data' => $updatedBrand,
                'message' => 'Item brand updated successfully'
            ], 200);
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Database error updating item brand', [
                'id' => $id,
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            return response()->json([
                'message' => 'Failed to update item brand due to database error',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Exception $e) {
            Log::error('Unexpected error updating item brand', [
                'id' => $id,
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
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
        try {
            $brand = DB::table('item_brands')->where('id', $id)->first();

            if (!$brand) {
                Log::warning('Item brand not found', ['id' => $id]);
                return response()->json([
                    'message' => 'Item brand not found'
                ], 404);
            }

            DB::table('item_brands')->where('id', $id)->delete();
            Log::info('Item brand deleted successfully', ['id' => $id]);

            return response()->json([
                'message' => 'Item brand deleted successfully'
            ], 200);
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Database error deleting item brand', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'message' => 'Cannot delete item brand due to existing references'
            ], 422);
        } catch (\Exception $e) {
            Log::error('Unexpected error deleting item brand', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'message' => 'Failed to delete item brand',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}