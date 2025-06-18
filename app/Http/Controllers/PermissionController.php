<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Permission;
use Illuminate\Support\Facades\Validator;

class PermissionController extends Controller
{
    // Display a listing of permissions with pagination
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 10); // Default to 10 items per page
        $permissions = Permission::with(['role', 'module'])->paginate($perPage);
        
        return response()->json([
            'success' => true,
            'data' => [
                'permissions' => $permissions->items(),
                'pagination' => [
                    'current_page' => $permissions->currentPage(),
                    'total_pages' => $permissions->lastPage(),
                    'total_items' => $permissions->total(),
                    'per_page' => $permissions->perPage(),
                ]
            ]
        ], 200);
    }

    // Store a newly created permission
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'role_id' => 'required|exists:roles,id',
            'module_id' => 'required|exists:permission_modules,id',
            'can_create' => 'boolean',
            'can_read' => 'boolean',
            'can_update' => 'boolean',
            'can_delete' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $permission = Permission::create($request->only([
            'role_id', 'module_id', 'can_create', 'can_read', 'can_update', 'can_delete'
        ]));
        $permission->load(['role', 'module']);
        return response()->json([
            'success' => true,
            'data' => ['permission' => $permission]
        ], 201);
    }

    // Display a specific permission
    public function show($id)
    {
        $permission = Permission::with(['role', 'module'])->find($id);
        if (!$permission) {
            return response()->json([
                'success' => false,
                'message' => 'Permission not found'
            ], 404);
        }
        return response()->json([
            'success' => true,
            'data' => ['permission' => $permission]
        ], 200);
    }

    // Update a specific permission
    public function update(Request $request, $id)
    {
        $permission = Permission::find($id);
        if (!$permission) {
            return response()->json([
                'success' => false,
                'message' => 'Permission not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'role_id' => 'required|exists:roles,id',
            'module_id' => 'required|exists:permission_modules,id',
            'can_create' => 'boolean',
            'can_read' => 'boolean',
            'can_update' => 'boolean',
            'can_delete' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $permission->update($request->only([
            'role_id', 'module_id', 'can_create', 'can_read', 'can_update', 'can_delete'
        ]));
        $permission->load(['role', 'module']);
        return response()->json([
            'success' => true,
            'data' => ['permission' => $permission]
        ], 200);
    }

    // Delete a specific permission (soft delete)
    public function destroy($id)
    {
        $permission = Permission::find($id);
        if (!$permission) {
            return response()->json([
                'success' => false,
                'message' => 'Permission not found'
            ], 404);
        }

        $permission->delete();
        return response()->json([
            'success' => true,
            'message' => 'Permission soft deleted'
        ], 200);
    }

    // Restore a soft-deleted permission
    public function restore($id)
    {
        $permission = Permission::withTrashed()->find($id);
        if (!$permission) {
            return response()->json([
                'success' => false,
                'message' => 'Permission not found'
            ], 404);
        }

        if (!$permission->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'Permission is not deleted'
            ], 400);
        }

        $permission->restore();
        return response()->json([
            'success' => true,
            'data' => ['permission' => $permission],
            'message' => 'Permission restored'
        ], 200);
    }
}