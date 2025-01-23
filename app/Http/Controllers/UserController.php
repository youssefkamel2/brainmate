<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Traits\ResponseTrait;

class UserController extends Controller
{
    use ResponseTrait;

    /**
     * Get the authenticated user's profile.
     */
    public function getProfile()
    {
        $user = Auth::user();
    
        $numberOfCompletedTasks = $user->tasks()->where('status', '1')->count();
    
        $numberOfTeams = $user->taskMembers()->distinct('team_id')->count('team_id');
    
        $numberOfProjects = $user->taskMembers()->distinct('project_id')->count('project_id');
    
        $user->number_of_completed_tasks = $numberOfCompletedTasks;
        $user->number_of_teams = $numberOfTeams;
        $user->number_of_projects = $numberOfProjects;
    
        return $this->success([
            'user' => $user,
        ], 'User profile retrieved successfully.');
    }

    /**
     * Update the authenticated user's profile.
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();
    
        // Validate the request data
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
            'position' => 'sometimes|string|max:255',
            'level' => 'sometimes|string|max:255',
            'skills' => 'required|array', // Allow skills to be an array
            'skills.*' => 'string|max:255', // Each skill must be a string
            'social' => 'nullable|string',
            'experience_years' => 'sometimes|integer|min:0',
        ]);
    
        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }
    
        $skills = null;
    
        if ($request->has('skills')) {
            $skills = $request->skills;
            if (is_string($skills)) {
                $skills = explode(',', $skills);
            }
        }
    
        $user->update([
            'name' => $request->input('name', $user->name),
            'email' => $request->input('email', $user->email),
            'position' => $request->input('position', $user->position),
            'level' => $request->input('level', $user->level),
            'skills' => $skills ? implode(',', $skills) : $user->skills, // Store as comma-separated string
            'social' => $request->input('social', $user->social),
            'experience_years' => $request->input('experience_years', $user->experience_years),
        ]);
    
        return $this->success([
            'user' => $user,
        ], 'User profile updated successfully.');
    }
    /**
     * Update the authenticated user's password.
     */
    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        // Validate the request data
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }

        // Verify the current password
        if (!Hash::check($request->current_password, $user->password)) {
            return $this->error(['current_password' => ['The current password is incorrect.']], 422);
        }

        // Update the user's password
        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return $this->success([], 'Password updated successfully.');
    }
}