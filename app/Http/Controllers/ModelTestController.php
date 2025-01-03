<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Team;
use App\Models\Reminder;
use App\Models\Attachment;
use App\Models\TaskNote;
use App\Models\TaskMember;
use App\Models\Workspace;

class ModelTestController extends Controller
{
    public function testModels()
    {
        // Example data fetching with relationships
        $project = Project::with('leader.role')->get(); // Assuming 'leader' is a relationship in Project model
        $task = Task::with('team', 'members')->get(); // Load project and team relationships
        $user = User::with('role')->first(); // Assuming 'role' is a relationship in User model
        $team = Team::with('tasks.members', 'members')->get(); // Load project relationship
        $reminder = Reminder::with(['task.members', 'user'])->get(); // Assuming 'creator' is a relationship in Reminder model
        $attachment = Attachment::with('task')->first(); // Load task relationship
        $taskNote = TaskNote::with(['task', 'user'])->first(); // Load task and user relationships
        $taskMember = TaskMember::with(['task', 'user'])->first(); // Load task and user relationships
        $workspace = Workspace::first(); // No foreign keys to load

        return response()->json([
            // 'project' => $project,
            // 'task' => $task,
            // 'user' => $user,
            // 'team' => $team,
            'reminder' => $reminder,
            // 'attachment' => $attachment,
            // 'task_note' => $taskNote,
            // 'task_member' => $taskMember,
            // 'workspace' => $workspace,
        ]);
    }
}
