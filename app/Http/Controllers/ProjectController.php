<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Project;
use Illuminate\Http\Request;
use App\Traits\ResponseTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ProjectController extends Controller
{

    use ResponseTrait;

    public function getUserProjects(Request $request)
    {
        // Get the authenticated user from the JWT token
        $user = Auth::user();

        // Retrieve all projects associated with the user
        $projectIds = $user->projects()
            ->distinct('project_id')
            ->pluck('project_id');

        // Step 2: Fetch the full project details for the distinct project IDs
        $projects = Project::whereIn('id', $projectIds)->get();


        return $this->success(['projects' => $projects], 'Projects Retrived Successfully.');
    }

    public function getProjectTeams(Request $request, $projectId)
    {
        // Get the authenticated user from the JWT token
        $user = Auth::user();

        // Find the project
        $project = Project::find($projectId);

        if (!$project) {
            return $this->error('Project not found.', 404);
        }

        // Check if the user is associated with the project
        $userProjectRoles = $user->roles()
            ->where('project_id', $projectId)
            ->get();

        if ($userProjectRoles->isEmpty()) {
            return $this->error('User is not associated with this project.', 403);
        }

        // Check if the user is a project manager (role_id = 1 and team_id = null)
        $isProjectManager = $userProjectRoles->contains(function ($role) {
            return $role->pivot->team_id === null && $role->id === 1; // Assuming role_id 1 is for manager
        });

        // Get all teams in the project
        $teams = $project->teams()->get();

        // Check if the user has access to each team and include their role
        $teamsWithAccess = $teams->map(function ($team) use ($user, $isProjectManager, $projectId) {
            // Initialize the role as null
            $role = null;

            // If the user is a project manager, they have access to all teams
            if ($isProjectManager) {
                $team->hasAccess = true;
                $role = 'manager'; // Assuming role_id 1 is for manager
            } else {
                // Check if the user is a team leader for this team
                $isTeamLeader = $user->roles()
                    ->where('project_id', $projectId)
                    ->where('team_id', $team->id)
                    ->where('role_id', 2) // Assuming role_id 2 is for leader
                    ->exists();

                // Check if the user is a member of the team
                $isTeamMember = $team->members()->where('user_id', $user->id)->exists();

                // Determine if the user has access to the team
                $hasAccess = $isTeamLeader || $isTeamMember;

                // Add the hasAccess flag to the team
                $team->hasAccess = $hasAccess;

                // Set the role based on the user's role in the team
                if ($isTeamLeader) {
                    $role = 'leader'; // Assuming role_id 2 is for leader
                } elseif ($isTeamMember) {
                    $role = 'member'; // Assuming role_id 3 is for member
                }
            }

            // Add the role to the team object
            $team->role = $role;

            return $team;
        });

        return $this->success(['teams' => $teamsWithAccess], 'Teams Retrieved Successfully.');
    }

    public function createProject(Request $request)
    {
        // Get the authenticated user from the JWT token
        $user = Auth::user();

        // Validate the request data
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }

        // Create the project
        $project = Project::create([
            'name' => $request->name,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'description' => $request->description,
        ]);

        // Assign the authenticated user as the manager of the project
        $managerRole = Role::where('name', 'manager')->first(); // Assuming 'manager' role exists

        if (!$managerRole) {
            return $this->error('Manager role not found.', 404);
        }

        // Insert into project_role_user table
        $user->roles()->attach($managerRole->id, [
            'project_id' => $project->id,
            'team_id' => null, // Project-level role (manager)
        ]);

        return $this->success(['project' => $project], 'Project created successfully.');
    }
}
