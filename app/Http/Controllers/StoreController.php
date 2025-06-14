<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StoreController extends Controller
{
    public function index()
    {
        $data = Store::with('manager')->get()->map(function ($store) {
            return [
                'id' => $store->id,
                'name' => $store->name,
                'location' => $store->location,
                'manager_id' => $store->manager_id,
                'manager_name' => $store->manager ? $store->manager->username : null,
                'created_at' => $store->created_at,
                'updated_at' => $store->updated_at,
            ];
        });
        return response()->json([
            'data' => $data,
            'message' => 'success'
        ], 200);
    }

    public function show($id)
    {
        $store = Store::with('manager')->find($id);
        if (!$store) {
            return response()->json([
                'message' => 'Store not found'
            ], 404);
        }

        return response()->json([
            'data' => [
                'id' => $store->id,
                'name' => $store->name,
                'location' => $store->location,
                'manager_id' => $store->manager_id,
                'manager_name' => $store->manager ? $store->manager->username : null,
                'created_at' => $store->created_at,
                'updated_at' => $store->updated_at,
            ],
            'message' => 'success'
        ], 200);
    }

    public function store(Request $request)
    {
        // Check if store limit is reached
        if (Store::count() >= 2) {
            return response()->json([
                'message' => 'Cannot create more than two stores'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'manager_id' => 'nullable|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $store = Store::create([
            'name' => $request->name,
            'location' => $request->location,
            'manager_id' => $request->manager_id
        ]);

        $store->load('manager');
        return response()->json([
            'data' => [
                'id' => $store->id,
                'name' => $store->name,
                'location' => $store->location,
                'manager_id' => $store->manager_id,
                'manager_name' => $store->manager ? $store->manager->name : null,
                'created_at' => $store->created_at,
                'updated_at' => $store->updated_at,
            ],
            'message' => 'Store created successfully'
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $store = Store::find($id);
        if (!$store) {
            return response()->json([
                'message' => 'Store not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'manager_id' => 'nullable|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $store->update([
            'name' => $request->name,
            'location' => $request->location,
            'manager_id' => $request->manager_id
        ]);

        $store->load('manager');
        return response()->json([
            'data' => [
                'id' => $store->id,
                'name' => $store->name,
                'location' => $store->location,
                'manager_id' => $store->manager_id,
                'manager_name' => $store->manager ? $store->manager->name : null,
                'created_at' => $store->created_at,
                'updated_at' => $store->updated_at,
            ],
            'message' => 'Store updated successfully'
        ], 200);
    }

    public function destroy($id)
    {
        $store = Store::find($id);
        if (!$store) {
            return response()->json([
                'message' => 'Store not found'
            ], 404);
        }

        $store->delete();

        return response()->json([
            'message' => 'Store deleted successfully'
        ], 200);
    }

    public function users()
    {
        $users = User::all()->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
            ];
        });
        return response()->json([
            'data' => $users,
            'message' => 'success'
        ], 200);
    }
}