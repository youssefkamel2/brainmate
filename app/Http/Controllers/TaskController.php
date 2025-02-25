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
use Spatie\Activitylog\Facades\CauserResolver;
use Spatie\Activitylog\Facades\LogBatch;

class TaskController extends Controller
{

    use ResponseTrait, MemberColorTrait;

    // Create Task (Team Leader & manager Only)
    public function createTask(Request $request)
    {
        // Start a batch for this operation
        LogBatch::startBatch();

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
            'attachments.*' => 'file|mimes:jpg,jpeg,png,pdf,doc,docx|max:8048', // Max 8MB per file
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

        // Log the task creation
        activity()
            ->causedBy($user)
            ->performedOn($task)
            ->withProperties(['attributes' => $task->getAttributes()])
            ->event('created')
            ->log('Task created');

        // Handle attachments
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

                // Log the attachment creation
                activity()
                    ->causedBy($user)
                    ->performedOn($attachmentRecord)
                    ->withProperties(['attributes' => $attachmentRecord->getAttributes()])
                    ->event('created')
                    ->log('Attachment added to task');
            }
        }

        // End the batch
        LogBatch::endBatch();

        return $this->success(['task' => $task], 'Task created successfully.');
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

        // Log the task before update
        $oldAttributes = $task->getAttributes();

        // Update the task
        $task->update($request->only(['name', 'description', 'tags', 'priority', 'deadline', 'status']));

        // Log the task update
        activity()
            ->causedBy($user)
            ->performedOn($task)
            ->withProperties([
                'old' => $oldAttributes,
                'attributes' => $task->getAttributes()
            ])
            ->event('updated')
            ->log('Task updated');

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

        // Log the task deletion
        activity()
            ->causedBy($user)
            ->performedOn($task)
            ->withProperties(['attributes' => $task->getAttributes()])
            ->event('deleted')
            ->log('Task deleted');

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
            'attachments.*' => 'file|mimes:jpg,jpeg,png,pdf,doc,docx|max:8048', // Max 8MB per file
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

                // Log the attachment creation
                activity()
                    ->causedBy(Auth::user())
                    ->performedOn($attachmentRecord)
                    ->withProperties(['attributes' => $attachmentRecord->getAttributes()])
                    ->event('created')
                    ->log('Attachment added to task');
            }
        }

        return $this->success([], 'Attachments added successfully.');
    }

    // Remove Attachment from a Task
    public function removeAttachment($attachmentId)
    {
        // Find the attachment
        $attachment = Attachment::find($attachmentId);
        if (!$attachment) {
            return $this->error('Attachment not found.', 404);
        }

        // Log the attachment deletion
        activity()
            ->causedBy(Auth::user())
            ->performedOn($attachment)
            ->withProperties(['attributes' => $attachment->getAttributes()])
            ->event('deleted')
            ->log('Attachment removed from task');

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
        $task = Task::with([
            'members',
            'notes' => function ($query) {
                $query->orderBy('created_at', 'desc'); // Sort notes by created_at in descending order
            },
            'notes.user', // Load the user relationship for each note
            'attachments' // Load existing attachments
        ])->find($taskId);
    
        if (!$task) {
            return $this->error('Task not found.', 404);
        }
    
        // Check if the user is authorized to view the task
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
    
        $isMember = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('team_id', $task->team_id)
            ->where('role_id', Role::ROLE_MEMBER)
            ->exists();
    
        if (!$isManager && !$isLeader && !$isMember) {
            return $this->error('You are not part of this team.', 403);
        }
    
        // Log the view event only if the user hasn't viewed the task before
        $hasViewed = \Spatie\Activitylog\Models\Activity::where('subject_type', Task::class)
            ->where('subject_id', $task->id)
            ->where('causer_id', $user->id)
            ->where('event', 'viewed')
            ->exists();
    
        if (!$hasViewed) {
            activity()
                ->causedBy($user)
                ->performedOn($task)
                ->event('viewed')
                ->log('Task viewed');
        }
    
        // Step 1: Fetch all logs related to the task, notes, and attachments
        $logs = \Spatie\Activitylog\Models\Activity::where(function ($query) use ($task) {
            // Logs for the task itself
            $query->where('subject_type', Task::class)
                ->where('subject_id', $task->id);
    
            // Logs for task notes
            $query->orWhere(function ($query) use ($task) {
                $query->where('subject_type', TaskNote::class)
                    ->whereIn('subject_id', $task->notes->pluck('id'));
            });
    
            // Logs for attachments (including deleted ones)
            $query->orWhere(function ($query) use ($task) {
                $query->where('subject_type', Attachment::class);
            });
        })
            ->orderBy('created_at', 'desc')
            ->get();
    
        // Step 2: Extract attachment details from the logs
        $attachmentLogs = $logs->filter(function ($log) {
            return $log->subject_type === 'App\Models\Attachment';
        });
    
        $formattedAttachmentLogs = $attachmentLogs->map(function ($log) {
            $properties = json_decode($log->properties, true);
            $attachmentDetails = $properties['attributes'] ?? [];
    
            return [
                'id' => $log->id,
                'description' => $log->description,
                'event' => $log->event,
                'attachment' => [
                    'id' => $attachmentDetails['id'] ?? null,
                    'name' => $attachmentDetails['name'] ?? 'an attachment',
                    'media' => $attachmentDetails['media'] ?? null,
                    'created_at' => $attachmentDetails['created_at'] ?? null,
                    'updated_at' => $attachmentDetails['updated_at'] ?? null,
                ],
                'user' => $log->causer ? [
                    'id' => $log->causer->id,
                    'name' => $log->causer->name,
                    'color' => $this->getMemberColor($log->causer->id),
                ] : null,
                'created_at' => $log->created_at,
            ];
        });
    
        // Step 3: Format the logs for display
        $formattedLogs = $logs->map(function ($log) {
            $description = $log->description;
            $userName = $log->causer ? $log->causer->name : 'System';
    
            switch ($log->event) {
                case 'created':
                    if ($log->subject_type === Task::class) {
                        $description = "{$userName} created the task.";
                    } elseif ($log->subject_type === Attachment::class) {
                        $properties = json_decode($log->properties, true);
                        $attachmentName = $properties['attributes']['name'] ?? 'an attachment';
                        $description = "{$userName} added an attachment: {$attachmentName}.";
                    } elseif ($log->subject_type === TaskNote::class) {
                        $noteDescription = $log->subject ? $log->subject->description : 'a note';
                        $description = "{$userName} added a note: {$noteDescription}.";
                    }
                    break;
    
                case 'updated':
                    if ($log->subject_type === Task::class) {
                        $properties = json_decode($log->properties, true);
                        if (isset($properties['old_status']) && isset($properties['new_status'])) {
                            $oldStatus = Task::$statusTexts[$properties['old_status']] ?? 'unknown';
                            $newStatus = Task::$statusTexts[$properties['new_status']] ?? 'unknown';
                            $description = "{$userName} changed the task status from {$oldStatus} to {$newStatus}.";
                        } else {
                            $description = "{$userName} updated the task.";
                        }
                    }
                    break;
    
                case 'viewed':
                    $description = "{$userName} viewed the task.";
                    break;
    
                case 'deleted':
                    if ($log->subject_type === Attachment::class) {
                        $properties = json_decode($log->properties, true);
                        $attachmentName = $properties['attributes']['name'] ?? 'an attachment';
                        $description = "{$userName} removed an attachment: {$attachmentName}.";
                    }
                    break;
            }
    
            return [
                'id' => $log->id,
                'description' => $description,
                'event' => $log->event,
                'user' => $log->causer ? [
                    'id' => $log->causer->id,
                    'name' => $log->causer->name,
                    'color' => $this->getMemberColor($log->causer->id),
                ] : null,
                'created_at' => $log->created_at,
            ];
        });
    
        // Step 4: Combine logs for attachments with other logs
        $combinedLogs = $formattedLogs->merge($formattedAttachmentLogs)->sortByDesc('created_at');
    
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
            'role' => $isManager ? 'manager' : ($isLeader ? 'leader' : 'member'),
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
            'logs' => $combinedLogs->values(), // Reset keys for JSON response
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

        $isProjectManager = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('project_id', $task->team->project_id)
            ->where('role_id', Role::ROLE_MANAGER)
            ->whereNull('team_id') // Manager has team_id = null
            ->exists();

        // Check if the user is part of the team (member or leader)
        $isPartOfTeam = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('team_id', $task->team_id)
            ->whereIn('role_id', [Role::ROLE_MEMBER, Role::ROLE_LEADER])
            ->exists();

        if (!$isPartOfTeam && !$isProjectManager) {
            return $this->error('You are not part of this team.', 403);
        }

        // Create the task note
        $taskNote = TaskNote::create([
            'task_id' => $taskId,
            'user_id' => $user->id,
            'description' => $request->description,
        ]);

        // Log the task note creation
        activity()
            ->causedBy($user)
            ->performedOn($taskNote)
            ->withProperties(['attributes' => $taskNote->getAttributes()])
            ->event('created')
            ->log('Task note added');

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

        // Log the task status update
        activity()
            ->causedBy($user)
            ->performedOn($task)
            ->withProperties(['old_status' => $task->status, 'new_status' => $request->status])
            ->event('updated')
            ->log('Task status updated');

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
