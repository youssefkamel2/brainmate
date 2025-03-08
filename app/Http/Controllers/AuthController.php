<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\Project;
use Illuminate\Support\Str;
use App\Models\Notification;
use Illuminate\Http\Request;
use App\Traits\ResponseTrait;
use App\Events\NotificationSent;
use App\Models\PasswordResetCode;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Validator;
use App\Notifications\ResetCodeNotification;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\TeamInvitationNotification;

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
            'password' => 'required|string|min:9|confirmed',
            'phone' => 'nullable|string|max:20',
            'gender' => 'nullable|string|in:Male,Female,Other',
            'birthdate' => 'nullable|date',
            'bio' => 'nullable|string|max:500',
            'position' => 'nullable|string|max:255',
            'level' => 'nullable|string|max:255',
            'skills' => 'nullable|array',
            'skills.*' => 'string|max:255',
            'facebook' => 'nullable|string|max:255',
            'instagram' => 'nullable|string|max:255',
            'linkedin' => 'nullable|string|max:255',
            'website' => 'nullable|string|max:255',
            'experience_years' => 'nullable|integer|min:0',
            'invitation_token' => 'nullable|string', // Add invitation token
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Normalize the skills field
        $skills = $request->skills;
        if (is_string($skills)) {
            $skills = explode(',', $skills);
        }

        // Prepare the social field
        $social = [
            'facebook' => $request->facebook ?? null,
            'instagram' => $request->instagram ?? null,
            'linkedin' => $request->linkedin ?? null,
            'website' => $request->website ?? null,
        ];

        // Create a new user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'gender' => $request->gender,
            'birthdate' => $request->birthdate,
            'bio' => $request->bio,
            'position' => $request->position,
            'level' => $request->level,
            'skills' => $skills ? implode(',', $skills) : null,
            'social' => $social,
            'experience_years' => $request->experience_years,
        ]);

        // Handle invitation token (if provided)
        if ($request->invitation_token) {
            $this->handleInvitationToken($user, $request->invitation_token);
        }

        // Generate a JWT token for the user
        $token = JWTAuth::fromUser($user);

        // Return success response with user data and token
        return $this->success([
            'user' => $user,
            'token' => $token,
        ], 'User registered successfully', 201);
    }

    // Handle invitation token during signup
    protected function handleInvitationToken(User $user, string $token)
    {
        // Find the invitation by token
        $invitation = DB::table('invitations')
            ->where('token', $token)
            ->first();

        if (!$invitation) {
            return; // Invalid or expired token
        }

        // Associate the invitation with the new user
        DB::table('invitations')
            ->where('id', $invitation->id)
            ->update(['invited_user_id' => $user->id]);

        // Create a system notification for the new user
        $team = Team::find($invitation->team_id);
        $project = Project::find($invitation->project_id);
        $role = Role::find($invitation->role_id)->name;

        $notification = Notification::create([
            'user_id' => $user->id,
            'message' => "You have been invited to join the team '{$team->name}' in the project '{$project->name}' as a {$role}.",
            'type' => 'invitation',
            'read' => false,
            'action_url' => url("https://brainmate.vercel.app/team-invitation-confirm?token={$invitation->token}"),
            'metadata' => [
                'team_id' => $team->id,
                'team_name' => $team->name,
                'project_id' => $project->id,
                'project_name' => $project->name,
                'role' => $role,
                'token' => $invitation->token,
            ],
        ]);

        // Broadcast the notification
        event(new NotificationSent($notification));
    }

    // Login method
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (!$token = JWTAuth::attempt($credentials)) {
            return $this->error('Invalid credentials', 401);
        }

        $user = auth()->user();

        return $this->success([
            'token' => $token,
            'token_type' => 'bearer',
            'user' => $user,
        ], 'Login successful');
    }


    public function sendResetLink(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
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
            'password' => 'required|string|min:9|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
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

    // for mobile app // send reset code

    public function sendResetCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Generate a random 6-digit numeric code
        $code = random_int(100000, 999999);

        // Hash the code for storage
        $hashedCode = Hash::make($code);

        // Store code in the database
        PasswordResetCode::updateOrCreate(
            ['email' => $request->email],
            [
                'reset_code' => $hashedCode,
                'expires_at' => now()->addMinutes(15),
                'attempts' => 0,
            ]
        );

        // Send the code via email
        $user = User::where('email', $request->email)->first();
        $user->notify(new ResetCodeNotification($code)); // Custom notification

        return $this->success(null, 'Reset code sent to your email.');
    }


    public function verifyResetCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:password_reset_codes,email',
            'reset_code' => 'required|numeric|digits:6',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $resetCode = PasswordResetCode::where('email', $request->email)->first();

        // Check if reset code exists and has not expired
        if (!$resetCode || $resetCode->expires_at < now()) {
            return $this->error('Invalid or expired reset code.', 400);
        }

        // Verify the code
        if (!Hash::check($request->reset_code, $resetCode->reset_code)) {
            $resetCode->increment('attempts');

            // Check if attempts exceeded limit
            if ($resetCode->attempts >= 5) {
                $resetCode->delete(); // Invalidate the reset code
                return $this->error('Too many attempts. Please request a new code.', 429);
            }

            return $this->error('Invalid reset code.', 400);
        }

        // Mark code as used
        $resetCode->delete();

        return $this->success(null, 'Code verified. Proceed to reset password.');
    }

    public function resetPasswordApp(Request $request)
    {
        // Validate the input
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string|min:9|confirmed',
            'password_confirmation' => 'required|string|min:9', 
        ]);

        // Return validation errors, if any
        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Fetch the user by email
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return $this->error('User not found.', 404);
        }

        // Update the password
        $user->password = Hash::make($request->password);
        $user->save();

        // Return success response
        return $this->success(null, 'Password has been reset successfully.');
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
        try {
            // Invalidate the token
            JWTAuth::invalidate(JWTAuth::getToken());

            // Return success response
            return $this->success(null, 'Successfully logged out.');
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return $this->error('Token is already invalidated.', 401);
        } catch (\Exception $e) {
            return $this->error('An error occurred during logout.', 500);
        }
    }

    // google login 

    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    {

        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            print_r($googleUser);die;
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
            $frontendUrl = env('FRONTEND_URL', 'https://brainmate.vercel.app/login');
            return redirect()->to("{$frontendUrl}?token={$token}");
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error during authentication: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function validateToken(Request $request)
    {
        try {
            // Authenticate and fetch the user
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return $this->error('Invalid token or user not found.', 401);
            }

            // Return success response with the same token
            return $this->success([
                'token' => JWTAuth::getToken()->get(),
                'token_type' => 'bearer',
                'user' => $user, // Include full user data
            ], 'Token is valid.');
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return $this->error('Token expired.', 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return $this->error('Token is invalid.', 401);
        } catch (\Exception $e) {
            return $this->error('Could not authenticate token.', 500);
        }
    }
}
