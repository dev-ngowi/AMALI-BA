<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;


use App\Models\PermissionModule;
use Illuminate\Support\Facades\Validator;

class PermissionModuleController extends Controller
{
    // Display a listing of permission modules with pagination
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 10); // Default to 10 items per page
        $permissionModules = PermissionModule::paginate($perPage);
        
        return response()->json([
            'success' => true,
            'data' => [
                'permission_modules' => $permissionModules->items(),
                'pagination' => [
                    'current_page' => $permissionModules->currentPage(),
                    'total_pages' => $permissionModules->lastPage(),
                    'total_items' => $permissionModules->total(),
                    'per_page' => $permissionModules->perPage(),
                ]
            ]
        ], 200);
    }

    // Store a newly created permission module
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:permission_modules',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $permissionModule = PermissionModule::create($request->only(['name']));
        return response()->json([
            'success' => true,
            'data' => ['permission_module' => $permissionModule]
        ], 201);
    }

    // Display a specific permission module
    public function show($id)
    {
        $permissionModule = PermissionModule::find($id);
        if (!$permissionModule) {
            return response()->json([
                'success' => false,
                'message' => 'Permission module not found'
            ], 404);
        }
        return response()->json([
            'success' => true,
            'data' => ['permission_module' => $permissionModule]
        ], 200);
    }

    // Update a specific permission module
    public function update(Request $request, $id)
    {
        $permissionModule = PermissionModule::find($id);
        if (!$permissionModule) {
            return response()->json([
                'success' => false,
                'message' => 'Permission module not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:permission_modules,name,' . $id,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $permissionModule->update($request->only(['name']));
        return response()->json([
            'success' => true,
            'data' => ['permission_module' => $permissionModule]
        ], 200);
    }

    // Delete a specific permission module (soft delete)
    public function destroy($id)
    {
        $permissionModule = PermissionModule::find($id);
        if (!$permissionModule) {
            return response()->json([
                'success' => false,
                'message' => 'Permission module not found'
            ], 404);
        }

        $permissionModule->delete();
        return response()->json([
            'success' => true,
            'message' => 'Permission module soft deleted'
        ], 200);
    }

    // Restore a soft-deleted permission module
    public function restore($id)
    {
        $permissionModule = PermissionModule::withTrashed()->find($id);
        if (!$permissionModule) {
            return response()->json([
                'success' => false,
                'message' => 'Permission module not found'
            ], 404);
        }

        if (!$permissionModule->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'Permission module is not deleted'
            ], 400);
        }

        $permissionModule->restore();
        return response()->json([
            'success' => true,
            'data' => ['permission_module' => $permissionModule],
            'message' => 'Permission module restored'
        ], 200);
    }
    //
}
