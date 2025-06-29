<?php

namespace App\Http\Controllers;

use App\Models\VirtualDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VirtualDeviceController extends Controller
{
    // Display a listing of virtual devices with pagination
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 10); // Default to 10 items per page
        $devices = VirtualDevice::with('company')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'devices' => $devices->items(),
                'pagination' => [
                    'current_page' => $devices->currentPage(),
                    'total_pages' => $devices->lastPage(),
                    'total_items' => $devices->total(),
                    'per_page' => $devices->perPage(),
                ]
            ]
        ], 200);
    }

    // Store a newly created virtual device
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->only(['company_id', 'name', 'is_active']);
        $device = VirtualDevice::create($data);
        $device->load('company');

        return response()->json([
            'success' => true,
            'data' => ['device' => $device]
        ], 201);
    }

    // Display a specific virtual device
    public function show($id)
    {
        $device = VirtualDevice::with('company')->find($id);
        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Virtual device not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => ['device' => $device]
        ], 200);
    }

    // Update a specific virtual device
    public function update(Request $request, $id)
    {
        $device = VirtualDevice::find($id);
        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Virtual device not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->only(['company_id', 'name', 'is_active']);
        $device->update($data);
        $device->load('company');

        return response()->json([
            'success' => true,
            'data' => ['device' => $device]
        ], 200);
    }

    // Delete a specific virtual device (soft delete)
    public function destroy($id)
    {
        $device = VirtualDevice::find($id);
        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Virtual device not found'
            ], 404);
        }

        $device->delete();
        return response()->json([
            'success' => true,
            'message' => 'Virtual device soft deleted'
        ], 200);
    }

    // Restore a soft-deleted virtual device
    public function restore($id)
    {
        $device = VirtualDevice::withTrashed()->find($id);
        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Virtual device not found'
            ], 404);
        }

        if (!$device->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'Virtual device is not deleted'
            ], 400);
        }

        $device->restore();
        $device->load('company');

        return response()->json([
            'success' => true,
            'data' => ['device' => $device],
            'message' => 'Virtual device restored'
        ], 200);
    }
}