<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Models\Project;
use App\Models\TaskNote;
use App\Models\Attachment;
use App\Models\TaskMember;
use Illuminate\Http\Request;
use App\Traits\ResponseTrait;
use App\Traits\MemberColorTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Notifications\TaskAssignedNotification;


class TaskController extends Controller
{

    use ResponseTrait, MemberColorTrait;

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
            'deadline' => 'nullable|date|after_or_equal:' . now()->format('Y-m-d'),
            'status' => 'nullable|boolean',
            'members' => 'required|array',
            'members.*' => 'exists:users,id',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,pdf,doc,docx|max:2048', // Max 2MB per file
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

                $member = User::find($memberId);
                if ($member) {
                    $member->notify(new TaskAssignedNotification($task));
                }
            }
        }

        // Handle attachments
        $attachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $attachment) {
                $fileName = time() . '_' . uniqid() . '.' . $attachment->getClientOriginalExtension();
                $attachment->move(public_path('uploads/tasks'), $fileName);
                $mediaUrl = 'uploads/tasks/' . $fileName;

                // Save attachment details to the database
                $attachmentRecord = Attachment::create([
                    'name' => $fileName,
                    'media' => $mediaUrl,
                    'task_id' => $task->id,
                ]);

                // Add attachment details to the response
                $attachments[] = [
                    'id' => $attachmentRecord->id,
                    'name' => $attachmentRecord->name,
                    'media' => $attachmentRecord->media,
                    'created_at' => $attachmentRecord->created_at,
                    'updated_at' => $attachmentRecord->updated_at,
                ];
            }
        }

        // Format the task response
        $taskResponse = [
            'id' => $task->id,
            'name' => $task->name,
            'description' => $task->description,
            'tags' => $task->tags,
            'priority' => $task->priority,
            'deadline' => $task->deadline,
            'status' => $task->status,
            'team_id' => $task->team_id,
            'created_at' => $task->created_at,
            'updated_at' => $task->updated_at,
            'attachments' => $attachments, // Include attachments in the response
        ];

        return $this->success(['task' => $taskResponse], 'Task created successfully.');
    }

    // Update Task (Team Leader & manager)
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

        // Check if the user is the manager of the project or the leader of the team
        $isManager = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('project_id', $task->team->project_id)
            ->where('role_id', Role::ROLE_MANAGER)
            ->whereNull('team_id') // Manager has team_id = null
            ->exists();

        $isLeader = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('team_id', $task->team_id)
            ->where('role_id', Role::ROLE_LEADER)
            ->exists();

        if (!$isLeader && !$isManager) {
            return $this->error('Only team leaders and managers can update tasks.', 403);
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

    // Delete Task (Team Leader & manager)
    public function deleteTask($taskId)
    {
        // Get the authenticated user
        $user = Auth::user();

        // Find the task
        $task = Task::find($taskId);
        if (!$task) {
            return $this->error('Task not found.', 404);
        }

        // Check if the user is the manager of the project or the leader of the team
        $isManager = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('project_id', $task->team->project_id)
            ->where('role_id', Role::ROLE_MANAGER)
            ->whereNull('team_id') // Manager has team_id = null
            ->exists();

        $isLeader = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('team_id', $task->team_id)
            ->where('role_id', Role::ROLE_LEADER)
            ->exists();

        if (!$isManager && !$isLeader) {
            return $this->error('Only the project manager or team leader can delete tasks.', 403);
        }

        // Delete the task and its members
        DB::table('task_members')->where('task_id', $task->id)->delete();
        $task->delete();

        return $this->success([], 'Task deleted successfully.');
    }

    public function addAttachments(Request $request, $taskId)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'attachments' => 'required|array',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,pdf,doc,docx|max:2048', // Max 2MB per file
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Find the task
        $task = Task::find($taskId);
        if (!$task) {
            return $this->error('Task not found.', 404);
        }

        // Handle attachments
        $attachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $attachment) {
                $fileName = time() . '_' . uniqid() . '.' . $attachment->getClientOriginalExtension();
                $attachment->move(public_path('uploads/tasks'), $fileName); // Move to public/uploads/tasks
                $mediaUrl = 'uploads/tasks/' . $fileName; // Relative path for the media URL

                // Save attachment details to the database
                $attachmentRecord = Attachment::create([
                    'name' => $fileName,
                    'media' => $mediaUrl,
                    'task_id' => $task->id,
                ]);

                // Add attachment details to the response
                $attachments[] = [
                    'id' => $attachmentRecord->id,
                    'name' => $attachmentRecord->name,
                    'media' => $attachmentRecord->media,
                    'created_at' => $attachmentRecord->created_at,
                    'updated_at' => $attachmentRecord->updated_at,
                ];
            }
        }

        return $this->success(['attachments' => $attachments], 'Attachments added successfully.');
    }

    // Remove Attachment from a Task
    public function removeAttachment($attachmentId)
    {
        // Find the attachment
        $attachment = Attachment::find($attachmentId);
        if (!$attachment) {
            return $this->error('Attachment not found.', 404);
        }

        // Delete the file from the server
        if (file_exists(public_path($attachment->media))) {
            unlink(public_path($attachment->media));
        }

        // Delete the attachment record from the database
        $attachment->delete();

        return $this->success([], 'Attachment removed successfully.');
    }

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
                'is_overdue' => $task->is_overdue, // Add the overdue flag
                'team_id' => $task->team_id,
                'created_at' => $task->created_at, 
                'updated_at' => $task->updated_at,
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
                'is_overdue' => $task->is_overdue, // Add the overdue flag
                'team_id' => $task->team_id,
                'assigned_to_me' => $task->members->contains('id', $user->id),
                'created_at' => $task->created_at, // Include created_at
                'updated_at' => $task->updated_at,
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
    
        // Retrieve the task with its members, notes, and attachments
        $task = Task::with(['members', 'notes.user', 'attachments'])->find($taskId);
    
        if (!$task) {
            return $this->error('Task not found.', 404);
        }
    
        // Check if the user is the manager of the project
        $isManager = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('project_id', $task->team->project_id)
            ->where('role_id', Role::ROLE_MANAGER)
            ->whereNull('team_id') // Manager has team_id = null
            ->exists();
    
        // Check if the user is the leader of the team
        $isLeader = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('team_id', $task->team_id)
            ->where('role_id', Role::ROLE_LEADER)
            ->exists();
    
        // Check if the user is a member of the team
        $isMember = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('team_id', $task->team_id)
            ->where('role_id', Role::ROLE_MEMBER)
            ->exists();
    
        // Determine the user's role
        $role = 'member'; // Default role
        if ($isManager) {
            $role = 'manager';
        } elseif ($isLeader) {
            $role = 'leader';
        } elseif ($isMember) {
            $role = 'member';
        }
    
        // Check if the user is authorized to view the task
        if (!$isManager && !$isLeader && !$isMember) {
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
            'created_at' => $task->created_at,
            'updated_at' => $task->updated_at,
            'assigned_to_me' => $task->members->contains('id', $user->id),
            'role' => $role, // Include the user's role in the response
            'is_overdue' => $task->is_overdue,
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
            'attachments' => $task->attachments->map(function ($attachment) {
                return [
                    'id' => $attachment->id,
                    'name' => $attachment->name,
                    'media' => $attachment->media,
                    'created_at' => $attachment->created_at,
                    'updated_at' => $attachment->updated_at,
                ];
            }),
        ];
    
        return $this->success(['task' => $formattedTask], 'Task retrieved successfully.');
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
            'status' => 'required|integer|in:' . implode(',', array_keys(Task::$statusTexts)),
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Find the task
        $task = Task::find($taskId);
        if (!$task) {
            return $this->error('Task not found.', 404);
        }

        // Check if the user is a task member, team leader, or project manager
        $isTaskMember = DB::table('task_members')
            ->where('task_id', $taskId)
            ->where('user_id', $user->id)
            ->exists();

        $isTeamLeader = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('team_id', $task->team_id)
            ->where('role_id', Role::ROLE_LEADER)
            ->exists();

        $isProjectManager = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('project_id', $task->team->project_id)
            ->where('role_id', Role::ROLE_MANAGER)
            ->whereNull('team_id') // Manager has team_id = null
            ->exists();

        // If the user is not a task member, team leader, or project manager, return an error
        if (!$isTaskMember && !$isTeamLeader && !$isProjectManager) {
            return $this->error('You are not authorized to update the task status.', 403);
        }

        // Update the task status
        $task->status = $request->status;
        $task->save();

        // Return the updated status and its text representation
        return $this->success([
            'status' => $task->status,
            'status_text' => $task->status_text,
        ], 'Task status updated successfully.');
    }
}
