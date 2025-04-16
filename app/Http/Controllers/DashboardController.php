<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use App\Models\Project;
use App\Models\TaskMember;
use App\Traits\ResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    use ResponseTrait;

    /**
     * Get dashboard data before selecting a project or team
     */
    public function getGeneralDashboard(Request $request)
    {
        $user = $request->user();

        // 1. Get task counts by status for the user
        $taskCounts = $this->getUserTaskCounts($user);

        // 2. Get projects and teams count with change percentage
        $projectsData = $this->getProjectsCountWithChange($user);
        $teamsData = $this->getTeamsCountWithChange($user);

        // 3. Get task completion rate over last year (monthly)
        $completionTrend = $this->getCompletionTrend($user);

        // 4. Get tasks by priority (excluding completed and cancelled)
        $tasksByPriority = $this->getTasksByPriority($user);

        // 5. Get workload distribution by project
        $workloadByProject = $this->getWorkloadByProject($user);

        // 6. Calculate overall progress percentage
        $totalTasks = array_sum($taskCounts);
        $completedTasks = $taskCounts[Task::STATUS_COMPLETED] ?? 0;
        $progressPercentage = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

        return $this->success([
            'task_counts' => [
                'pending' => $taskCounts[Task::STATUS_PENDING] ?? 0,
                'in_progress' => $taskCounts[Task::STATUS_IN_PROGRESS] ?? 0,
                'completed' => $taskCounts[Task::STATUS_COMPLETED] ?? 0,
                'cancelled' => $taskCounts[Task::STATUS_CANCELLED] ?? 0,
                'on_hold' => $taskCounts[Task::STATUS_ON_HOLD] ?? 0,
                'in_review' => $taskCounts[Task::STATUS_IN_REVIEW] ?? 0,
                'total' => $totalTasks,
            ],
            'projects_count' => [
                'current' => $projectsData['current'],
                'previous' => $projectsData['previous'],
                'change_percentage' => $projectsData['change_percentage'],
                'trend' => $projectsData['trend'],
            ],
            'teams_count' => [
                'current' => $teamsData['current'],
                'previous' => $teamsData['previous'],
                'change_percentage' => $teamsData['change_percentage'],
                'trend' => $teamsData['trend'],
            ],
            'progress_percentage' => $progressPercentage,
            'completion_trend' => [
                'labels' => $completionTrend['labels'],
                'values' => $completionTrend['values']
            ],
            'tasks_by_priority' => $tasksByPriority,
            'workload_by_project' => [
                'labels' => $workloadByProject['labels'],
                'values' => $workloadByProject['values']
            ],
        ]);
    }

    /**
     * Get projects count with percentage change from last month
     */
    protected function getProjectsCountWithChange(User $user)
    {
        // Current count - all projects user has access to (manager or member)
        $currentCount = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->distinct('project_id')
            ->count('project_id');

        // Previous month count - projects user had access to at the end of last month
        $previousCount = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('created_at', '<=', Carbon::now()->subMonth()->endOfMonth())
            ->distinct('project_id')
            ->count('project_id');

        return $this->calculateChangeData($currentCount, $previousCount);
    }

    /**
     * Get teams count with percentage change from last month
     */
    protected function getTeamsCountWithChange(User $user)
    {
        // Current count - all teams user has access to (excluding manager roles where team_id is null)
        $currentCount = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->whereNotNull('team_id')
            ->distinct('team_id')
            ->count('team_id');

        // Previous month count - teams user had access to at the end of last month
        $previousCount = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->whereNotNull('team_id')
            ->where('created_at', '<=', Carbon::now()->subMonth()->endOfMonth())
            ->distinct('team_id')
            ->count('team_id');

        return $this->calculateChangeData($currentCount, $previousCount);
    }

    /**
     * Calculate change percentage and trend between two values
     */
    protected function calculateChangeData($current, $previous)
    {
        if ($previous == 0) {
            // Handle division by zero (no previous data)
            $changePercentage = $current > 0 ? 100 : 0;
            $trend = $current > 0 ? 'up' : 'neutral';
        } else {
            $changePercentage = round((($current - $previous) / $previous) * 100);
            $trend = $current > $previous ? 'up' : ($current < $previous ? 'down' : 'neutral');
        }

        return [
            'current' => $current,
            'previous' => $previous,
            'change_percentage' => abs($changePercentage),
            'trend' => $trend,
        ];
    }

    /**
     * Get task counts by status for the user
     */
    protected function getUserTaskCounts(User $user)
    {
        return TaskMember::where('user_id', $user->id)
            ->join('tasks', 'task_members.task_id', '=', 'tasks.id')
            ->select('tasks.status', DB::raw('count(*) as count'))
            ->groupBy('tasks.status')
            ->pluck('count', 'status')
            ->toArray();
    }

    /**
     * Get completion trend (tasks completed per month for last year)
     */
    protected function getCompletionTrend(User $user)
    {
        $startDate = now()->subYear()->startOfMonth();
        $endDate = now()->endOfMonth();
    
        $completionData = TaskMember::where('user_id', $user->id)
            ->join('tasks', 'task_members.task_id', '=', 'tasks.id')
            ->where('tasks.status', Task::STATUS_COMPLETED)
            ->whereBetween('tasks.updated_at', [$startDate, $endDate])
            ->select(
                DB::raw('YEAR(tasks.updated_at) as year'),
                DB::raw('MONTH(tasks.updated_at) as month'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();
    
        // Format the data for chart
        $labels = [];
        $values = [];
        $currentDate = $startDate->copy();
    
        while ($currentDate <= $endDate) {
            $monthKey = $currentDate->format('Y-m');
            $labels[] = $monthKey;
            $values[] = 0; // Default value
            $currentDate->addMonth();
        }
    
        // Fill in actual values
        foreach ($completionData as $data) {
            $monthKey = sprintf('%04d-%02d', $data->year, $data->month);
            $index = array_search($monthKey, $labels);
            if ($index !== false) {
                $values[$index] = $data->count;
            }
        }
    
        return [
            'labels' => $labels,
            'values' => $values
        ];
    }

    /**
     * Get tasks grouped by priority (excluding completed and cancelled)
     */
    protected function getTasksByPriority(User $user)
    {
        // Get counts from database
        $priorityCounts = TaskMember::where('user_id', $user->id)
            ->join('tasks', 'task_members.task_id', '=', 'tasks.id')
            ->whereNotIn('tasks.status', [Task::STATUS_COMPLETED, Task::STATUS_CANCELLED])
            ->select('tasks.priority', DB::raw('count(*) as count'))
            ->groupBy('tasks.priority')
            ->pluck('count', 'priority')
            ->toArray();
    
        $allPriorities = [
            'low' => 0,
            'medium' => 0,
            'high' => 0
        ];
    
        foreach ($priorityCounts as $priority => $count) {
            if (array_key_exists($priority, $allPriorities)) {
                $allPriorities[$priority] = $count;
            }
        }
    
        return $allPriorities;
    }
    /**
     * Get workload distribution by project
     */
    protected function getWorkloadByProject(User $user)
    {
        $projects = TaskMember::where('user_id', $user->id)
            ->join('projects', 'task_members.project_id', '=', 'projects.id')
            ->select(
                'projects.id',
                'projects.name',
                DB::raw('count(*) as task_count')
            )
            ->groupBy('projects.id', 'projects.name')
            ->orderByDesc('task_count')
            ->get();
    
        $labels = [];
        $values = [];
    
        foreach ($projects as $project) {
            $labels[] = $project->name;
            $values[] = $project->task_count;
        }
    
        return [
            'labels' => $labels,
            'values' => $values
        ];
    }
}