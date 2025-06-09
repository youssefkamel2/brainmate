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
use App\Models\Notification;
use Illuminate\Http\Request;
use App\Traits\ResponseTrait;
use App\Events\NotificationSent;
use App\Traits\MemberColorTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Facades\LogBatch;
use Illuminate\Support\Facades\Validator;
use App\Jobs\SendTaskAssignedNotifications;
use Spatie\Activitylog\Facades\CauserResolver;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\TaskNoteAddedNotification;
use App\Notifications\TaskStatusUpdatedNotification;


class TaskController extends Controller
{

    use ResponseTrait, MemberColorTrait;

    // Create Task (Team Leader & manager Only)

    public function createTask(Request $request)
    {
        LogBatch::startBatch();

        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'team_id' => 'required|exists:teams,id',
            'description' => 'nullable|string',
            'tags' => 'nullable|string',
            'priority' => 'nullable|in:low,medium,high',
            'deadline' => 'nullable|date|after_or_equal:' . now()->format('Y-m-d'),
            'duration_days' => 'nullable|integer|min:1|required_if:is_backlog,true',
            'is_backlog' => 'nullable|boolean',
            'status' => 'nullable|boolean',
            'members' => 'required|array',
            'members.*' => 'exists:users,id',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,pdf,doc,docx|max:8048',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $team = Team::find($request->team_id);
        if (!$team) {
            return $this->error('Team not found.', 404);
        }

        $isManager = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('project_id', $team->project_id)
            ->where('role_id', Role::ROLE_MANAGER)
            ->whereNull('team_id')
            ->exists();

        $isLeader = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('team_id', $request->team_id)
            ->where('role_id', Role::ROLE_LEADER)
            ->exists();

        if (!$isManager && !$isLeader) {
            return $this->error('Only the project manager or team leader can create tasks.', 403);
        }

        $teamMembers = DB::table('project_role_user')
            ->where('project_id', $team->project_id)
            ->where(function ($query) use ($request) {
                $query->where('team_id', $request->team_id)
                    ->orWhereNull('team_id');
            })
            ->whereIn('role_id', [Role::ROLE_MEMBER, Role::ROLE_LEADER, Role::ROLE_MANAGER])
            ->pluck('user_id')
            ->toArray();

        $invalidMembers = array_diff($request->members, $teamMembers);
        if (!empty($invalidMembers)) {
            $invalidMemberNames = User::whereIn('id', $invalidMembers)->pluck('name')->toArray();
            return $this->error('The following users are not part of the team or project: ' . implode(', ', $invalidMemberNames), 422);
        }

        $taskData = [
            'name' => $request->name,
            'team_id' => $request->team_id,
            'description' => $request->description,
            'tags' => $request->tags,
            'priority' => $request->priority ?? 'medium',
            'status' => $request->is_backlog ? Task::STATUS_BACKLOG : ($request->status ?? Task::STATUS_PENDING),
            'is_backlog' => $request->is_backlog ?? false,
        ];

        if ($request->is_backlog) {
            $taskData['duration_days'] = $request->duration_days;
            $taskData['deadline'] = today()->addDays($request->duration_days);
        } else {
            $taskData['deadline'] = $request->deadline;
            // If no deadline is provided, set it = deadline - today's date
            $taskData['duration_days'] = $request->duration_days ?? now()->diffInDays($request->deadline);
        }

        $task = Task::create($taskData);

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

                // Only send notifications if the task is not a backlog task
                if (!$task->is_backlog) {
                    $member = User::find($memberId);
                    if ($member) {
                        dispatch(new SendTaskAssignedNotifications($member, $task))->afterResponse();
                    }
                }
            }
        }

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $attachment) {
                $fileName = time() . '_' . uniqid() . '.' . $attachment->getClientOriginalExtension();
                $attachment->move(public_path('uploads/tasks'), $fileName);
                $mediaUrl = 'uploads/tasks/' . $fileName;

                $attachmentRecord = Attachment::create([
                    'name' => $fileName,
                    'media' => $mediaUrl,
                    'task_id' => $task->id,
                ]);

                activity()
                    ->causedBy($user)
                    ->performedOn($attachmentRecord)
                    ->withProperties(['attributes' => $attachmentRecord->getAttributes()])
                    ->event('created')
                    ->log('Attachment added to task');
            }
        }

        activity()
            ->causedBy($user)
            ->performedOn($task)
            ->withProperties(['attributes' => $task->getAttributes()])
            ->event('created')
            ->log($task->is_backlog ? 'Backlog task created' : 'Task created');

        LogBatch::endBatch();

        return $this->success(['task' => $task], $task->is_backlog ? 'Backlog task created successfully.' : 'Task created successfully.');
    }

    // Add new method to publish backlog tasks
    public function publishBulkBacklogTasks(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'task_ids' => 'required|array',
            'task_ids.*' => 'exists:tasks,id',
            'team_id' => 'required|exists:teams,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $team = Team::find($request->team_id);
        if (!$team) {
            return $this->error('Team not found.', 404);
        }

        // Check permissions
        $isManager = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('project_id', $team->project_id)
            ->where('role_id', Role::ROLE_MANAGER)
            ->whereNull('team_id')
            ->exists();

        $isLeader = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('team_id', $team->id)
            ->where('role_id', Role::ROLE_LEADER)
            ->exists();

        if (!$isManager && !$isLeader) {
            return $this->error('Only the project manager or team leader can publish backlog tasks.', 403);
        }

        // Get the tasks before updating for logging
        $tasks = Task::whereIn('id', $request->task_ids)
            ->where('team_id', $team->id)
            ->backlog()
            ->get();

        if ($tasks->isEmpty()) {
            return $this->error('No valid backlog tasks found to publish.', 404);
        }

        // Publish the tasks
        $publishedCount = Task::publishBulk($request->task_ids);

        // Log the activity for each task
        foreach ($tasks as $task) {
            activity()
                ->causedBy($user)
                ->performedOn($task)
                ->withProperties(['attributes' => $task->fresh()->getAttributes()])
                ->event('updated')
                ->log('Backlog task published in bulk');

            // Notify members
            $taskMembers = $task->members;
            foreach ($taskMembers as $member) {
                $notification = Notification::create([
                    'user_id' => $member->id,
                    'message' => "Backlog task published: {$task->name} is now active with deadline {$task->fresh()->deadline}.",
                    'type' => 'info',
                    'read' => false,
                    'action_url' => NULL,
                    'metadata' => [
                        'task_id' => $task->id,
                        'task_name' => $task->name,
                        'team_id' => $task->team_id,
                        'deadline' => $task->fresh()->deadline,
                    ],
                ]);
                event(new NotificationSent($notification));
            }
        }

        return $this->success([
            'published_count' => $publishedCount,
            'total_selected' => count($request->task_ids)
        ], "Successfully published {$publishedCount} backlog tasks.");
    }
    public function deleteBulkBacklogTasks(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'task_ids' => 'required|array',
            'task_ids.*' => 'exists:tasks,id',
            'team_id' => 'required|exists:teams,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $team = Team::find($request->team_id);
        if (!$team) {
            return $this->error('Team not found.', 404);
        }

        // Check permissions
        $isManager = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('project_id', $team->project_id)
            ->where('role_id', Role::ROLE_MANAGER)
            ->whereNull('team_id')
            ->exists();

        $isLeader = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('team_id', $team->id)
            ->where('role_id', Role::ROLE_LEADER)
            ->exists();

        if (!$isManager && !$isLeader) {
            return $this->error('Only the project manager or team leader can delete backlog tasks.', 403);
        }

        // Get the tasks before deleting for logging
        $tasks = Task::whereIn('id', $request->task_ids)
            ->where('team_id', $team->id)
            ->backlog()
            ->get();

        if ($tasks->isEmpty()) {
            return $this->error('No valid backlog tasks found to delete.', 404);
        }

        // Delete task members first
        DB::table('task_members')->whereIn('task_id', $request->task_ids)->delete();

        // Delete the tasks
        $deletedCount = Task::whereIn('id', $request->task_ids)->delete();

        // Log the activity for each task
        foreach ($tasks as $task) {
            activity()
                ->causedBy($user)
                ->performedOn($task)
                ->withProperties(['attributes' => $task->getAttributes()])
                ->event('deleted')
                ->log('Backlog task deleted in bulk');
        }

        return $this->success([
            'deleted_count' => $deletedCount,
            'total_selected' => count($request->task_ids)
        ], "Successfully deleted {$deletedCount} backlog tasks.");
    }

    // Add new method to get backlog tasks
    public function getBacklogTasks($teamId)
    {
        $user = Auth::user();
        $team = Team::find($teamId);

        if (!$team) {
            return $this->error('Team not found.', 404);
        }

        // Check permissions
        $isManager = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('project_id', $team->project_id)
            ->where('role_id', Role::ROLE_MANAGER)
            ->whereNull('team_id')
            ->exists();

        $isPartOfTeam = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('team_id', $teamId)
            ->whereIn('role_id', [Role::ROLE_MEMBER, Role::ROLE_LEADER])
            ->exists();

        if (!$isManager && !$isPartOfTeam) {
            return $this->error('You are not authorized to view this team\'s backlog.', 403);
        }

        $backlogTasks = Task::where('team_id', $teamId)
            ->backlog()
            ->get();

        $formattedTasks = $backlogTasks->map(function ($task) {
            return [
                'id' => $task->id,
                'name' => $task->name,
                'description' => $task->description,
                'tags' => $task->tags,
                'priority' => $task->priority,
                'duration_days' => $task->duration_days,
                'status' => $task->status_text,
                'is_backlog' => $task->is_backlog,
                'created_at' => $task->created_at,
                'members' => $task->members->map(function ($member) {
                    return [
                        'id' => $member->id,
                        'name' => $member->name,
                        'color' => $this->getMemberColor($member->id),
                    ];
                }),
            ];
        });

        return $this->success(['backlog_tasks' => $formattedTasks], 'Backlog tasks retrieved successfully.');
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

    // check deadlines
    public function checkApproachingDeadlines(Request $request)
    {
        // Get tasks with deadlines within 24 hours that are not completed
        $approachingTasks = Task::where('deadline', '<=', now()->addHours(24))
            ->where('deadline', '>', now())
            ->whereNotIn('status', [Task::STATUS_COMPLETED, Task::STATUS_CANCELLED])
            ->with('members')
            ->get();

        $notifiedCount = 0;

        foreach ($approachingTasks as $task) {
            foreach ($task->members as $member) {
                // Check if notification was already sent for this task to this user
                $alreadyNotified = Notification::where('user_id', $member->id)
                    ->where('type', 'deadline_reminder')
                    ->whereJsonContains('metadata->task_id', $task->id)
                    ->exists();

                if (!$alreadyNotified) {
                    // Calculate hours remaining
                    $hoursRemaining = now()->diffInHours($task->deadline);
                    $formattedDeadline = $task->deadline->format('Y-m-d H:i:s');

                    // Create and send notification
                    $notification = Notification::create([
                        'user_id' => $member->id,
                        'message' => "Task '{$task->name}' deadline is approaching (due in {$hoursRemaining} hours).",
                        'type' => 'deadline_reminder',
                        'read' => false,
                        'action_url' => NULL,
                        'metadata' => [
                            'task_id' => $task->id,
                            'task_name' => $task->name,
                            'team_id' => $task->team_id,
                            'deadline' => $formattedDeadline,
                            'hours_remaining' => $hoursRemaining,
                            'reminder_sent_at' => now()->toDateTimeString(),
                        ],
                    ]);

                    event(new NotificationSent($notification));
                    $notifiedCount++;
                }
            }
        }

        return $this->success([
            'total_approaching_tasks' => $approachingTasks->count(),
            'total_potential_notifications' => $approachingTasks->sum(fn($task) => $task->members->count()),
            'notifications_sent' => $notifiedCount,
            'time_checked' => now()->toDateTimeString(),
        ], 'Deadline reminders processed successfully.');
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
        $user = Auth::user();

        $team = Team::find($teamId);
        if (!$team) {
            return $this->error('Team not found.', 404);
        }

        $isTeamMember = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('team_id', $teamId)
            ->exists();

        $isProjectManager = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('project_id', $team->project_id)
            ->where('role_id', Role::ROLE_MANAGER)
            ->whereNull('team_id')
            ->exists();

        if (!$isTeamMember && !$isProjectManager) {
            return $this->error('You are not authorized to view team tasks.', 403);
        }

        $tasks = Task::where('team_id', $teamId)
            ->where('is_backlog', false)
            ->with(['members', 'attachments', 'team.project'])
            ->get()
            ->map(function ($task) {
                return [
                    'id' => $task->id,
                    'name' => $task->name,
                    'description' => $task->description,
                    'tags' => $task->tags,
                    'priority' => $task->priority,
                    'deadline' => $task->deadline,
                    'status' => $task->status,
                    'status_text' => $task->status_text,
                    'is_overdue' => $task->is_overdue,
                    'completed_at' => $task->completed_at,
                    'completed_on_time' => $task->completed_at && $task->deadline 
                        ? $task->completed_at->lessThanOrEqualTo($task->deadline)
                        : null,
                    'completion_status' => $this->getCompletionStatus($task),
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
                    'attachments' => $task->attachments->map(function ($attachment) {
                        return [
                            'id' => $attachment->id,
                            'name' => $attachment->name,
                            'media' => $attachment->media,
                            'created_at' => $attachment->created_at,
                        ];
                    }),
                ];
            });

        return $this->success(['tasks' => $tasks], 'Team tasks retrieved successfully.');
    }

    public function getAllTasks(Request $request)
    {
        $user = Auth::user();

        $query = Task::query()
            ->where('is_backlog', false)
            ->with(['members', 'team.project', 'attachments']);

        // Filter by team if specified
        if ($request->has('team_id')) {
            $query->where('team_id', $request->team_id);
        }

        // Filter by status if specified
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by priority if specified
        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        // Filter by deadline range if specified
        if ($request->has('deadline_start') && $request->has('deadline_end')) {
            $query->whereBetween('deadline', [$request->deadline_start, $request->deadline_end]);
        }

        // Filter overdue tasks if specified
        if ($request->has('is_overdue') && $request->boolean('is_overdue')) {
            $query->where('deadline', '<', now())
                ->where(function ($q) {
                    $q->where('status', '!=', Task::STATUS_COMPLETED)
                        ->orWhere(function ($q) {
                            $q->where('status', Task::STATUS_COMPLETED)
                                ->whereNotNull('completed_at')
                                ->whereColumn('completed_at', '>', 'deadline');
                        });
                });
        }

        // Get tasks where user is a member or has team/project level access
        $query->where(function ($q) use ($user) {
            $q->whereHas('members', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->orWhereHas('team', function ($q) use ($user) {
                $q->whereHas('projectRoleUsers', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                });
            });
        });

        $tasks = $query->get()
            ->map(function ($task) use ($user) {
                return [
                    'id' => $task->id,
                    'name' => $task->name,
                    'description' => $task->description,
                    'tags' => $task->tags,
                    'priority' => $task->priority,
                    'deadline' => $task->deadline,
                    'status' => $task->status,
                    'status_text' => $task->status_text,
                    'is_overdue' => $task->is_overdue,
                    'completed_at' => $task->completed_at,
                    'completed_on_time' => $task->completed_at && $task->deadline 
                        ? $task->completed_at->lessThanOrEqualTo($task->deadline)
                        : null,
                    'completion_status' => $this->getCompletionStatus($task),
                    'team_id' => $task->team_id,
                    'project_id' => $task->team->project_id,
                    'team_name' => $task->team->name,
                    'project_name' => $task->team->project->name,
                    'assigned_to_me' => $task->members->contains('id', $user->id),
                    'created_at' => $task->created_at,
                    'updated_at' => $task->updated_at,
                    'members' => $task->members->map(function ($member) {
                        return [
                            'id' => $member->id,
                            'name' => $member->name,
                            'color' => $this->getMemberColor($member->id),
                        ];
                    }),
                    'attachments' => $task->attachments->map(function ($attachment) {
                        return [
                            'id' => $attachment->id,
                            'name' => $attachment->name,
                            'media' => $attachment->media,
                            'created_at' => $attachment->created_at,
                        ];
                    }),
                ];
            });

        return $this->success(['tasks' => $tasks], 'All tasks retrieved successfully.');
    }

    /**
     * Get the completion status of a task
     */
    private function getCompletionStatus(Task $task)
    {
        if (!$task->is_completed) {
            return null;
        }

        if (!$task->deadline || !$task->completed_at) {
            return 'completed_no_deadline';
        }

        return $task->completed_at->lessThanOrEqualTo($task->deadline)
            ? 'completed_on_time'
            : 'completed_late';
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
            'attachments'
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

        // Retrieve logs related to the task, notes, and attachments
        $logs = \Spatie\Activitylog\Models\Activity::where(function ($query) use ($task) {
            $query->where('subject_type', Task::class)
                ->where('subject_id', $task->id)
                ->orWhere('subject_type', TaskNote::class)
                ->whereIn('subject_id', $task->notes->pluck('id'))
                ->orWhere('subject_type', Attachment::class)
                ->whereIn('subject_id', $task->attachments->pluck('id'));
        })
            ->orderBy('created_at', 'desc')
            ->get();

        // Filter logs based on the user's role
        $filteredLogs = $logs->filter(function ($log) use ($user, $task) {
            $causerRole = $log->causer ? $log->causer->getRoleInTeam($task->team->project_id) : null;

            // If the log causer is the current user, always include it
            if ($log->causer_id === $user->id) {
                return true;
            }

            // Get the current user's role in the project
            $userRole = $user->getRoleInTeam($task->team->project_id);

            // If the current user is a manager, include all logs
            if ($userRole === 'manager') {
                return true;
            }

            // If the current user is a leader, include logs from themselves, members, and other leaders
            if ($userRole === 'leader') {
                return in_array($causerRole, ['member', 'leader']);
            }

            // If the current user is a member, include logs from themselves and other members
            if ($userRole === 'member') {
                return $causerRole === 'member';
            }

            return false;
        });

        // Format the filtered logs for display
        $formattedLogs = $filteredLogs->map(function ($log) use ($task) {
            $description = $log->description;
            $userName = $log->causer ? $log->causer->name : 'System';

            switch ($log->event) {
                case 'created':
                    if ($log->subject_type === Task::class) {
                        $description = "created the task.";
                    } elseif ($log->subject_type === Attachment::class) {
                        $attachmentName = $log->subject ? $log->subject->name : 'an attachment';
                        $description = "added an attachment: {$attachmentName}.";
                    } elseif ($log->subject_type === TaskNote::class) {
                        $noteDescription = $log->subject ? $log->subject->description : 'a note';
                        $description = "added a note: {$noteDescription}.";
                    }
                    break;

                case 'updated':
                    if ($log->subject_type === Task::class) {
                        $properties = json_decode($log->properties, true);
                        if (isset($properties['old_status']) && isset($properties['new_status'])) {
                            $oldStatus = Task::$statusTexts[$properties['old_status']] ?? 'unknown';
                            $newStatus = Task::$statusTexts[$properties['new_status']] ?? 'unknown';
                            $description = "changed the task status from {$oldStatus} to {$newStatus}.";
                        } else {
                            $description = "updated the task.";
                        }
                    }
                    break;

                case 'viewed':
                    $description = "viewed the task.";
                    break;

                case 'deleted':
                    if ($log->subject_type === Attachment::class) {
                        $attachmentName = $log->subject ? $log->subject->name : 'an attachment';
                        $description = "removed an attachment: {$attachmentName}.";
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
            'team_name' => $task->team->name,
            'project_name' => $task->team->project->name,
            'created_at' => $task->created_at,
            'updated_at' => $task->updated_at,
            'assigned_to_me' => $task->members->contains('id', $user->id),
            'role' => $isManager ? 'manager' : ($isLeader ? 'leader' : 'member'),
            'is_overdue' => $task->is_overdue,
            'members' => $task->members->map(function ($member) use ($task) {
                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'color' => $this->getMemberColor($member->id),
                    'role' => $member->getRoleInTeam($task->team->project_id),
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
            'logs' => $formattedLogs->values(), // Reset keys for JSON response
        ];

        return $this->success(['task' => $formattedTask], 'Task retrieved successfully.');
    }

    // ============= task notes ============

    public function addTaskNote(Request $request, $taskId)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'description' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $task = Task::find($taskId);
        if (!$task) {
            return $this->error('Task not found.', 404);
        }

        $isProjectManager = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('project_id', $task->team->project_id)
            ->where('role_id', Role::ROLE_MANAGER)
            ->whereNull('team_id')
            ->exists();

        $isPartOfTeam = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('team_id', $task->team_id)
            ->whereIn('role_id', [Role::ROLE_MEMBER, Role::ROLE_LEADER])
            ->exists();

        if (!$isPartOfTeam && !$isProjectManager) {
            return $this->error('You are not part of this team.', 403);
        }

        $taskNote = TaskNote::create([
            'task_id' => $taskId,
            'user_id' => $user->id,
            'description' => $request->description,
        ]);

        activity()
            ->causedBy($user)
            ->performedOn($taskNote)
            ->withProperties(['attributes' => $taskNote->getAttributes()])
            ->event('created')
            ->log('Task note added');

        $teamLeader = DB::table('project_role_user')
            ->where('team_id', $task->team_id)
            ->where('role_id', Role::ROLE_LEADER)
            ->join('users', 'project_role_user.user_id', '=', 'users.id')
            ->select('users.*')
            ->first();

        $taskMembers = $task->members;

        if ($teamLeader) {

            // $teamLeader->notify(new TaskNoteAddedNotification($task, $taskNote));

            // Send notification to team leader
            $notification = Notification::create([
                'user_id' => $teamLeader->id,
                'message' => "A note has been added to the task: {$task->name}.",
                'type' => 'info',
                'read' => false,
                'action_url' => NULL,
                'metadata' => [
                    'task_id' => $task->id,
                    'task_name' => $task->name,
                    'team_id' => $task->team_id,
                    'note_description' => $taskNote->description,
                ],
            ]);

            event(new NotificationSent($notification));
        }

        foreach ($taskMembers as $member) {
            if ($member->id !== $user->id) {

                // $member->notify(new TaskNoteAddedNotification($task, $taskNote));

                // Send notification to task members
                $notification = Notification::create([
                    'user_id' => $member->id,
                    'message' => "A note has been added to the task: {$task->name}.",
                    'type' => 'info',
                    'read' => false,
                    'action_url' => NULL,
                    'metadata' => [
                        'task_id' => $task->id,
                        'task_name' => $task->name,
                        'team_id' => $task->team_id,
                        'note_description' => $taskNote->description,
                    ],
                ]);

                event(new NotificationSent($notification));
            }
        }

        return $this->success(['note' => $taskNote], 'Task note added successfully.');
    }

    public function updateTaskStatus(Request $request, $taskId)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'status' => 'required|integer|in:' . implode(',', array_keys(Task::$statusTexts)),
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $task = Task::find($taskId);
        if (!$task) {
            return $this->error('Task not found.', 404);
        }

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
            ->whereNull('team_id')
            ->exists();

        if (!$isTaskMember && !$isTeamLeader && !$isProjectManager) {
            return $this->error('You are not authorized to update the task status.', 403);
        }

        $oldStatus = $task->status;
        $newStatus = $request->status;

        // Handle completed_at timestamp
        if ($newStatus === Task::STATUS_COMPLETED && $oldStatus !== Task::STATUS_COMPLETED) {
            $task->completed_at = now();
        } elseif ($oldStatus === Task::STATUS_COMPLETED && $newStatus !== Task::STATUS_COMPLETED) {
            $task->completed_at = null;
        }

        activity()
            ->causedBy($user)
            ->performedOn($task)
            ->withProperties([
                'old_status' => $task->status_text,
                'new_status' => Task::$statusTexts[$newStatus],
                'completed_at' => $task->completed_at
            ])
            ->event('updated')
            ->log('Task status updated');

        $task->status = $newStatus;
        $task->save();

        // Send notifications
        $teamLeader = DB::table('project_role_user')
            ->where('team_id', $task->team_id)
            ->where('role_id', Role::ROLE_LEADER)
            ->join('users', 'project_role_user.user_id', '=', 'users.id')
            ->select('users.*')
            ->first();

        $taskMembers = $task->members;

        if ($teamLeader) {
            $notification = Notification::create([
                'user_id' => $teamLeader->id,
                'message' => "Task status updated: {$task->name} is now {$task->status_text}",
                'type' => 'info',
                'read' => false,
                'action_url' => NULL,
                'metadata' => [
                    'task_id' => $task->id,
                    'task_name' => $task->name,
                    'team_id' => $task->team_id,
                    'new_status' => $task->status_text,
                    'completed_at' => $task->completed_at,
                    'completed_on_time' => $task->completed_at && $task->deadline 
                        ? $task->completed_at->lessThanOrEqualTo($task->deadline)
                        : null,
                ],
            ]);

            event(new NotificationSent($notification));
        }

        foreach ($taskMembers as $member) {
            if ($member->id !== $user->id) {
                $notification = Notification::create([
                    'user_id' => $member->id,
                    'message' => "Task status updated: {$task->name} is now {$task->status_text}",
                    'type' => 'info',
                    'read' => false,
                    'action_url' => NULL,
                    'metadata' => [
                        'task_id' => $task->id,
                        'task_name' => $task->name,
                        'team_id' => $task->team_id,
                        'new_status' => $task->status_text,
                        'completed_at' => $task->completed_at,
                        'completed_on_time' => $task->completed_at && $task->deadline 
                            ? $task->completed_at->lessThanOrEqualTo($task->deadline)
                            : null,
                    ],
                ]);

                event(new NotificationSent($notification));
            }
        }

        // Enhanced response with additional task information
        return $this->success([
            'status' => $task->status,
            'status_text' => $task->status_text,
            'completed_at' => $task->completed_at,
            'completed_on_time' => $task->completed_at && $task->deadline 
                ? $task->completed_at->lessThanOrEqualTo($task->deadline)
                : null,
            'is_overdue' => $task->deadline && !$task->is_backlog && 
                ($task->status !== Task::STATUS_COMPLETED || 
                ($task->completed_at && $task->completed_at->greaterThan($task->deadline))),
            'deadline' => $task->deadline,
            'updated_at' => $task->updated_at,
            'updated_by' => [
                'id' => $user->id,
                'name' => $user->name
            ]
        ], 'Task status updated successfully.');
    }
}
