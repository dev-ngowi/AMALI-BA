<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function index()
    {
        try {
            $users = User::all()->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->fullname,
                    'username' => $user->username,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'email_verified_at' => $user->email_verified_at?->toDateTimeString(),
                    'phone_verified_at' => $user->phone_verified_at?->toDateTimeString(),
                    'pin' => $user->pin,
                    'created_at' => $user->created_at?->toDateTimeString(),
                    'updated_at' => $user->updated_at?->toDateTimeString(),
                ];
            });

            Log::info('Fetched users', ['count' => $users->count(), 'users' => $users->toArray()]);

            return response()->json([
                'data' => $users,
                'message' => 'Success'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to fetch users', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'message' => 'Failed to fetch users',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        Log::info('Received POST /users request', ['input' => $request->all()]);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'nullable|email|max:255|unique:users,email',
            'phone' => 'nullable|string|max:255',
            'password' => 'required|string|min:6', // Changed to required
            'pin' => 'nullable|integer|digits:4',
        ]);

        if ($validator->fails()) {
            Log::error('Validation failed', ['errors' => $validator->errors()]);
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Check if user exists by username, email, or phone
            $existingUserQuery = User::query();
            if ($request->username) {
                $existingUserQuery->where('username', $request->username);
            }
            if ($request->email) {
                $existingUserQuery->orWhere('email', $request->email);
            }
            if ($request->phone) {
                $existingUserQuery->orWhere('phone', $request->phone);
            }
            $existingUser = $existingUserQuery->first();

            if ($existingUser) {
                Log::info('User already exists', ['user_id' => $existingUser->id]);
                return response()->json([
                    'data' => [
                        'id' => $existingUser->id,
                        'name' => $existingUser->fullname,
                        'username' => $existingUser->username,
                        'email' => $existingUser->email,
                        'phone' => $existingUser->phone,
                        'email_verified_at' => $existingUser->email_verified_at?->toDateTimeString(),
                        'phone_verified_at' => $existingUser->phone_verified_at?->toDateTimeString(),
                        'pin' => $existingUser->pin,
                        'created_at' => $existingUser->created_at?->toDateTimeString(),
                        'updated_at' => $existingUser->updated_at?->toDateTimeString(),
                    ],
                    'message' => 'User already exists'
                ], 200);
            }

            $user = User::create([
                'fullname' => $request->name,
                'username' => $request->username,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'pin' => $request->pin,
                'remember_token' => Str::random(60),
            ]);

            Log::info('User created successfully', ['user_id' => $user->id]);

            return response()->json([
                'data' => [
                    'id' => $user->id,
                    'name' => $user->fullname,
                    'username' => $user->username,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'email_verified_at' => $user->email_verified_at?->toDateTimeString(),
                    'phone_verified_at' => $user->phone_verified_at?->toDateTimeString(),
                    'pin' => $user->pin,
                    'created_at' => $user->created_at?->toDateTimeString(),
                    'updated_at' => $user->updated_at?->toDateTimeString(),
                ],
                'message' => 'User created successfully'
            ], 201);
        } catch (QueryException $e) {
            Log::error('Database error creating user', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'message' => 'Failed to create user due to database error',
                'error' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            Log::error('Failed to create user', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'message' => 'Failed to create user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function login(Request $request)
    {
        Log::info('Received POST /login request', [
            'input' => $request->all(),
            'raw_username' => $request->username,
            'username_length' => strlen($request->username)
        ]);

        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            Log::error('Validation failed for login', ['errors' => $validator->errors()]);
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::where('username', $request->username)->first();

            Log::info('User lookup', [
                'input_username' => $request->username,
                'user_found' => $user ? true : false,
                'db_username' => $user ? $user->username : null,
                'password_hash' => $user ? $user->password : null,
                'password_match' => $user ? Hash::check($request->password, $user->password) : false
            ]);

            if (!$user || !Hash::check($request->password, $user->password)) {
                Log::warning('Invalid login attempt', [
                    'username' => $request->username,
                    'user_exists' => $user ? true : false,
                    'password_match' => $user ? Hash::check($request->password, $user->password) : false
                ]);
                return response()->json([
                    'message' => 'Invalid username or password'
                ], 401);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            Log::info('User logged in successfully', ['user_id' => $user->id]);

            return response()->json([
                'data' => [
                    'id' => $user->id,
                    'name' => $user->fullname,
                    'username' => $user->username,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'token' => $token,
                ],
                'message' => 'Login successful'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to login user', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'message' => 'Failed to login',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}