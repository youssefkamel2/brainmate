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
        // Get the authenticated user
        $user = Auth::user();

        // Retrieve all projects associated with the user
        $projectIds = $user->projects()
            ->distinct('project_id')
            ->pluck('project_id');

        // Fetch the full project details for the distinct project IDs
        $projects = Project::whereIn('id', $projectIds)->get();

        // Add the is_manager flag to each project
        $projectsWithManagerFlag = $projects->map(function ($project) use ($user) {
            // Check if the user is the manager of the project
            $isManager = $user->roles()
                ->where('project_id', $project->id)
                ->where('role_id', Role::ROLE_MANAGER)
                ->whereNull('team_id')
                ->exists();

            // Add the is_manager flag to the project
            $project->is_manager = $isManager;

            return $project;
        });

        return $this->success(['projects' => $projectsWithManagerFlag], 'Projects retrieved successfully.');
    }

    public function getProjectDetails($projectId)
    {
        // Find the project
        $project = Project::with(['teams', 'users'])->find($projectId);
        if (!$project) {
            return $this->error('Project not found.', 404);
        }

        return $this->success(['project' => $project], 'Project details retrieved successfully.');
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

    public function updateProject(Request $request, $projectId)
    {
        // Get the authenticated user
        $user = Auth::user();

        // Find the project
        $project = Project::find($projectId);
        if (!$project) {
            return $this->error('Project not found.', 404);
        }

        // Check if the user is the manager of the project
        $isManager = $user->roles()
            ->where('project_id', $projectId)
            ->where('role_id', Role::ROLE_MANAGER)
            ->whereNull('team_id')
            ->exists();

        if (!$isManager) {
            return $this->error('Only the project manager can update the project.', 403);
        }

        // Validate the request data
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }

        // Update the project
        $project->update($request->only(['name', 'description', 'start_date', 'end_date']));

        return $this->success(['project' => $project], 'Project updated successfully.');
    }

    // Delete a project
    public function deleteProject($projectId)
    {
        // Get the authenticated user
        $user = Auth::user();

        // Find the project
        $project = Project::find($projectId);
        if (!$project) {
            return $this->error('Project not found.', 404);
        }

        // Check if the user is the manager of the project
        $isManager = $user->roles()
            ->where('project_id', $projectId)
            ->where('role_id', Role::ROLE_MANAGER)
            ->whereNull('team_id')
            ->exists();

        if (!$isManager) {
            return $this->error('Only the project manager can delete the project.', 403);
        }

        // Delete the project
        $project->delete();

        return $this->success([], 'Project deleted successfully.');
    }
}
