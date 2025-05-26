<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VendorController extends Controller
{
    public function index()
    {
        $data = Vendor::all();
        return response()->json([
            'data' => $data,
            'message' => 'success'
        ], 200);
    }

    public function store(Request $request)
    {
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
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $vendor = Vendor::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'city_id' => $request->city_id,
            'state' => $request->state,
            'postal_code' => $request->postal_code,
            'country_id' => $request->country_id,
            'contact_person' => $request->contact_person,
            'tin' => $request->tin,
            'vrn' => $request->vrn,
            'status' => $request->status ?? 'active'
        ]);

        return response()->json([
            'data' => $vendor,
            'message' => 'Vendor created successfully'
        ], 201);
    }
}