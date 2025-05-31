<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class VendorController extends Controller
{
    /**
     * Display a listing of the vendors.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $data = Vendor::all();
            return response()->json([
                'data' => $data,
                'message' => 'Success'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching vendors: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error fetching vendors',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified vendor.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $vendor = Vendor::find($id);
            if (!$vendor) {
                Log::warning("Vendor with ID {$id} not found");
                return response()->json([
                    'message' => 'Vendor not found'
                ], 404);
            }
            return response()->json([
                'data' => $vendor,
                'message' => 'Success'
            ], 200);
        } catch (\Exception $e) {
            Log::error("Error fetching vendor ID {$id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error fetching vendor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created vendor in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer|unique:vendors,id',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'required|string',
            'city_id' => 'required|exists:cities,id',
            'state' => 'required|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country_id' => 'required|exists:countries,id',
            'contact_person' => 'nullable|string|max:255',
            'tin' => 'nullable|string|max:50',
            'vrn' => 'nullable|string|max:50',
            'status' => 'nullable|in:active,inactive'
        ]);

        if ($validator->fails()) {
            Log::error('Vendor creation validation failed: ' . json_encode($validator->errors()));
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $request->only([
                'id', 'name', 'email', 'phone', 'address', 'city_id', 'state',
                'postal_code', 'country_id', 'contact_person', 'tin', 'vrn', 'status'
            ]);
            if (!isset($data['status'])) {
                $data['status'] = 'active';
            }

            $vendor = Vendor::create($data);
            Log::info("Vendor created successfully: ID {$vendor->id}, Name: {$vendor->name}");

            return response()->json([
                'data' => $vendor,
                'message' => 'Vendor created successfully'
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating vendor: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error creating vendor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified vendor in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $vendor = Vendor::find($id);
        if (!$vendor) {
            Log::warning("Vendor with ID {$id} not found for update");
            return response()->json([
                'message' => 'Vendor not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'required|string',
            'city_id' => 'required|exists:cities,id',
            'state' => 'required|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country_id' => 'required|exists:countries,id',
            'contact_person' => 'nullable|string|max:255',
            'tin' => 'nullable|string|max:50',
            'vrn' => 'nullable|string|max:50',
            'status' => 'nullable|in:active,inactive'
        ]);

        if ($validator->fails()) {
            Log::error('Vendor update validation failed for ID {$id}: ' . json_encode($validator->errors()));
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $vendor->update($request->only([
                'name', 'email', 'phone', 'address', 'city_id', 'state',
                'postal_code', 'country_id', 'contact_person', 'tin', 'vrn', 'status'
            ]));
            Log::info("Vendor updated successfully: ID {$vendor->id}, Name: {$vendor->name}");

            return response()->json([
                'data' => $vendor,
                'message' => 'Vendor updated successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error("Error updating vendor ID {$id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error updating vendor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified vendor from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $vendor = Vendor::find($id);
            if (!$vendor) {
                Log::warning("Vendor with ID {$id} not found for deletion");
                return response()->json([
                    'message' => 'Vendor not found'
                ], 404);
            }

            $vendor->delete();
            Log::info("Vendor deleted successfully: ID {$id}");

            return response()->json([
                'message' => 'Vendor deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error("Error deleting vendor ID {$id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error deleting vendor',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}