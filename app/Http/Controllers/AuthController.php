<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Traits\ResponseTrait;
use Illuminate\Support\Facades\Password;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;


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

    public function sendResetLink(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }

        $user = User::where('email', $request->email)->first();
        $token = Password::createToken($user);

        // Send the notification
        $user->notify(new ResetPasswordNotification($token));

        return $this->success(null, 'Password reset link sent to your email');
    }

    // Reset Password
    public function reset(Request $request)
    {
        // Validate request data
        $validator = Validator::make($request->all(), [
            'token' => 'required',
            'email' => 'required|string|email|exists:users,email',
            'password' => 'required|string|min:6|confirmed',
        ]);
    
        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }
    
        // Verify token validity
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->save();
            }
        );
    
        if ($status == Password::PASSWORD_RESET) {
            return $this->success(null, 'Password has been reset successfully');
        } else {
            return $this->error(['token' => 'Invalid or expired token'], 400);
        }
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

    // google login 

    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }


//     public function handleGoogleCallback()
// {
//     try {
//         // Use stateless to avoid session-related issues
//         // @ts-ignore
//         $googleUser = Socialite::driver('google')->stateless()->user();

//         // Your logic to find or create the user in your database
//         $user = User::firstOrCreate(
//             ['email' => $googleUser->getEmail()],
//             [
//                 'name' => $googleUser->getName(),
//                 'password' => bcrypt(Str::random(16)),
//             ]
//         );

//         // Generate JWT for the user
//         $token = JWTAuth::fromUser($user);

//         return response()->json([
//             'user' => $user,
//             'token' => $token,
//         ], 200);
//     } catch (\Exception $e) {
//         return response()->json([
//             'error' => 'Error during authentication: ' . $e->getMessage(),
//         ], 500);
//     }
//     }



public function handleGoogleCallback()
{
    try {
        $googleUser = Socialite::driver('google')->stateless()->user();

        // Your logic to find or create the user in your database
        $user = User::firstOrCreate(
            ['email' => $googleUser->getEmail()],
            [
                'name' => $googleUser->getName(),
                'password' => bcrypt(Str::random(16)),
            ]
        );

        // Generate JWT for the user
        $token = JWTAuth::fromUser($user);

        // Redirect to React frontend with token
        $frontendUrl = env('FRONTEND_URL', 'https://brainmate.vercel.app/login'); // Replace with your React app URL
        return redirect()->to("{$frontendUrl}?token={$token}");
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Error during authentication: ' . $e->getMessage(),
        ], 500);
    }
}

}
