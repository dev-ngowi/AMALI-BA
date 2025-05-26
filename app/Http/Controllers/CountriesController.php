<?php

namespace App\Http\Controllers;

use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CountriesController extends Controller
{
    public function index()
    {
        $data = Country::all();
        return response()->json([
            'data' => $data,
            'message' => 'success'
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:countries,name',
            'code' => 'nullable|string|max:10'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $country = Country::create([
            'name' => $request->name,
            'code' => $request->code
        ]);

        return response()->json([
            'data' => $country,
            'message' => 'Country created successfully'
        ], 201);
    }
}