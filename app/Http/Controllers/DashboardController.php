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

        // 4. Get overall monthly progress (modified this part)
        $monthlyProgress = $this->getOverallMonthlyProgress($project);

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
        return Task::whereHas('team', function ($query) use ($project) {
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
            'tasks as completed_tasks' => function ($query) {
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
        $totalTasks = Task::whereHas('team', function ($query) use ($project) {
            $query->where('project_id', $project->id);
        })->count();

        $completedTasks = Task::whereHas('team', function ($query) use ($project) {
            $query->where('project_id', $project->id);
        })->where('status', Task::STATUS_COMPLETED)->count();

        $inProgressTasks = Task::whereHas('team', function ($query) use ($project) {
            $query->where('project_id', $project->id);
        })->whereIn('status', [Task::STATUS_IN_PROGRESS, Task::STATUS_IN_REVIEW])
            ->count();

        // Tasks at risk - overdue tasks that are pending/in progress/in review
        $tasksAtRisk = Task::whereHas('team', function ($query) use ($project) {
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

    protected function getOverallMonthlyProgress(Project $project)
    {
        $startDate = now()->subYear()->startOfMonth();
        $endDate = now()->endOfMonth();

        // Initialize result structure
        $result = [
            'labels' => [],
            'values' => []
        ];

        // Generate all month labels
        $currentDate = $startDate->copy();
        while ($currentDate <= $endDate) {
            $result['labels'][] = $currentDate->format('Y-m');
            $result['values'][] = 0;
            $currentDate->addMonth();
        }

        // Get completion data for all teams in project
        $completionData = DB::table('tasks')
            ->join('teams', 'tasks.team_id', '=', 'teams.id')
            ->where('teams.project_id', $project->id)
            ->where('tasks.status', Task::STATUS_COMPLETED)
            ->whereBetween('tasks.updated_at', [$startDate, $endDate])
            ->select(
                DB::raw('YEAR(tasks.updated_at) as year'),
                DB::raw('MONTH(tasks.updated_at) as month'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('year', 'month')
            ->get();

        // Get total tasks data for all teams in project
        $totalTasksData = DB::table('tasks')
            ->join('teams', 'tasks.team_id', '=', 'teams.id')
            ->where('teams.project_id', $project->id)
            ->whereBetween('tasks.created_at', [$startDate, $endDate])
            ->select(
                DB::raw('YEAR(tasks.created_at) as year'),
                DB::raw('MONTH(tasks.created_at) as month'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('year', 'month')
            ->get();

        // Fill in the progress values
        $currentDate = $startDate->copy();
        $monthIndex = 0;

        while ($currentDate <= $endDate) {
            $year = $currentDate->year;
            $month = $currentDate->month;

            // Get completed tasks for this month
            $completed = $completionData->first(function ($item) use ($year, $month) {
                return $item->year == $year && $item->month == $month;
            });

            // Get total tasks for this month
            $total = $totalTasksData->first(function ($item) use ($year, $month) {
                return $item->year == $year && $item->month == $month;
            });

            $completedCount = $completed ? $completed->count : 0;
            $totalCount = $total ? $total->count : 0;

            $result['values'][$monthIndex] = $totalCount > 0
                ? round(($completedCount / $totalCount) * 100)
                : 0;

            $currentDate->addMonth();
            $monthIndex++;
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

    /**
     * Get team dashboard data for a member
     */
    public function getTeamMemberDashboard(Request $request, $teamId)
    {
        $user = $request->user();
        $team = Team::find($teamId);

        if (!$team) {
            return $this->error('Team Not Found.', 404);
        }

        // Check if user is a member of this team
        $isMember = $user->roles()
            ->where('team_id', $team->id)
            ->exists();

        if (!$isMember) {
            return $this->error('Not authorized to view this data.', 403);
        }

        // Get user role in team
        $role = $this->getUserTeamRole($user, $team);

        // 1. Get overdue and at risk tasks for current user
        $taskAlerts = $this->getUserTaskAlerts($user, $team->id);

        // 2. Get total tasks duration per month for current user
        $monthlyDuration = $this->getUserMonthlyTaskDuration($user, $team->id);

        // 3. Get average time to complete tasks for current user
        $avgCompletionTime = $this->getUserAvgCompletionTime($user, $team->id);

        // 4. Get team name and user role
        $teamInfo = [
            'name' => $team->name,
            'role' => $role
        ];

        // 5. Get task counts by status for current user
        $taskCounts = $this->getUserTaskCounts($user, null, $team->id);

        // 6. Get task breakdown by priority for current user
        $tasksByPriority = $this->getTasksByPriority($user, null, $team->id);

        // 7. Get task completion rate over year for current user
        $completionTrend = $this->getCompletionTrend($user, null, $team->id);

        return $this->success([
            'task_alerts' => $taskAlerts,
            'monthly_duration' => $monthlyDuration,
            'avg_completion_time' => $avgCompletionTime,
            'team_info' => $teamInfo,
            'task_counts' => [
                'pending' => $taskCounts[Task::STATUS_PENDING] ?? 0,
                'in_progress' => $taskCounts[Task::STATUS_IN_PROGRESS] ?? 0,
                'completed' => $taskCounts[Task::STATUS_COMPLETED] ?? 0,
                'cancelled' => $taskCounts[Task::STATUS_CANCELLED] ?? 0,
                'on_hold' => $taskCounts[Task::STATUS_ON_HOLD] ?? 0,
                'in_review' => $taskCounts[Task::STATUS_IN_REVIEW] ?? 0,
                'total' => array_sum($taskCounts),
            ],
            'tasks_by_priority' => $tasksByPriority,
            'completion_trend' => $completionTrend
        ]);
    }

    /**
     * Get team dashboard data for a leader/manager
     */
    public function getTeamLeaderDashboard(Request $request, $teamId)
    {
        $user = $request->user();
        $team = Team::find($teamId);

        if (!$team) {
            return $this->error('Team Not Found.', 404);
        }

        
        // Get user role in team
        $role = $this->getUserTeamRole($user, $team);

        // Check if user is a leader or manager of this team

        if ($role == 'member') {
            return $this->error('Not authorized to view this data.', 403);
        }
        
        // 1. Get overdue and at risk tasks for whole team
        $taskAlerts = $this->getTeamTaskAlerts($team->id);

        // 2. Get workload distribution across team members
        $workloadDistribution = $this->getTeamWorkloadDistribution($team->id);

        // 3. Get team progress percentage
        $teamProgress = $this->getTeamProgress($team->id);

        // 4. Get average time to complete tasks for whole team
        $avgCompletionTime = $this->getTeamAvgCompletionTime($team->id);

        // 5. Get team name and user role
        $teamInfo = [
            'name' => $team->name,
            'role' => $role
        ];

        // task counts
        // get total tasks per status in this team
        $pendingTasks = Task::where('team_id', $team->id)
            ->where('status', Task::STATUS_PENDING)
            ->count();
        $inProgressTasks = Task::where('team_id', $team->id)
            ->where('status', Task::STATUS_IN_PROGRESS)
            ->count();
        $completedTasks = Task::where('team_id', $team->id)
            ->where('status', Task::STATUS_COMPLETED)
            ->count();
        $cancelledTasks = Task::where('team_id', $team->id)
            ->where('status', Task::STATUS_CANCELLED)
            ->count();
        $onHoldTasks = Task::where('team_id', $team->id)
            ->where('status', Task::STATUS_ON_HOLD)
            ->count();
        $inReviewTasks = Task::where('team_id', $team->id)
            ->where('status', Task::STATUS_IN_REVIEW)
            ->count();
        $taskCounts = [
            'pending' => $pendingTasks,
            'in_progress' => $inProgressTasks,
            'completed' => $completedTasks,
            'cancelled' => $cancelledTasks,
            'on_hold' => $onHoldTasks,
            'in_review' => $inReviewTasks,
            'total' => $pendingTasks + $inProgressTasks + $completedTasks + $cancelledTasks + $onHoldTasks + $inReviewTasks
        ];


        // 6. Get task breakdown by priority for whole team
        $tasksByPriority = $this->getTeamTasksByPriority($team->id);
        

        // 7. Get task completion rate over year for whole team
        $completionTrend = $this->getTeamCompletionTrend($team->id);

        return $this->success([
            'task_alerts' => $taskAlerts,
            'workload_distribution' => $workloadDistribution,
            'team_progress' => $teamProgress,
            'avg_completion_time' => $avgCompletionTime,
            'team_info' => $teamInfo,
            'task_counts' => $taskCounts,
            'tasks_by_priority' => $tasksByPriority,
            'completion_trend' => $completionTrend
        ]);
    }

    /**
     * Get user role in team
     */
    protected function getUserTeamRole(User $user, Team $team)
    {
        // Check if manager
        $isManager = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('project_id', $team->project_id)
            ->where('role_id', Role::ROLE_MANAGER)
            ->whereNull('team_id')
            ->exists();

        if ($isManager) {
            return 'manager';
        }

        //  Check if leader
        $isLeader = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('team_id', $team->id)
            ->where('role_id', Role::ROLE_LEADER)
            ->exists();
        if ($isLeader) {
            return 'leader';
        }
        // Default to member 

        return 'member';
    }

    /**
     * Get overdue and at risk tasks for user in team
     */

    protected function getUserTaskAlerts(User $user, $teamId)
    {
        // Overdue tasks (have deadline and it's passed)
        $overdue = TaskMember::where('user_id', $user->id)
            ->join('tasks', 'task_members.task_id', '=', 'tasks.id')
            ->where('tasks.team_id', $teamId)
            ->whereNotNull('tasks.deadline')
            ->where('tasks.deadline', '<', now())
            ->whereIn('tasks.status', [Task::STATUS_PENDING, Task::STATUS_IN_PROGRESS, Task::STATUS_IN_REVIEW])
            ->count();

        // At risk tasks (have deadline and it's within 3 days)
        $atRisk = TaskMember::where('user_id', $user->id)
            ->join('tasks', 'task_members.task_id', '=', 'tasks.id')
            ->where('tasks.team_id', $teamId)
            ->whereNotNull('tasks.deadline')
            ->whereBetween('tasks.deadline', [now(), now()->addDays(3)])
            ->whereIn('tasks.status', [Task::STATUS_PENDING, Task::STATUS_IN_PROGRESS, Task::STATUS_IN_REVIEW])
            ->count();

        return [
            'overdue' => $overdue,
            'at_risk' => $atRisk
        ];
    }

    /**
     * Get total tasks duration per month for user in team
     */

    protected function getUserMonthlyTaskDuration(User $user, $teamId)
    {
        $startDate = now()->subYear()->startOfMonth();
        $endDate = now()->endOfMonth();

        $durationData = TaskMember::where('user_id', $user->id)
            ->join('tasks', 'task_members.task_id', '=', 'tasks.id')
            ->where('tasks.team_id', $teamId)
            ->whereBetween('tasks.created_at', [$startDate, $endDate])
            ->select(
                DB::raw('YEAR(tasks.created_at) as year'),
                DB::raw('MONTH(tasks.created_at) as month'),
                DB::raw('SUM(
                CASE 
                    WHEN tasks.duration_days IS NULL AND tasks.deadline IS NOT NULL 
                    THEN DATEDIFF(tasks.deadline, tasks.created_at)
                    WHEN tasks.duration_days IS NULL 
                    THEN 1
                    ELSE tasks.duration_days
                END
            ) as total_duration')
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
        foreach ($durationData as $data) {
            $monthKey = sprintf('%04d-%02d', $data->year, $data->month);
            $index = array_search($monthKey, $labels);
            if ($index !== false) {
                $values[$index] = (int)$data->total_duration;
            }
        }

        return [
            'labels' => $labels,
            'values' => $values,
            'unit' => 'days' // Make it clear the values represent days
        ];
    }

    /**
     * Get average time to complete tasks for user in team
     */
    protected function getUserAvgCompletionTime(User $user, $teamId)
    {
        // Since we don't have started_at/completed_at, we'll use created_at and updated_at
        // for completed tasks as a rough estimate
        $completedTasks = TaskMember::where('user_id', $user->id)
            ->join('tasks', 'task_members.task_id', '=', 'tasks.id')
            ->where('tasks.team_id', $teamId)
            ->where('tasks.status', Task::STATUS_COMPLETED)
            ->select(
                DB::raw('AVG(TIMESTAMPDIFF(HOUR, tasks.created_at, tasks.updated_at)) as avg_hours')
            )
            ->first();

        // Convert hours to days (assuming 8 working hours per day)
        return $completedTasks ? round($completedTasks->avg_hours / 8, 1) : 0;
    }

    /**
     * Get task counts by status for the user (optionally filtered by project or team)
     */
    protected function getUserTaskCounts(User $user, $projectId = null, $teamId = null)
    {
        $query = TaskMember::where('user_id', $user->id)
            ->join('tasks', 'task_members.task_id', '=', 'tasks.id');

        if ($projectId) {
            $query->join('teams', 'tasks.team_id', '=', 'teams.id')
                ->where('teams.project_id', $projectId);
        }

        if ($teamId) {
            $query->where('tasks.team_id', $teamId);
        }

        return $query->select('tasks.status', DB::raw('count(*) as count'))
            ->groupBy('tasks.status')
            ->pluck('count', 'status')
            ->toArray();
    }

    /**
     * Get completion trend (tasks completed per month for last year)
     * Optionally filtered by project or team
     */

    protected function getCompletionTrend(User $user, $projectId = null, $teamId = null)
    {
        $startDate = now()->subYear()->startOfMonth();
        $endDate = now()->endOfMonth();

        $query = TaskMember::where('user_id', $user->id)
            ->join('tasks', 'task_members.task_id', '=', 'tasks.id')
            ->where('tasks.status', Task::STATUS_COMPLETED)
            ->whereBetween('tasks.updated_at', [$startDate, $endDate]);

        if ($projectId) {
            $query->join('teams', 'tasks.team_id', '=', 'teams.id')
                ->where('teams.project_id', $projectId);
        }

        if ($teamId) {
            $query->where('tasks.team_id', $teamId);
        }

        $completionData = $query->select(
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
     * Optionally filtered by project or team
     */
    protected function getTasksByPriority(User $user, $projectId = null, $teamId = null)
    {
        $query = TaskMember::where('user_id', $user->id)
            ->join('tasks', 'task_members.task_id', '=', 'tasks.id');

        if ($projectId) {
            $query->join('teams', 'tasks.team_id', '=', 'teams.id')
                ->where('teams.project_id', $projectId);
        }

        if ($teamId) {
            $query->where('tasks.team_id', $teamId);
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
     * Get overdue and at risk tasks for whole team
     */
    protected function getTeamTaskAlerts($teamId)
    {
        // Overdue tasks
        $overdue = Task::where('team_id', $teamId)
            ->whereNotNull('deadline')
            ->where('deadline', '<', now())
            ->whereIn('status', [Task::STATUS_PENDING, Task::STATUS_IN_PROGRESS, Task::STATUS_IN_REVIEW])
            ->count();

        // At risk tasks
        $atRisk = Task::where('team_id', $teamId)
            ->whereNotNull('deadline')
            ->whereBetween('deadline', [now(), now()->addDays(3)])
            ->whereIn('status', [Task::STATUS_PENDING, Task::STATUS_IN_PROGRESS, Task::STATUS_IN_REVIEW])
            ->count();

        return [
            'overdue' => $overdue,
            'at_risk' => $atRisk
        ];
    }

    /**
     * Get workload distribution across team members
     */
    protected function getTeamWorkloadDistribution($teamId)
    {
        // First get total number of active tasks in the team (excluding completed/cancelled)
        $totalTasks = Task::where('team_id', $teamId)
            ->count();

        if ($totalTasks == 0) {
            return [
                'labels' => [],
                'values' => [],
                'percentages' => []
            ];
        }

        // Get each member's task count and calculate percentage
        $members = TaskMember::join('tasks', 'task_members.task_id', '=', 'tasks.id')
            ->join('users', 'task_members.user_id', '=', 'users.id')
            ->where('tasks.team_id', $teamId)
            ->select(
                'users.id',
                'users.name',
                DB::raw('count(*) as task_count')
            )
            ->groupBy('users.id', 'users.name')
            ->get()
            ->map(function ($member) use ($totalTasks) {
                $member->percentage = round(($member->task_count / $totalTasks) * 100);
                return $member;
            });

        $labels = [];
        $values = [];
        $percentages = [];

        foreach ($members as $member) {
            $labels[] = $member->name;
            $values[] = $member->task_count;
            $percentages[] = $member->percentage;
        }

        return [
            'labels' => $labels,
            'values' => $values,
            'percentages' => $percentages,
            'total_team_tasks' => $totalTasks
        ];
    }

    /**
     * Get team progress percentage
     */
    protected function getTeamProgress($teamId)
    {
        $completedTasks = Task::where('team_id', $teamId)
            ->where('status', Task::STATUS_COMPLETED)
            ->count();

        $totalTasks = Task::where('team_id', $teamId)
            ->count();

        return $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;
    }

    /**
     * Get average time to complete tasks for whole team
     */

    protected function getTeamAvgCompletionTime($teamId)
    {
        // Using created_at and updated_at as proxies for start/complete times
        $completedTasks = Task::where('team_id', $teamId)
            ->where('status', Task::STATUS_COMPLETED)
            ->select(
                DB::raw('AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as avg_hours')
            )
            ->first();

        // Convert hours to days (assuming 8 working hours per day)
        return $completedTasks ? round($completedTasks->avg_hours / 8, 1) : 0;
    }

    /**
     * Get task breakdown by priority for whole team
     */
    protected function getTeamTasksByPriority($teamId)
    {
        $priorityCounts = Task::where('team_id', $teamId)
            ->select('priority', DB::raw('count(*) as count'))
            ->groupBy('priority')
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
     * Get task completion rate over year for whole team
     */
    protected function getTeamCompletionTrend($teamId)
    {
        $startDate = now()->subYear()->startOfMonth();
        $endDate = now()->endOfMonth();

        $completionData = Task::where('team_id', $teamId)
            ->where('status', Task::STATUS_COMPLETED)
            ->whereBetween('updated_at', [$startDate, $endDate])
            ->select(
                DB::raw('YEAR(updated_at) as year'),
                DB::raw('MONTH(updated_at) as month'),
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
}
