<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use App\Models\Role;
use App\Models\Project;
use App\Models\TaskMember;
use App\Models\Team;
use App\Traits\ResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    use ResponseTrait;

    /**
     * Get general dashboard data for user
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
            'projects_count' => $projectsData,
            'teams_count' => $teamsData,
            'progress_percentage' => $progressPercentage,
            'completion_trend' => $completionTrend,
            'tasks_by_priority' => $tasksByPriority,
            'workload_by_project' => $workloadByProject,
        ]);
    }

    /**
     * Get project-specific dashboard data
     */
    public function getProjectDashboard(Request $request, $projectId)
    {
        $user = $request->user();
        $project = Project::find($projectId);
        
        if (!$project) {
            return $this->error('Project Not Found.', 404);
        }
        
        // Get user role in project
        $role = $this->getUserProjectRole($user, $project);
        
        if ($role === 'manager') {
            // Manager dashboard
            return $this->getManagerProjectDashboard($user, $project);
        } else {
            // Member/Leader dashboard
            return $this->error('Not authraized to view this data.', 403);
        }
    }

    /**
     * Get manager-specific project dashboard
     */
    protected function getManagerProjectDashboard(User $user, Project $project)
    {
        // 1. Get task counts by status for entire project
        $taskCounts = $this->getProjectTaskCounts($project);

        // 2. Get teams progress data
        $teamsProgress = $this->getTeamsProgress($project);

        // 3. Get project metrics
        $metrics = $this->getProjectMetrics($project);

        // 4. Get monthly progress per team
        $monthlyProgress = $this->getMonthlyTeamProgress($project);

        // Calculate overall progress percentage
        $totalTasks = array_sum($taskCounts);
        $completedTasks = $taskCounts[Task::STATUS_COMPLETED] ?? 0;
        $progressPercentage = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

        return $this->success([
            'role' => 'manager',
            'task_counts' => [
                'pending' => $taskCounts[Task::STATUS_PENDING] ?? 0,
                'in_progress' => $taskCounts[Task::STATUS_IN_PROGRESS] ?? 0,
                'completed' => $taskCounts[Task::STATUS_COMPLETED] ?? 0,
                'cancelled' => $taskCounts[Task::STATUS_CANCELLED] ?? 0,
                'on_hold' => $taskCounts[Task::STATUS_ON_HOLD] ?? 0,
                'in_review' => $taskCounts[Task::STATUS_IN_REVIEW] ?? 0,
                'total' => $totalTasks,
            ],
            'teams_progress' => $teamsProgress,
            'project_metrics' => [
                'overall_progress' => $progressPercentage,
                'tasks_at_risk' => $metrics['tasks_at_risk'],
                'in_progress_tasks' => $metrics['in_progress_tasks'],
                'completed_tasks' => $metrics['completed_tasks']
            ],
            'monthly_progress' => $monthlyProgress
        ]);
    }

    /**
     * Get member/leader project dashboard
     */
    protected function getMemberProjectDashboard(User $user, Project $project, $role)
    {
        // 1. Get task counts by status for the user in this project
        $taskCounts = $this->getUserTaskCounts($user, $project->id);

        // 2. Get tasks by priority (excluding completed and cancelled)
        $tasksByPriority = $this->getTasksByPriority($user, $project->id);

        // 3. Calculate progress percentage
        $totalTasks = array_sum($taskCounts);
        $completedTasks = $taskCounts[Task::STATUS_COMPLETED] ?? 0;
        $progressPercentage = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

        return $this->success([
            'role' => $role,
            'task_counts' => [
                'pending' => $taskCounts[Task::STATUS_PENDING] ?? 0,
                'in_progress' => $taskCounts[Task::STATUS_IN_PROGRESS] ?? 0,
                'completed' => $taskCounts[Task::STATUS_COMPLETED] ?? 0,
                'cancelled' => $taskCounts[Task::STATUS_CANCELLED] ?? 0,
                'on_hold' => $taskCounts[Task::STATUS_ON_HOLD] ?? 0,
                'in_review' => $taskCounts[Task::STATUS_IN_REVIEW] ?? 0,
                'total' => $totalTasks,
            ],
            'progress_percentage' => $progressPercentage,
            'tasks_by_priority' => $tasksByPriority,
        ]);
    }

    /**
     * Get user role in project
     */
    protected function getUserProjectRole(User $user, Project $project)
    {
        // Check if manager
        $isManager = $user->roles()
            ->where('project_id', $project->id)
            ->where('role_id', Role::ROLE_MANAGER)
            ->whereNull('team_id')
            ->exists();

        if ($isManager) {
            return 'manager';
        }

        // Check if leader
        $isLeader = $user->roles()
            ->where('project_id', $project->id)
            ->where('role_id', Role::ROLE_LEADER)
            ->whereNotNull('team_id')
            ->exists();

        if ($isLeader) {
            return 'leader';
        }

        // Default to member
        return 'member';
    }

    /**
     * Get task counts by status for entire project
     */
    protected function getProjectTaskCounts(Project $project)
    {
        return Task::whereHas('team', function($query) use ($project) {
                $query->where('project_id', $project->id);
            })
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    /**
     * Get teams progress data
     */
    protected function getTeamsProgress(Project $project)
    {
        $teams = $project->teams()->withCount([
            'tasks as completed_tasks' => function($query) {
                $query->where('status', Task::STATUS_COMPLETED);
            },
            'tasks as total_tasks'
        ])->get();

        $labels = [];
        $values = [];

        foreach ($teams as $team) {
            $labels[] = $team->name;
            $values[] = $team->total_tasks > 0 
                ? round(($team->completed_tasks / $team->total_tasks) * 100)
                : 0;
        }

        return [
            'labels' => $labels,
            'values' => $values
        ];
    }

    /**
     * Get project metrics
     */
    protected function getProjectMetrics(Project $project)
    {
        $totalTasks = Task::whereHas('team', function($query) use ($project) {
            $query->where('project_id', $project->id);
        })->count();

        $completedTasks = Task::whereHas('team', function($query) use ($project) {
            $query->where('project_id', $project->id);
        })->where('status', Task::STATUS_COMPLETED)->count();

        $inProgressTasks = Task::whereHas('team', function($query) use ($project) {
            $query->where('project_id', $project->id);
        })->whereIn('status', [Task::STATUS_IN_PROGRESS, Task::STATUS_IN_REVIEW])
          ->count();
        
        // Tasks at risk - overdue tasks that are pending/in progress/in review
        $tasksAtRisk = Task::whereHas('team', function($query) use ($project) {
            $query->where('project_id', $project->id);
        })->where('deadline', '<', now())
          ->whereIn('status', [
              Task::STATUS_PENDING,
              Task::STATUS_IN_PROGRESS,
              Task::STATUS_IN_REVIEW
          ])->count();

        return [
            'tasks_at_risk' => $tasksAtRisk,
            'in_progress_tasks' => $inProgressTasks,
            'completed_tasks' => $completedTasks
        ];
    }

    /**
     * Get monthly progress per team
     */
    protected function getMonthlyTeamProgress(Project $project)
    {
        $startDate = now()->subYear()->startOfMonth();
        $endDate = now()->endOfMonth();
        
        // Get all teams in project
        $teams = $project->teams()->pluck('name', 'id');
        $result = [];
        
        // Initialize structure for each team
        foreach ($teams as $teamId => $teamName) {
            $result[$teamName] = [
                'labels' => [],
                'values' => []
            ];
            
            $currentDate = $startDate->copy();
            while ($currentDate <= $endDate) {
                $monthKey = $currentDate->format('Y-m');
                $result[$teamName]['labels'][] = $monthKey;
                $result[$teamName]['values'][] = 0;
                $currentDate->addMonth();
            }
        }
        
        // Get completion data for all teams
        $completionData = DB::table('tasks')
            ->join('teams', 'tasks.team_id', '=', 'teams.id')
            ->where('teams.project_id', $project->id)
            ->where('tasks.status', Task::STATUS_COMPLETED)
            ->whereBetween('tasks.updated_at', [$startDate, $endDate])
            ->select(
                'tasks.team_id',
                DB::raw('YEAR(tasks.updated_at) as year'),
                DB::raw('MONTH(tasks.updated_at) as month'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('tasks.team_id', 'year', 'month')
            ->get();
        
        // Calculate total tasks per team per month
        $totalTasksData = DB::table('tasks')
            ->join('teams', 'tasks.team_id', '=', 'teams.id')
            ->where('teams.project_id', $project->id)
            ->whereBetween('tasks.created_at', [$startDate, $endDate])
            ->select(
                'tasks.team_id',
                DB::raw('YEAR(tasks.created_at) as year'),
                DB::raw('MONTH(tasks.created_at) as month'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('tasks.team_id', 'year', 'month')
            ->get();
        
        // Fill in the progress values
        foreach ($teams as $teamId => $teamName) {
            $currentDate = $startDate->copy();
            $monthIndex = 0;
            
            while ($currentDate <= $endDate) {
                $year = $currentDate->year;
                $month = $currentDate->month;
                
                // Get completed tasks for this team/month
                $completed = $completionData->first(function ($item) use ($teamId, $year, $month) {
                    return $item->team_id == $teamId && 
                           $item->year == $year && 
                           $item->month == $month;
                });
                
                // Get total tasks for this team/month
                $total = $totalTasksData->first(function ($item) use ($teamId, $year, $month) {
                    return $item->team_id == $teamId && 
                           $item->year == $year && 
                           $item->month == $month;
                });
                
                $completedCount = $completed ? $completed->count : 0;
                $totalCount = $total ? $total->count : 0;
                
                $result[$teamName]['values'][$monthIndex] = $totalCount > 0 
                    ? round(($completedCount / $totalCount) * 100)
                    : 0;
                
                $currentDate->addMonth();
                $monthIndex++;
            }
        }
        
        return $result;
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
     * Get task counts by status for the user (optionally filtered by project)
     */
    protected function getUserTaskCounts(User $user, $projectId = null)
    {
        $query = TaskMember::where('user_id', $user->id)
            ->join('tasks', 'task_members.task_id', '=', 'tasks.id');
        
        if ($projectId) {
            $query->join('teams', 'tasks.team_id', '=', 'teams.id')
                 ->where('teams.project_id', $projectId);
        }
        
        return $query->select('tasks.status', DB::raw('count(*) as count'))
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
     * Get tasks grouped by priority (excluding completed and cancelled, optionally filtered by project)
     */
    protected function getTasksByPriority(User $user, $projectId = null)
    {
        $query = TaskMember::where('user_id', $user->id)
            ->join('tasks', 'task_members.task_id', '=', 'tasks.id')
            ->whereNotIn('tasks.status', [Task::STATUS_COMPLETED, Task::STATUS_CANCELLED]);
        
        if ($projectId) {
            $query->join('teams', 'tasks.team_id', '=', 'teams.id')
                 ->where('teams.project_id', $projectId);
        }
        
        $priorityCounts = $query->select('tasks.priority', DB::raw('count(*) as count'))
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