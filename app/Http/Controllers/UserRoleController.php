<?php

namespace App\Http\Controllers;

use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserRoleController extends Controller
{
    // Display a listing of user roles with pagination
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 10); // Default to 10 items per page
        $userRoles = UserRole::with(['user', 'role'])->paginate($perPage);
        
        return response()->json([
            'success' => true,
            'data' => [
                'user_roles' => $userRoles->items(),
                'pagination' => [
                    'current_page' => $userRoles->currentPage(),
                    'total_pages' => $userRoles->lastPage(),
                    'total_items' => $userRoles->total(),
                    'per_page' => $userRoles->perPage(),
                ]
            ]
        ], 200);
    }

    // Store a newly created user role
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'role_id' => 'required|exists:roles,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $userRole = UserRole::create($request->only(['user_id', 'role_id']));
        $userRole->load(['user', 'role']);
        return response()->json([
            'success' => true,
            'data' => ['user_role' => $userRole]
        ], 201);
    }

    // Display a specific user role
    public function show($id)
    {
        $userRole = UserRole::with(['user', 'role'])->find($id);
        if (!$userRole) {
            return response()->json([
                'success' => false,
                'message' => 'User role not found'
            ], 404);
        }
        return response()->json([
            'success' => true,
            'data' => ['user_role' => $userRole]
        ], 200);
    }

    // Update a specific user role
    public function update(Request $request, $id)
    {
        $userRole = UserRole::find($id);
        if (!$userRole) {
            return response()->json([
                'success' => false,
                'message' => 'User role not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'role_id' => 'required|exists:roles,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $userRole->update($request->only(['user_id', 'role_id']));
        $userRole->load(['user', 'role']);
        return response()->json([
            'success' => true,
            'data' => ['user_role' => $userRole]
        ], 200);
    }

    // Delete a specific user role (soft delete)
    public function destroy($id)
    {
        $userRole = UserRole::find($id);
        if (!$userRole) {
            return response()->json([
                'success' => false,
                'message' => 'User role not found'
            ], 404);
        }

        $userRole->delete();
        return response()->json([
            'success' => true,
            'message' => 'User role soft deleted'
        ], 200);
    }

    // Restore a soft-deleted user role
    public function restore($id)
    {
        $userRole = UserRole::withTrashed()->find($id);
        if (!$userRole) {
            return response()->json([
                'success' => false,
                'message' => 'User role not found'
            ], 404);
        }

        if (!$userRole->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'User role is not deleted'
            ], 400);
        }

        $userRole->restore();
        return response()->json([
            'success' => true,
            'data' => ['user_role' => $userRole],
            'message' => 'User role restored'
        ], 200);
    }
}