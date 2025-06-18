<?php

namespace App\Http\Controllers;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller
{
    // Display a listing of roles with pagination
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 10); // Default to 10 items per page
        $roles = Role::paginate($perPage);
        
        return response()->json([
            'success' => true,
            'data' => [
                'roles' => $roles->items(),
                'pagination' => [
                    'current_page' => $roles->currentPage(),
                    'total_pages' => $roles->lastPage(),
                    'total_items' => $roles->total(),
                    'per_page' => $roles->perPage(),
                ]
            ]
        ], 200);
    }

    // Store a newly created role
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'role_name' => 'required|string|unique:roles',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $role = Role::create($request->only(['role_name', 'description']));
        return response()->json([
            'success' => true,
            'data' => ['role' => $role]
        ], 201);
    }

    // Display a specific role
    public function show($id)
    {
        $role = Role::find($id);
        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found'
            ], 404);
        }
        return response()->json([
            'success' => true,
            'data' => ['role' => $role]
        ], 200);
    }

    // Update a specific role
    public function update(Request $request, $id)
    {
        $role = Role::find($id);
        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'role_name' => 'required|string|unique:roles,role_name,' . $id,
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $role->update($request->only(['role_name', 'description']));
        return response()->json([
            'success' => true,
            'data' => ['role' => $role]
        ], 200);
    }

    // Delete a specific role
    public function destroy($id)
    {
        $role = Role::find($id);
        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found'
            ], 404);
        }

        $role->delete();
        return response()->json([
            'success' => true,
            'message' => 'Role deleted'
        ], 200);
    }
}