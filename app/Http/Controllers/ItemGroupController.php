<?php

namespace App\Http\Controllers;

use App\Models\ItemGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ItemGroupController extends Controller
{
    /**
     * Display a listing of all item groups.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 10);
            $page = $request->input('page', 1);
            $search = $request->input('search');

            $query = ItemGroup::with('store')->whereNull('deleted_at');

            if ($search) {
                $query->where('name', 'like', '%' . $search . '%');
            }

            $itemGroups = $query->paginate($perPage);

            Log::info('Fetched item groups', [
                'query' => $query->toSql(),
                'bindings' => $query->getBindings(),
                'count' => $itemGroups->total(),
                'page' => $page,
                'per_page' => $perPage,
                'request' => $request->all()
            ]);

            return response()->json([
                'data' => $itemGroups->items(),
                'current_page' => $itemGroups->currentPage(),
                'per_page' => $itemGroups->perPage(),
                'total' => $itemGroups->total(),
                'last_page' => $itemGroups->lastPage(),
                'message' => 'Item groups retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve item groups', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return response()->json([
                'message' => 'Failed to retrieve item groups',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if an item group name exists for a specific store.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkName(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'store_id' => 'required|integer|exists:stores,id'
            ]);

            if ($validator->fails()) {
                Log::warning('Validation failed for checkName', [
                    'errors' => $validator->errors()->toArray(),
                    'request' => $request->all()
                ]);
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $exists = ItemGroup::where('name', $request->name)
                              ->where('store_id', $request->store_id)
                              ->whereNull('deleted_at')
                              ->exists();

            Log::info('checkName successful', [
                'name' => $request->name,
                'store_id' => $request->store_id,
                'exists' => $exists,
                'request' => $request->all()
            ]);

            return response()->json([
                'exists' => $exists,
                'message' => $exists ? 'Name already exists for this store' : 'Name is available'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error in checkName', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return response()->json([
                'error' => 'Internal Server Error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created item group in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'store_id' => 'required|integer|exists:stores,id',
                'created_at' => 'sometimes|date',
                'updated_at' => 'sometimes|date'
            ]);

            if ($validator->fails()) {
                Log::warning('Validation failed for store item group', [
                    'errors' => $validator->errors()->toArray(),
                    'request' => $request->all()
                ]);
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Ensure name is unique for the store
            if (ItemGroup::where('name', $request->name)
                         ->where('store_id', $request->store_id)
                         ->whereNull('deleted_at')
                         ->exists()) {
                Log::warning('Duplicate item group name for store', [
                    'name' => $request->name,
                    'store_id' => $request->store_id,
                    'request' => $request->all()
                ]);
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => ['name' => ['The name has already been taken for this store.']]
                ], 422);
            }

            $itemGroup = ItemGroup::create([
                'name' => $request->name,
                'store_id' => $request->store_id,
                'created_at' => $request->created_at ?? now(),
                'updated_at' => $request->updated_at ?? now()
            ]);

            Log::info('Item group created successfully', [
                'id' => $itemGroup->id,
                'name' => $itemGroup->name,
                'store_id' => $itemGroup->store_id,
                'request' => $request->all()
            ]);

            return response()->json([
                'data' => $itemGroup,
                'message' => 'Item group created successfully'
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating item group', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return response()->json([
                'message' => 'Error creating item group',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified item group.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            $itemGroup = ItemGroup::whereNull('deleted_at')->find($id);

            if (!$itemGroup) {
                Log::warning('Item group not found for update', [
                    'id' => $id,
                    'request' => $request->all()
                ]);
                return response()->json([
                    'message' => 'Item group not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'store_id' => 'required|integer|exists:stores,id',
                'updated_at' => 'sometimes|date'
            ]);

            if ($validator->fails()) {
                Log::warning('Validation failed for update item group', [
                    'id' => $id,
                    'errors' => $validator->errors()->toArray(),
                    'request' => $request->all()
                ]);
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Ensure name is unique for the store, excluding the current item group
            if (ItemGroup::where('name', $request->name)
                         ->where('store_id', $request->store_id)
                         ->where('id', '!=', $id)
                         ->whereNull('deleted_at')
                         ->exists()) {
                Log::warning('Duplicate item group name for store during update', [
                    'id' => $id,
                    'name' => $request->name,
                    'store_id' => $request->store_id,
                    'request' => $request->all()
                ]);
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => ['name' => ['The name has already been taken for this store.']]
                ], 422);
            }

            $itemGroup->update([
                'name' => $request->name,
                'store_id' => $request->store_id,
                'updated_at' => $request->updated_at ?? now()
            ]);

            Log::info('Item group updated successfully', [
                'id' => $itemGroup->id,
                'name' => $itemGroup->name,
                'store_id' => $itemGroup->store_id,
                'request' => $request->all()
            ]);

            return response()->json([
                'data' => $itemGroup,
                'message' => 'Item group updated successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating item group', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return response()->json([
                'message' => 'Error updating item group',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified item group (soft delete).
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $itemGroup = ItemGroup::whereNull('deleted_at')->find($id);

            if (!$itemGroup) {
                Log::warning('Item group not found for deletion', [
                    'id' => $id
                ]);
                return response()->json([
                    'message' => 'Item group not found'
                ], 404);
            }

            $itemGroup->delete();

            Log::info('Item group soft deleted successfully', [
                'id' => $id,
                'name' => $itemGroup->name,
                'store_id' => $itemGroup->store_id
            ]);

            return response()->json([
                'message' => 'Item group deleted successfully'
            ], 200);
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Error deleting item group due to references', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'id' => $id
            ]);
            return response()->json([
                'message' => 'Cannot delete item group due to existing references'
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error deleting item group', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'id' => $id
            ]);
            return response()->json([
                'message' => 'Error deleting item group',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}