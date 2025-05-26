<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UnitController extends Controller
{
    /**
     * Display a listing of the units.
     */
    public function index()
    {
        $units = Unit::all();
        return response()->json([
            'data' => $units,
            'message' => 'success'
        ], 200);
    }

    /**
     * Display the specified unit.
     */
    public function show($id)
    {
        $unit = Unit::find($id);
        if (!$unit) {
            return response()->json([
                'message' => 'Unit not found'
            ], 404);
        }

        return response()->json([
            'data' => $unit,
            'message' => 'success'
        ], 200);
    }

    /**
     * Store a newly created unit in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:units,name'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $unit = Unit::create([
            'name' => $request->name
        ]);

        return response()->json([
            'data' => $unit,
            'message' => 'Unit created successfully'
        ], 201);
    }

    /**
     * Update the specified unit in storage.
     */
    public function update(Request $request, $id)
    {
        $unit = Unit::find($id);
        if (!$unit) {
            return response()->json([
                'message' => 'Unit not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:units,name,' . $id
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $unit->update([
            'name' => $request->name
        ]);

        return response()->json([
            'data' => $unit,
            'message' => 'Unit updated successfully'
        ], 200);
    }

    /**
     * Remove the specified unit from storage.
     */
    public function destroy($id)
    {
        $unit = Unit::find($id);
        if (!$unit) {
            return response()->json([
                'message' => 'Unit not found'
            ], 404);
        }

        $unit->delete();

        return response()->json([
            'message' => 'Unit deleted successfully'
        ], 200);
    }
}