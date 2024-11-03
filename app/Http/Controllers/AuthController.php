<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Traits\ResponseTrait;

class AuthController extends Controller
{
    use ResponseTrait;

    // Register method
    public function register(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);

        // Return validation errors if they exist
        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }

        // Create a new user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Generate a JWT token for the user
        $token = JWTAuth::fromUser($user);

        // Return success response with user data and token
        return $this->success([
            'user' => $user,
            'token' => $token,
        ], 'User registered successfully', 201);
    }

    // Login method
    public function login(Request $request)
    {
        // Retrieve credentials
        $credentials = $request->only('email', 'password');

        // Attempt to authenticate the user
        if (!$token = JWTAuth::attempt($credentials)) {
            return $this->error('Invalid credentials', 401);
        }

        // Return success response with token and its details
        return $this->success([
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,  // Retrieve the configured TTL directly
        ], 'Login successful');
    }

    // Method to get authenticated user details
    public function user()
    {
        // Check if the user is authenticated
        if (!auth()->check()) {
            return $this->error('Unauthorized access. Please log in.', 401);
        }

        // Return success response with authenticated user's details
        return $this->success(auth()->user(), 'User retrieved successfully');
    }

    // Logout method
    public function logout()
    {
        if (!auth()->check()) {
            return $this->error('Unauthorized access. Please log in.', 401);
        }

        auth()->logout();

        // Return success response for logout
        return $this->success(null, 'Successfully logged out');
    }
}
