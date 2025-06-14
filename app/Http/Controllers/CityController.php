<?php

namespace App\Http\Controllers;

use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Response;

class CityController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = City::query();

            if ($request->has('name') && $request->has('country_id')) {
                $query->where('name', $request->input('name'))
                      ->where('country_id', $request->input('country_id'));
            } elseif ($request->has('name')) {
                $query->where('name', $request->input('name'));
            } elseif ($request->has('country_id')) {
                $query->where('country_id', $request->input('country_id'));
            }

            $data = $query->get();
            Log::info('Fetched cities', [
                'query' => $query->toSql(),
                'bindings' => $query->getBindings(),
                'count' => $data->count()
            ]);

            return response()->json([
                'data' => $data,
                'message' => 'success'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve cities', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to retrieve cities',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'country_id' => 'required|exists:countries,id',
            'name' => 'required|string|max:255|unique:cities,name,NULL,id,country_id,' . $request->input('country_id'),
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180'
        ]);

        if ($validator->fails()) {
            Log::error('Validation failed for city creation', [
                'errors' => $validator->errors(),
                'request' => $request->all()
            ]);
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $city = City::create([
                'country_id' => $request->country_id,
                'name' => $request->name,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            Log::info('City created successfully', ['city' => $city]);

            return response()->json([
                'data' => $city,
                'message' => 'City created successfully'
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create city', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            return response()->json([
                'message' => 'Failed to create city',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show($id)
    {
        try {
            $city = City::find($id);
            if (!$city) {
                Log::warning('City not found', ['id' => $id]);
                return response()->json([
                    'message' => 'City not found'
                ], 404);
            }
            return response()->json([
                'data' => $city,
                'message' => 'success'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve city', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'message' => 'Failed to retrieve city',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $city = City::find($id);
            if (!$city) {
                Log::warning('City not found', ['id' => $id]);
                return response()->json([
                    'message' => 'City not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'country_id' => 'required|exists:countries,id',
                'name' => 'required|string|max:255|unique:cities,name,' . $id . ',id,country_id,' . $request->input('country_id'),
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180'
            ]);

            if ($validator->fails()) {
                Log::error('Validation failed for city update', [
                    'id' => $id,
                    'errors' => $validator->errors(),
                    'request' => $request->all()
                ]);
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $city->update([
                'country_id' => $request->country_id,
                'name' => $request->name,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'updated_at' => now()
            ]);
            Log::info('City updated successfully', ['city' => $city]);

            return response()->json([
                'data' => $city,
                'message' => 'City updated successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to update city', [
                'id' => $id,
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            return response()->json([
                'message' => 'Failed to update city',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy($id)
    {
        try {
            $city = City::find($id);
            if (!$city) {
                Log::warning('City not found', ['id' => $id]);
                return response()->json([
                    'message' => 'City not found'
                ], 404);
            }
            $city->delete();
            Log::info('City deleted successfully', ['id' => $id]);
            return response()->json([
                'message' => 'City deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to delete city', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'message' => 'Failed to delete city',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}