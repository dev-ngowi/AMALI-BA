<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class UnitController extends Controller
{
    /**
     * Display a listing of the units with pagination and search.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 10);
            $page = $request->input('page', 1);
            $searchQuery = $request->input('searchQuery');
    
            $query = Unit::query();
    
            if ($searchQuery) {
                $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($searchQuery) . '%']);
            }
    
            $units = $query->paginate($perPage);
    
            Log::info('Fetched units', [
                'query' => $query->toSql(),
                'bindings' => $query->getBindings(),
                'count' => $units->total(),
                'page' => $page,
                'per_page' => $perPage
            ]);
    
            return response()->json([
                'data' => $units->items(),
                'current_page' => $units->currentPage(),
                'per_page' => $units->perPage(),
                'total' => $units->total(),
                'last_page' => $units->lastPage(),
                'message' => 'Units retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve units', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to retrieve units',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified unit.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $unit = Unit::find($id);

            if (!$unit) {
                Log::warning('Unit not found', ['id' => $id]);
                return response()->json([
                    'message' => 'Unit not found'
                ], 404);
            }

            Log::info('Fetched unit', ['id' => $id, 'name' => $unit->name]);

            return response()->json([
                'data' => $unit,
                'message' => 'Unit retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve unit', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to retrieve unit',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created unit in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:units,name'
            ]);

            if ($validator->fails()) {
                Log::error('Validation failed for store unit', [
                    'errors' => $validator->errors(),
                    'request' => $request->all()
                ]);
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $unit = Unit::create([
                'name' => $request->name,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            Log::info('Unit created successfully', [
                'id' => $unit->id,
                'name' => $unit->name
            ]);

            return response()->json([
                'data' => $unit,
                'message' => 'Unit created successfully'
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating unit', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Error creating unit',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified unit in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            $unit = Unit::find($id);

            if (!$unit) {
                Log::warning('Unit not found for update', ['id' => $id]);
                return response()->json([
                    'message' => 'Unit not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:units,name,' . $id
            ]);

            if ($validator->fails()) {
                Log::error('Validation failed for update unit', [
                    'id' => $id,
                    'errors' => $validator->errors(),
                    'request' => $request->all()
                ]);
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $unit->update([
                'name' => $request->name,
                'updated_at' => now()
            ]);

            Log::info('Unit updated successfully', [
                'id' => $unit->id,
                'name' => $unit->name
            ]);

            return response()->json([
                'data' => $unit,
                'message' => 'Unit updated successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating unit', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'message' => 'Error updating unit',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified unit from storage (soft delete).
     *
     * @param int $id
     * @return array
     * \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $unit = Unit::find($id);

            if (!$unit) {
                // Log::warning('Unit not found', ['id'] => ['delete' => $id]);
                return response()->json([
                    'message' => 'Unit not found'
                ], 404);
            }

            $unit->delete();

            Log::info('Unit deleted successfully', ['id' => $id, 'name' => $unit->name]);

            return response()->json([
                'message' => 'Unit deleted successfully'
            ], 200);
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Error deleting unit due to references', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Cannot delete unit due to existing references'
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error deleting unit', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Error deleting unit',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
