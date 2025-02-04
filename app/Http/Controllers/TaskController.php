<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Models\Project;
use App\Models\TaskNote;
use App\Models\TaskMember;
use Illuminate\Http\Request;
use App\Traits\ResponseTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;


class TaskController extends Controller
{

    use ResponseTrait;

    // Create Task (Team Leader & manager Only)
    public function createTask(Request $request)
    {
        // Get the authenticated user
        $user = Auth::user();

        // Validate the request
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'team_id' => 'required|exists:teams,id',
            'description' => 'nullable|string',
            'tags' => 'nullable|string',
            'priority' => 'nullable|in:low,medium,high',
            'deadline' => 'nullable|date',
            'status' => 'nullable|boolean',
            'members' => 'required|array',
            'members.*' => 'exists:users,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Find the team
        $team = Team::find($request->team_id);
        if (!$team) {
            return $this->error('Team not found.', 404);
        }

        // Check if the user is the manager of the project or the leader of the team
        $isManager = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('project_id', $team->project_id)
            ->where('role_id', Role::ROLE_MANAGER)
            ->whereNull('team_id') // Manager has team_id = null
            ->exists();

        $isLeader = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('team_id', $request->team_id)
            ->where('role_id', Role::ROLE_LEADER)
            ->exists();

        if (!$isManager && !$isLeader) {
            return $this->error('Only the project manager or team leader can create tasks.', 403);
        }

        // Validate that all selected members are part of the team
        if ($request->has('members')) {
            $teamMembers = DB::table('project_role_user')
                ->where('team_id', $request->team_id)
                ->whereIn('role_id', [Role::ROLE_MEMBER, Role::ROLE_LEADER])
                ->pluck('user_id')
                ->toArray();

            $invalidMembers = array_diff($request->members, $teamMembers);
            if (!empty($invalidMembers)) {
                $invalidMemberNames = User::whereIn('id', $invalidMembers)->pluck('name')->toArray();
                return $this->error('The following users are not part of the team: ' . implode(', ', $invalidMemberNames), 422);
            }
        }

        // Create the task
        $task = Task::create([
            'name' => $request->name,
            'team_id' => $request->team_id,
            'description' => $request->description,
            'tags' => $request->tags,
            'priority' => $request->priority ?? 'medium',
            'deadline' => $request->deadline,
            'status' => $request->status ?? true,
        ]);

        // Assign members to the task
        if ($request->has('members')) {
            foreach ($request->members as $memberId) {
                DB::table('task_members')->insert([
                    'task_id' => $task->id,
                    'user_id' => $memberId,
                    'team_id' => $request->team_id,
                    'project_id' => $task->team->project_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return $this->success(['task' => $task], 'Task created successfully.');
    }

    // Update Task (Team Leader Only)
    public function updateTask(Request $request, $taskId)
    {
        // Get the authenticated user
        $user = Auth::user();

        // Validate the request
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'tags' => 'nullable|string',
            'priority' => 'nullable|in:low,medium,high',
            'deadline' => 'nullable|date',
            'status' => 'nullable|boolean',
            'members' => 'nullable|array',
            'members.*' => 'exists:users,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Find the task
        $task = Task::find($taskId);
        if (!$task) {
            return $this->error('Task not found.', 404);
        }

        // Check if the user is a leader of the team
        $isLeader = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('team_id', $task->team_id)
            ->where('role_id', Role::ROLE_LEADER)
            ->exists();

        if (!$isLeader) {
            return $this->error('Only team leaders can update tasks.', 403);
        }

        // Validate that all selected members are part of the team
        if ($request->has('members')) {
            $teamMembers = DB::table('project_role_user')
                ->where('team_id', $task->team_id)
                ->whereIn('role_id', [Role::ROLE_MEMBER, Role::ROLE_LEADER])
                ->pluck('user_id')
                ->toArray();

            $invalidMembers = array_diff($request->members, $teamMembers);
            if (!empty($invalidMembers)) {
                $invalidMemberNames = User::whereIn('id', $invalidMembers)->pluck('name')->toArray();
                return $this->error('The following users are not part of the team: ' . implode(', ', $invalidMemberNames), 422);
            }
        }

        // Update the task
        $task->update($request->only(['name', 'description', 'tags', 'priority', 'deadline', 'status']));

        // Update members if provided
        if ($request->has('members')) {
            // Remove existing members
            DB::table('task_members')->where('task_id', $task->id)->delete();

            // Assign new members
            foreach ($request->members as $memberId) {
                DB::table('task_members')->insert([
                    'task_id' => $task->id,
                    'user_id' => $memberId,
                    'team_id' => $task->team_id,
                    'project_id' => $task->team->project_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return $this->success(['task' => $task], 'Task updated successfully.');
    }

    // Delete Task (Team Leader Only)
    public function deleteTask($taskId)
    {
        // Get the authenticated user
        $user = Auth::user();

        // Find the task
        $task = Task::find($taskId);
        if (!$task) {
            return $this->error('Task not found.', 404);
        }

        // Check if the user is a leader of the team
        $isLeader = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('team_id', $task->team_id)
            ->where('role_id', Role::ROLE_LEADER)
            ->exists();

        if (!$isLeader) {
            return $this->error('Only team leaders can delete tasks.', 403);
        }

        // Delete the task and its members
        DB::table('task_members')->where('task_id', $task->id)->delete();
        $task->delete();

        return $this->success([], 'Task deleted successfully.');
    }

    // Get Team Tasks
    public function getTeamTasks($teamId)
    {
        // Get the authenticated user
        $user = Auth::user();

        // Check if the team exists
        $team = Team::find($teamId);
        if (!$team) {
            return $this->error('Team not found.', 404);
        }

        // Check if the user is the manager of the project or part of the team (member or leader)
        $isManager = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('project_id', $team->project_id)
            ->where('role_id', Role::ROLE_MANAGER)
            ->whereNull('team_id') // Manager has team_id = null
            ->exists();

        $isPartOfTeam = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('team_id', $teamId)
            ->whereIn('role_id', [Role::ROLE_MEMBER, Role::ROLE_LEADER])
            ->exists();

        if (!$isManager && !$isPartOfTeam) {
            return $this->error('You are not authorized to view this team\'s tasks.', 403);
        }

        // Get tasks for the team
        $tasks = Task::where('team_id', $teamId)->get();

        // Format the response
        $formattedTasks = $tasks->map(function ($task) {
            return [
                'id' => $task->id,
                'name' => $task->name,
                'description' => $task->description,
                'tags' => $task->tags,
                'priority' => $task->priority,
                'deadline' => $task->deadline,
                'status' => $task->status,
                'team_id' => $task->team_id,
                'members' => $task->members->map(function ($member) {
                    return [
                        'id' => $member->id,
                        'name' => $member->name,
                        'color' => $this->getMemberColor($member->id),
                    ];
                }),
            ];
        });

        return $this->success(['tasks' => $formattedTasks], 'Team tasks retrieved successfully.');
    }
    // Get All Tasks (Assigned to User or Teams They Belong To)
    public function getAllTasks(Request $request)
    {
        // Get the authenticated user
        $user = Auth::user();

        // Get all teams where the user is a member or leader
        $teamIds = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->whereIn('role_id', [Role::ROLE_MEMBER, Role::ROLE_LEADER])
            ->pluck('team_id')
            ->toArray();

        // Get all tasks in these teams
        $tasks = Task::whereIn('team_id', $teamIds)->get();

        // Format the response
        $formattedTasks = $tasks->map(function ($task) use ($user) {
            return [
                'id' => $task->id,
                'name' => $task->name,
                'description' => $task->description,
                'tags' => $task->tags,
                'priority' => $task->priority,
                'deadline' => $task->deadline,
                'status' => $task->status,
                'team_id' => $task->team_id,
                'assigned_to_me' => $task->members->contains('id', $user->id),
                'members' => $task->members->map(function ($member) {
                    return [
                        'id' => $member->id,
                        'name' => $member->name,
                        'color' => $this->getMemberColor($member->id),
                    ];
                }),
            ];
        });

        return $this->success(['tasks' => $formattedTasks], 'All tasks retrieved successfully.');
    }

    // get task by id

    public function getTaskById($taskId)
    {
        // Get the authenticated user
        $user = Auth::user();

        // Retrieve the task with its members and notes
        $task = Task::with(['members', 'notes.user'])->find($taskId);

        if (!$task) {
            return $this->error('Task not found.', 404);
        }

        // Check if the user is part of the team (member or leader)
        $isPartOfTeam = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('team_id', $task->team_id)
            ->whereIn('role_id', [Role::ROLE_MEMBER, Role::ROLE_LEADER])
            ->exists();

        if (!$isPartOfTeam) {
            return $this->error('You are not part of this team.', 403);
        }

        // Format the response
        $formattedTask = [
            'id' => $task->id,
            'name' => $task->name,
            'description' => $task->description,
            'tags' => $task->tags,
            'priority' => $task->priority,
            'deadline' => $task->deadline,
            'status' => $task->status,
            'team_id' => $task->team_id,
            'assigned_to_me' => $task->members->contains('id', $user->id),
            'members' => $task->members->map(function ($member) {
                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'color' => $this->getMemberColor($member->id),
                ];
            }),
            'notes' => $task->notes->map(function ($note) {
                return [
                    'id' => $note->id,
                    'description' => $note->description,
                    'created_at' => $note->created_at,
                    'user' => [
                        'id' => $note->user->id,
                        'name' => $note->user->name,
                        'email' => $note->user->email,
                        'color' => $this->getMemberColor($note->user->id),
                    ],
                ];
            }),
        ];

        return $this->success(['task' => $formattedTask], 'Task retrieved successfully.');
    }
    // Helper Function: Get Member Color
    private function getMemberColor($userId)
    {
        // Predefined list of colors
        $colors = ['#FF6633', '#FFB399', '#FF33FF', '#FFFF99', '#00B3E6', '#E6B333', '#3366E6', '#999966', '#99FF99', '#B34D4D'];

        // Use a hash function to map user_id to a color
        $index = $userId % count($colors);
        return $colors[$index];
    }

    // ============= task notes ============

    public function addTaskNote(Request $request, $taskId)
    {
        // Get the authenticated user
        $user = Auth::user();

        // Validate the request
        $validator = Validator::make($request->all(), [
            'description' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Find the task
        $task = Task::find($taskId);
        if (!$task) {
            return $this->error('Task not found.', 404);
        }

        // Check if the user is part of the team (member or leader)
        $isPartOfTeam = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('team_id', $task->team_id)
            ->whereIn('role_id', [Role::ROLE_MEMBER, Role::ROLE_LEADER])
            ->exists();

        if (!$isPartOfTeam) {
            return $this->error('You are not part of this team.', 403);
        }

        // Create the task note
        $taskNote = TaskNote::create([
            'task_id' => $taskId,
            'user_id' => $user->id,
            'description' => $request->description,
        ]);

        return $this->success(['note' => $taskNote], 'Task note added successfully.');
    }


    public function updateTaskStatus(Request $request, $taskId)
    {
        // Get the authenticated user
        $user = Auth::user();


        // Validate the request
        $validator = Validator::make($request->all(), [
            'status' => 'required|integer|in:' . implode(',', array_keys(Task::$statuses)),
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Find the task
        $task = Task::find($taskId);
        if (!$task) {
            return $this->error('Task not found.', 404);
        }
        $team = Team::find($task->team_id);
        if (!$team) {
            return $this->error('Team not found.', 404);
        }

        // Check if the user is a manager
        $isManager = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('project_id', $team->project_id)
            ->where('role_id', Role::ROLE_MANAGER)
            ->whereNull('team_id') // Manager has team_id = null
            ->exists();

        // Check if the user is a team leader
        $isTeamLeader = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('team_id', $task->team_id)
            ->where('role_id', Role::ROLE_LEADER)
            ->exists();

        // Check if the user is assigned to the task
        $isAssignedToTask = DB::table('task_members')
            ->where('task_id', $taskId)
            ->where('user_id', $user->id)
            ->exists();

        // Allow managers and team leaders to update all task statuses, including cancelled ones
        if ($task->status === Task::STATUS_CANCELLED) {
            if (!$isTeamLeader && !$isManager) {
                return $this->error('Only the team leader or manager can update the status of a cancelled task.', 403);
            }
        } else {
            // For non-cancelled tasks, allow the assigned user, team leader, or manager to update the status
            if (!$isAssignedToTask && !$isTeamLeader && !$isManager) {
                return $this->error('You are not authorized to update the task status.', 403);
            }
        }

        // Update the task status
        $task->update(['status' => $request->status]);

        return $this->success(['status' => (string) $task->status], 'Task status updated successfully.');
    }
}
