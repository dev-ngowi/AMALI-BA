<?php

namespace App\Http\Controllers;

use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserRoleController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 10);
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

    public function check(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'role_id' => 'required|exists:roles,id',
            'id' => 'sometimes|exists:user_roles,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $query = UserRole::where('user_id', $request->user_id)->where('role_id', $request->role_id);
        if ($request->has('id')) {
            $query->where('id', '!=', $request->id);
        }

        $exists = $query->exists();

        return response()->json([
            'success' => true,
            'exists' => $exists
        ], 200);
    }
}