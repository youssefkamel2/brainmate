<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskMember;
use App\Models\Team;
use Illuminate\Http\Request;
use App\Traits\ResponseTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;


class TaskController extends Controller
{

    use ResponseTrait;
    public function getAllTasks()
    {

        $tasks = Task::with('members')->get();

        return $this->success(['tasks' => $tasks], 'Tasks Retrived Successfully');
    }

    public function getAssignedTasks()
    {

        $tasks = Task::whereHas('members', function ($query) {
            $query->where('user_id', Auth::id());
        })->with(['members'])->get();

        return $this->success(['Tasks' => $tasks], 'Tasks Retrived Successfully');
    }

    public function createTask(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'team_id' => 'exists:teams,id',
            'description' => 'nullable|string',
            'tags' => 'nullable|string',
            'priority' => 'required|in:low,medium,high',
            'deadline' => 'date',
            'members' => 'required|array',
            'members.*' => 'exists:users,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $team = Team::find($request['team_id']);
        $project_id = $team->project_id;

        // Create the task
        $task = Task::create([
            'name' => $request['name'],
            'team_id' => $request['team_id'],
            'description' => $request['description'] ?? null,
            'tags' => $request['tags'] ?? null,
            'priority' => $request['priority'],
            'deadline' => $request['deadline'] ?? null,
            'status' => true, // Default active status
        ]);

        // Add members to the task
        if (!empty($request['members'])) {
            foreach ($request['members'] as $memberId) {
                TaskMember::create([
                    'task_id' => $task->id,
                    'team_id' => $request['team_id'],
                    'project_id' => $project_id,
                    'user_id' => $memberId,
                ]);
            }
        }

        return $this->success(['task' => $task], 'Task created successfully.', 201);
    }

    // Update a task
    public function updateTask(Request $request, $task_id)
    {

        $task = Task::find($task_id);
        if (!$task) {
            return $this->error('Task not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'team_id' => 'required|exists:teams,id',
            'description' => 'nullable|string',
            'tags' => 'nullable|string',
            'priority' => 'required|in:low,medium,high',
            'deadline' => 'required|date',
            'members' => 'required|array',
            'members.*' => 'exists:users,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $team = Team::find($request['team_id']);
        $project_id = $team->project_id;
        
        // Update task attributes
        $task->update([
            'name' => $request['name'],
            'team_id' => $request['team_id'],
            'description' => $request['description'],
            'tags' => $request['tags'],
            'priority' => $request['priority'],
            'deadline' => $request['deadline'],
            'status' => $request['status'] ?? $task->status,
        ]);

        // Remove all existing task members and add new members
        TaskMember::where('task_id', $task->id)->delete();

        if (!empty($request['members'])) {
            foreach ($request['members'] as $memberId) {
                TaskMember::create([
                    'task_id' => $task->id,
                    'team_id' => $request['team_id'],
                    'project_id' => $project_id,
                    'user_id' => $memberId,
                ]);
            }
        }

        return $this->success($task, 'Task updated successfully.');
    }

    // Delete a task
    public function deleteTask($task_id)
    {

        $task = Task::find($task_id);
        if (!$task) {
            return $this->error('Task not found', 404);
        }

        // Check if the user is authorized (Team leader)
        $team = Team::findOrFail($task->team_id);
        $leader_id = $team->leader_id;

        if ($leader_id !== Auth::id()) {
            return $this->error('You are not authorized to delete this task.', 403);
        }

        // Delete task members
        TaskMember::where('task_id', $task->id)->delete();

        // Delete the task
        $task->delete();

        return $this->success(null, 'Task deleted successfully.');
    }

    // get task by id
    public function getTaskById($task_id)
{
    // Retrieve the task with its members
    $task = Task::with('members')->find($task_id);

    if (!$task) {
        return $this->error('Task not found', 404);
    }

    return $this->success(['task' => $task], 'Task retrieved successfully.');
}
}
