<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Models\Project;
use Illuminate\Support\Str;
use App\Models\Notification as AppNotification;
use Illuminate\Http\Request;
use App\Traits\ResponseTrait;
use App\Events\NotificationSent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Notifications\TeamInvitationNotification;
use Illuminate\Support\Facades\Notification;


class TeamController extends Controller
{
    use ResponseTrait;

    // Create a new team (Manager only)
    public function createTeam(Request $request, $projectId)
    {
        // Get the authenticated user
        $user = Auth::user();

        // Validate the request
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Check if the user is the manager of the project
        $isManager = $user->roles()
            ->where('project_id', $projectId)
            ->where('role_id', Role::ROLE_MANAGER)
            ->exists();

        if (!$isManager) {
            return $this->error('Only the project manager can create teams.', 403);
        }

        // Create the team
        $team = Team::create([
            'name' => $request->name,
            'description' => $request->description,
            'project_id' => $projectId,
            'added_by' => $user->id,
        ]);

        return $this->success(['team' => $team], 'Team created successfully.');
    }

    // Update team details (Manager and Leader)
    public function updateTeam(Request $request, $teamId)
    {
        // Get the authenticated user
        $user = Auth::user();

        // Validate the request
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Find the team
        $team = Team::find($teamId);
        if (!$team) {
            return $this->error('Team not found.', 404);
        }

        // Check if the user is the manager or leader of the team
        $isManagerOrLeader = $user->roles()
            ->where('project_id', $team->project_id)
            ->where(function ($query) use ($team) {
                $query->where('role_id', Role::ROLE_MANAGER)
                    ->orWhere(function ($q) use ($team) {
                        $q->where('role_id', Role::ROLE_LEADER)
                            ->where('team_id', $team->id);
                    });
            })
            ->exists();

        if (!$isManagerOrLeader) {
            return $this->error('Only the manager or team leader can update the team.', 403);
        }

        // Update the team
        $team->update($request->only(['name', 'description']));

        return $this->success(['team' => $team], 'Team updated successfully.');
    }

    // Delete a team (Manager only)
    public function deleteTeam($teamId)
    {
        // Get the authenticated user
        $user = Auth::user();

        // Find the team
        $team = Team::find($teamId);
        if (!$team) {
            return $this->error('Team not found.', 404);
        }

        // Check if the user is the manager of the project
        $isManager = $user->roles()
            ->where('project_id', $team->project_id)
            ->where('role_id', Role::ROLE_MANAGER)
            ->exists();

        if (!$isManager) {
            return $this->error('Only the project manager can delete teams.', 403);
        }

        // Delete the team
        $team->delete();

        return $this->success([], 'Team deleted successfully.');
    }

    // Invite user to team (Manager and Leader)
    public function inviteUserToTeam(Request $request, $teamId)
    {
        // Get the authenticated user
        $user = Auth::user();

        // Validate the request
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'role_id' => 'required|exists:roles,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Find the team
        $team = Team::find($teamId);
        if (!$team) {
            return $this->error('Team not found.', 404);
        }

        // Check if the user is the manager or leader of the team
        $isManagerOrLeader = $user->roles()
            ->where('project_id', $team->project_id)
            ->where(function ($query) use ($team) {
                $query->where('role_id', Role::ROLE_MANAGER)
                    ->orWhere(function ($q) use ($team) {
                        $q->where('role_id', Role::ROLE_LEADER)
                            ->where('team_id', $team->id);
                    });
            })
            ->exists();

        if (!$isManagerOrLeader) {
            return $this->error('Only the manager or team leader can invite users.', 403);
        }

        // Find the user by email
        $invitedUser = User::where('email', $request->email)->first();

        // Check if the invited user is the same as the inviting user
        if ($invitedUser && $invitedUser->id == $user->id) {
            return $this->error('Cannot invite yourself.', 422);
        }

        // Check if the role is manager (only leaders and members can be invited)
        if ($request->role_id == Role::ROLE_MANAGER) {
            return $this->error('You can only invite users as leaders or members.', 422);
        }

        // Check if the user already exists in the team
        if ($invitedUser) {
            $userAlreadyInTeam = DB::table('project_role_user')
                ->where('project_id', $team->project_id)
                ->where('team_id', $teamId)
                ->where('user_id', $invitedUser->id)
                ->exists();

            if ($userAlreadyInTeam) {
                return $this->error('User is already a member of this team.', 422);
            }
        }

        // Check if there's already a pending invitation for this email and team
        $existingInvitation = DB::table('invitations')
            ->where('project_id', $team->project_id)
            ->where('team_id', $teamId)
            ->where('invited_user_id', $invitedUser->id)
            ->whereNull('accepted_at') // Not accepted
            ->whereNull('rejected_at') // Not rejected
            ->first();

        if ($existingInvitation) {
            return $this->error('An invitation is already pending for this user.', 422);
        }

        // If the role is leader, check if there's already a leader
        if ($request->role_id == Role::ROLE_LEADER) {
            DB::table('project_role_user')
                ->where('team_id', $teamId)
                ->where('role_id', Role::ROLE_LEADER)
                ->delete();
        }

        // Generate a unique token for the invitation
        $token = Str::random(60);

        // Store the invitation in the invitations table
        DB::table('invitations')->insert([
            'project_id' => $team->project_id,
            'team_id' => $teamId,
            'invited_by' => $user->id,
            'invited_user_id' => $invitedUser ? $invitedUser->id : null,
            'role_id' => $request->role_id,
            'token' => $token,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // If the user exists, send a system notification (action notification)
        $project = Project::find($team->project_id);
        $role = Role::find($request->role_id)->name;

        if ($invitedUser) {

            // Create a system notification for the invited user
            $notification = AppNotification::create([
                'user_id' => $invitedUser->id,
                'message' => "You have been invited to join the team '{$team->name}' in the project '{$project->name}' as a {$role}.",
                'type' => 'invitation',
                'read' => false,
                'action_url' => NULL,
                'metadata' => [
                    'team_id' => $team->id,
                    'team_name' => $team->name,
                    'project_id' => $project->id,
                    'project_name' => $project->name,
                    'role' => $role,
                    'token' => $token,
                ],
            ]);

            // Broadcast the notification
            event(new NotificationSent($notification));
        } else {

            // If the user does not exist, send an email notification
            Notification::route('mail', $request->email)
                ->notify(new TeamInvitationNotification($team, $project, $role, $token));
        }

        return $this->success([], 'Invitation sent successfully.');
    }

    // accept invite
    public function acceptInvitation(Request $request){
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
        ]);
    
        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }
    
        // Get the authenticated user
        $user = Auth::user();
    
        // Find the invitation by token
        $invitation = DB::table('invitations')
            ->where('token', $request->token)
            ->first();
    
        if (!$invitation) {
            return $this->error('Invalid or expired invitation.', 404);
        }
    
        // Check if the authenticated user is the one who was invited
        if ($user->id != $invitation->invited_user_id) {
            return $this->error('You are not authorized to accept this invitation.', 403);
        }
    
        // Check if the invitation has already been accepted or rejected
        if ($invitation->accepted_at) {
            return $this->error('Invitation has already been accepted.', 400);
        }
        if ($invitation->rejected_at) {
            return $this->error('Invitation has already been rejected.', 400);
        }
    
        // Check if the user is already a member or leader of this team
        $userAlreadyInTeam = DB::table('project_role_user')
            ->where('project_id', $invitation->project_id)
            ->where('team_id', $invitation->team_id)
            ->where('user_id', $user->id)
            ->whereIn('role_id', [Role::ROLE_MEMBER, Role::ROLE_LEADER])
            ->exists();
    
        if ($userAlreadyInTeam) {
            return $this->error('You are already part of this team.', 400);
        }
    
        // Check if the user is already a manager of the project
        $userIsManager = DB::table('project_role_user')
            ->where('project_id', $invitation->project_id)
            ->where('user_id', $user->id)
            ->where('role_id', Role::ROLE_MANAGER)
            ->whereNull('team_id') // Manager has team_id = null
            ->exists();
    
        if ($userIsManager) {
            return $this->error('You are already a manager of this project.', 400);
        }
    
        // Add the user to the project/team
        DB::table('project_role_user')->insert([
            'user_id' => $invitation->invited_user_id,
            'role_id' => $invitation->role_id,
            'project_id' => $invitation->project_id,
            'team_id' => $invitation->team_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    
        // Mark the invitation as accepted
        DB::table('invitations')
            ->where('id', $invitation->id)
            ->update(['accepted_at' => now()]);
    
        // Update the notification type from 'invitation' to 'info'
        DB::table('notifications')
            ->where('metadata->token', $request->token)
            ->where('type', 'invitation')
            ->update(['type' => 'info']);
    
        // Notify the team leader or project manager
        $leader = DB::table('project_role_user')
            ->where('team_id', $invitation->team_id)
            ->where('role_id', Role::ROLE_LEADER)
            ->join('users', 'project_role_user.user_id', '=', 'users.id')
            ->select('users.*')
            ->first();
    
        if ($leader) {
            // Notify the team leader
            $notification = AppNotification::create([
                'user_id' => $leader->id,
                'message' => "User with ID {$invitation->invited_user_id} has accepted the invitation to join the team.",
                'type' => 'info',
                'read' => false,
                'action_url' => NULL,
                'metadata' => [
                    'user_id' => $invitation->invited_user_id,
                    'team_id' => $invitation->team_id,
                ],
            ]);
    
            // Broadcast the notification
            event(new NotificationSent($notification));
        } else {
            // If there's no team leader, notify the project manager
            $projectManager = DB::table('project_role_user')
                ->where('project_id', $invitation->project_id)
                ->where('role_id', Role::ROLE_MANAGER)
                ->whereNull('team_id') // Manager has team_id = null
                ->join('users', 'project_role_user.user_id', '=', 'users.id')
                ->select('users.*')
                ->first();
    
            if ($projectManager) {
                $notification = AppNotification::create([
                    'user_id' => $projectManager->id,
                    'message' => "User with ID {$invitation->invited_user_id} has accepted the invitation to join the team (Team ID: {$invitation->team_id}).",
                    'type' => 'info',
                    'read' => false,
                    'action_url' => NULL,
                    'metadata' => [
                        'user_id' => $invitation->invited_user_id,
                        'team_id' => $invitation->team_id,
                    ],
                ]);
    
                // Broadcast the notification
                event(new NotificationSent($notification));
            }
        }
    
        return $this->success([], 'Invitation accepted successfully.');
    }

    public function rejectInvitation(Request $request) {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
        ]);
    
        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }
    
        // Get the authenticated user
        $user = Auth::user();
    
        // Find the invitation by token
        $invitation = DB::table('invitations')
            ->where('token', $request->token)
            ->first();
    
        if (!$invitation) {
            return $this->error('Invalid or expired invitation.', 404);
        }
    
        // Check if the authenticated user is the one who was invited
        if ($user->id != $invitation->invited_user_id) {
            return $this->error('You are not authorized to reject this invitation.', 403);
        }
    
        // Check if the invitation has already been accepted or rejected
        if ($invitation->accepted_at) {
            return $this->error('Invitation has already been accepted.', 400);
        }
        if ($invitation->rejected_at) {
            return $this->error('Invitation has already been rejected.', 400);
        }
    
        // Mark the invitation as rejected
        DB::table('invitations')
            ->where('id', $invitation->id)
            ->update(['rejected_at' => now()]);
    
        // Update the notification type from 'invitation' to 'info'
        DB::table('notifications')
            ->where('metadata->token', $request->token)
            ->where('type', 'invitation')
            ->update(['type' => 'info']);
    
        // Notify the team leader
        $leader = DB::table('project_role_user')
            ->where('team_id', $invitation->team_id)
            ->where('role_id', Role::ROLE_LEADER)
            ->join('users', 'project_role_user.user_id', '=', 'users.id')
            ->select('users.*')
            ->first();
    
        if ($leader) {
            $notification = AppNotification::create([
                'user_id' => $leader->id,
                'message' => "User with ID {$invitation->invited_user_id} has rejected the invitation to join the team.",
                'type' => 'info',
                'read' => false,
                'action_url' => NULL,
                'metadata' => [
                    'user_id' => $invitation->invited_user_id,
                    'team_id' => $invitation->team_id,
                ],
            ]);
    
            // Broadcast the notification
            event(new NotificationSent($notification));
        }
    
        return $this->success([], 'Invitation rejected successfully.');
    }

    // join team by code
    public function joinTeam(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'team_code' => 'required|string|exists:teams,team_code',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Get the authenticated user
        $user = Auth::user();

        // Find the team by team_code
        $team = Team::where('team_code', $request->team_code)->first();
        if (!$team) {
            return $this->error('Team not found.', 404);
        }

        // Check if the user is the manager of the project
        $isManager = $user->roles()
            ->where('project_id', $team->project_id)
            ->where('role_id', Role::ROLE_MANAGER)
            ->whereNull('team_id') // Manager role has team_id = null
            ->exists();

        if ($isManager) {
            return $this->error('You are already the manager of this project and have access to all teams.', 400);
        }

        // Check if the user is already a member of the team
        $isAlreadyMember = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('team_id', $team->id)
            ->exists();

        if ($isAlreadyMember) {
            return $this->error('You are already a member of this team.', 400);
        }

        // Assign the user to the team as a member
        DB::table('project_role_user')->insert([
            'user_id' => $user->id,
            'role_id' => Role::ROLE_MEMBER, // Assign as member
            'project_id' => $team->project_id,
            'team_id' => $team->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->success([], 'You have successfully joined the team.');
    }
    // Get team details
    public function getTeamDetails($teamId)
    {
        // Find the team
        $team = Team::find($teamId);
        if (!$team) {
            return $this->error('Team not found.', 404);
        }

        // Get the authenticated user
        $user = Auth::user();

        // Get the project ID
        $projectId = $team->project_id;

        // Check if the user is associated with the project
        $userProjectRoles = $user->roles()
            ->where('project_id', $projectId)
            ->get();

        if ($userProjectRoles->isEmpty()) {
            return $this->error('User is not associated with this project.', 403);
        }

        // Check if the user is a project manager (role_id = 1 and team_id = null)
        $isProjectManager = $userProjectRoles->contains(function ($role) {
            return $role->pivot->team_id === null && $role->id === Role::ROLE_MANAGER;
        });

        // Initialize the user's role in the team
        $userRole = null;

        // If the user is a project manager, they have access to all teams
        if ($isProjectManager) {
            $userRole = 'manager';
        } else {
            // Check if the user is a team leader for this team
            $isTeamLeader = $user->roles()
                ->where('project_id', $projectId)
                ->where('team_id', $teamId)
                ->where('role_id', Role::ROLE_LEADER)
                ->exists();

            // Check if the user is a member of the team
            $isTeamMember = $team->members()->where('user_id', $user->id)->exists();

            // Set the role based on the user's role in the team
            if ($isTeamLeader) {
                $userRole = 'leader';
            } elseif ($isTeamMember) {
                $userRole = 'member';
            }
        }

        // Fetch the manager of the project
        $manager = DB::table('project_role_user')
            ->where('project_id', $projectId)
            ->where('role_id', Role::ROLE_MANAGER)
            ->whereNull('team_id')
            ->join('users', 'project_role_user.user_id', '=', 'users.id')
            ->select('users.*', 'project_role_user.role_id')
            ->first();

        // Fetch the leader of the team
        $leader = DB::table('project_role_user')
            ->where('team_id', $teamId)
            ->where('role_id', Role::ROLE_LEADER)
            ->join('users', 'project_role_user.user_id', '=', 'users.id')
            ->select('users.*', 'project_role_user.role_id')
            ->first();

        // Fetch all members of the team (excluding the leader)
        $members = DB::table('project_role_user')
            ->where('team_id', $teamId)
            ->where('role_id', Role::ROLE_MEMBER)
            ->join('users', 'project_role_user.user_id', '=', 'users.id')
            ->select('users.*', 'project_role_user.role_id')
            ->get();

        // Combine all users into a single collection
        $allMembers = collect([]);

        if ($manager) {
            $allMembers->push($manager);
        }

        if ($leader) {
            $allMembers->push($leader);
        }

        if ($members->isNotEmpty()) {
            $allMembers = $allMembers->merge($members);
        }

        // Add the members and the user's role to the team object
        $team->all_members = $allMembers;
        $team->role = $userRole; // Add the user's role to the response
        $team->project_name = Project::find($projectId)->name;

        return $this->success(['team' => $team], 'Team details retrieved successfully.');
    }

    // // List teams in a project
    public function listTeamsInProject(Request $request, $projectId)
    {
        // Get the authenticated user
        $user = Auth::user();

        // Find the project
        $project = Project::find($projectId);
        if (!$project) {
            return $this->error('Project not found.', 404);
        }

        // Check if the user is associated with the project
        $userProjectRoles = $user->roles()
            ->where('project_id', $projectId)
            ->get();

        if ($userProjectRoles->isEmpty()) {
            return $this->error('User is not associated with this project.', 403);
        }

        // Check if the user is a project manager (role_id = 1 and team_id = null)
        $isProjectManager = $userProjectRoles->contains(function ($role) {
            return $role->pivot->team_id === null && $role->id === Role::ROLE_MANAGER;
        });

        // Get all teams in the project
        $teams = $project->teams()->get();

        // Check if the user has access to each team and include their role
        $teamsWithAccess = $teams->map(function ($team) use ($user, $isProjectManager, $projectId) {
            // Initialize the role as null
            $role = null;

            // If the user is a project manager, they have access to all teams
            if ($isProjectManager) {
                $team->hasAccess = true;
                $role = 'manager'; // Assuming role_id 1 is for manager
            } else {
                // Check if the user is a team leader for this team
                $isTeamLeader = $user->roles()
                    ->where('project_id', $projectId)
                    ->where('team_id', $team->id)
                    ->where('role_id', Role::ROLE_LEADER)
                    ->exists();

                // Check if the user is a member of the team
                $isTeamMember = $team->members()->where('user_id', $user->id)->exists();

                // Determine if the user has access to the team
                $hasAccess = $isTeamLeader || $isTeamMember;

                // Add the hasAccess flag to the team
                $team->hasAccess = $hasAccess;

                // Set the role based on the user's role in the team
                if ($isTeamLeader) {
                    $role = 'leader'; // Assuming role_id 2 is for leader
                } elseif ($isTeamMember) {
                    $role = 'member'; // Assuming role_id 3 is for member
                }
            }

            // Add the role to the team object
            $team->role = $role;

            return $team;
        });

        // Add the is_manager flag to the response
        $response = [
            'teams' => $teamsWithAccess,
            'is_manager' => $isProjectManager, // Add the is_manager flag
        ];

        return $this->success($response, 'Teams retrieved successfully.');
    }

    public function removeUserFromTeam(Request $request, $teamId)
    {
        // Get the authenticated user
        $user = Auth::user();

        // Validate the request
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Find the team
        $team = Team::find($teamId);
        if (!$team) {
            return $this->error('Team not found.', 404);
        }

        // Check if the user is the manager or leader of the team
        $isManager = $user->roles()
            ->where('project_id', $team->project_id)
            ->where('role_id', Role::ROLE_MANAGER)
            ->whereNull('team_id')
            ->exists();

        $isLeader = $user->roles()
            ->where('project_id', $team->project_id)
            ->where('role_id', Role::ROLE_LEADER)
            ->where('team_id', $team->id)
            ->exists();

        if (!$isManager && !$isLeader) {
            return $this->error('Only the manager or team leader can remove users from the team.', 403);
        }

        // Find the user to be removed
        $userToRemove = User::find($request->user_id);
        if (!$userToRemove) {
            return $this->error('User not found.', 404);
        }

        // Check the role of the user to be removed
        $userToRemoveRole = DB::table('project_role_user')
            ->where('user_id', $request->user_id)
            ->where('project_id', $team->project_id)
            ->where(function ($query) use ($teamId) {
                $query->where('team_id', $teamId)
                    ->orWhereNull('team_id'); // Include manager (team_id = null)
            })
            ->value('role_id');

        if (!$userToRemoveRole) {
            return $this->error('User is not associated with this project or team.', 404);
        }

        // Manager can delete leader or member
        if ($isManager) {
            if ($userToRemoveRole === Role::ROLE_MANAGER) {
                return $this->error('Manager cannot delete another manager.', 403);
            }
        }
        // Leader can delete member but not manager
        elseif ($isLeader) {
            if ($userToRemoveRole === Role::ROLE_MANAGER || $userToRemoveRole === Role::ROLE_LEADER) {
                return $this->error('Leader cannot delete manager or another leader.', 403);
            }
        }

        // Check if the user to be removed has incomplete tasks assigned only to them
        $incompleteTasksAssignedOnlyToUser = DB::table('task_members')
            ->join('tasks', 'task_members.task_id', '=', 'tasks.id')
            ->where('tasks.team_id', $teamId)
            ->where('task_members.user_id', $request->user_id)
            ->whereIn('tasks.status', [Task::STATUS_PENDING, Task::STATUS_IN_PROGRESS, Task::STATUS_IN_REVIEW])
            ->whereNotExists(function ($query) use ($request) {
                $query->select(DB::raw(1))
                    ->from('task_members as tm')
                    ->whereRaw('tm.task_id = tasks.id')
                    ->where('tm.user_id', '<>', $request->user_id); // Check if other users are assigned to the task
            })
            ->exists();

        if ($incompleteTasksAssignedOnlyToUser) {
            return $this->error('The user cannot be removed because they have incomplete tasks assigned only to them.', 403);
        }

        // Remove the user from the team
        DB::table('project_role_user')
            ->where('user_id', $request->user_id)
            ->where('project_id', $team->project_id)
            ->where(function ($query) use ($teamId) {
                $query->where('team_id', $teamId)
                    ->orWhereNull('team_id'); // Include manager (team_id = null)
            })
            ->delete();

        // Remove the user from all tasks related to this team
        DB::table('task_members')
            ->where('user_id', $request->user_id)
            ->where('team_id', $teamId)
            ->delete();

        return $this->success([], 'User removed from the team successfully.');
    }
    public function changeUserRole(Request $request, $teamId)
    {
        // Get the authenticated user
        $user = Auth::user();

        // Validate the request
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'role_id' => 'required|exists:roles,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Find the team
        $team = Team::find($teamId);
        if (!$team) {
            return $this->error('Team not found.', 404);
        }

        // Check if the user is the manager of the project
        $isManager = $user->roles()
            ->where('project_id', $team->project_id)
            ->where('role_id', Role::ROLE_MANAGER)
            ->whereNull('team_id')
            ->exists();

        if (!$isManager) {
            return $this->error('Only the manager can change user roles.', 403);
        }

        // Find the user whose role is being changed
        $userToChange = User::find($request->user_id);
        if (!$userToChange) {
            return $this->error('User not found.', 404);
        }

        // Check the current role of the user
        $currentRole = DB::table('project_role_user')
            ->where('user_id', $request->user_id)
            ->where('project_id', $team->project_id)
            ->where(function ($query) use ($teamId) {
                $query->where('team_id', $teamId)
                    ->orWhereNull('team_id'); // Include manager (team_id = null)
            })
            ->value('role_id');

        if (!$currentRole) {
            return $this->error('User is not associated with this project or team.', 404);
        }

        // If the user being changed is the manager and is being demoted to member or leader
        if ($currentRole == Role::ROLE_MANAGER && ($request->role_id == Role::ROLE_MEMBER || $request->role_id == Role::ROLE_LEADER)) {
            // Check if there is another manager available
            $otherManagers = DB::table('project_role_user')
                ->where('project_id', $team->project_id)
                ->where('role_id', Role::ROLE_MANAGER)
                ->where('user_id', '!=', $request->user_id) // Exclude the current manager
                ->exists();

            if (!$otherManagers) {
                return $this->error('You must assign another manager before demoting the current manager.', 403);
            }
        }

        // If the new role is manager
        if ($request->role_id == Role::ROLE_MANAGER) {
            // Find the current manager
            $currentManager = DB::table('project_role_user')
                ->where('project_id', $team->project_id)
                ->where('role_id', Role::ROLE_MANAGER)
                ->whereNull('team_id')
                ->first();

            // If there is a current manager, demote them to a member of the team
            if ($currentManager) {
                // Remove the current manager's manager role
                DB::table('project_role_user')
                    ->where('user_id', $currentManager->user_id)
                    ->where('project_id', $team->project_id)
                    ->whereNull('team_id')
                    ->delete();

                // Add the current manager as a member of the team
                DB::table('project_role_user')->insert([
                    'user_id' => $currentManager->user_id,
                    'role_id' => Role::ROLE_MEMBER,
                    'project_id' => $team->project_id,
                    'team_id' => $teamId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Remove any existing roles for the new manager (member or leader)
            DB::table('project_role_user')
                ->where('user_id', $request->user_id)
                ->where('project_id', $team->project_id)
                ->delete();

            // Assign the new manager
            DB::table('project_role_user')->insert([
                'user_id' => $request->user_id,
                'role_id' => Role::ROLE_MANAGER,
                'project_id' => $team->project_id,
                'team_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $this->success([], 'User role updated to manager successfully.');
        }

        // If the new role is leader
        if ($request->role_id == Role::ROLE_LEADER) {

            $currentLeader = DB::table('project_role_user')
            ->where('project_id', $team->project_id)
            ->where('role_id', Role::ROLE_LEADER)
            ->where('team_id', $teamId)
            ->first();

            // If there is a current leader, demote them to a member of the team
            if ($currentLeader) {
                // Remove the current leader's leader role
                DB::table('project_role_user')
                    ->where('user_id', $currentLeader->user_id)
                    ->where('project_id', $team->project_id)
                    ->where('team_id', $teamId)
                    ->delete();

                // Add the current leader as a member of the team
                DB::table('project_role_user')->insert([
                    'user_id' => $currentLeader->user_id,
                    'role_id' => Role::ROLE_MEMBER,
                    'project_id' => $team->project_id,
                    'team_id' => $teamId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Remove any existing roles for the new leader
            DB::table('project_role_user')
                ->where('user_id', $request->user_id)
                ->where('project_id', $team->project_id)
                ->delete();


            // Assign the new leader
            DB::table('project_role_user')->insert([
                'user_id' => $request->user_id,
                'role_id' => Role::ROLE_LEADER,
                'project_id' => $team->project_id,
                'team_id' => $teamId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $this->success([], 'User role updated to leader successfully.');
        }

        // If the new role is member
        if ($request->role_id == Role::ROLE_MEMBER) {
            // Check if the user being changed is the current manager
            if ($currentRole == Role::ROLE_MANAGER) {
                // Check if there is another manager available
                $otherManagers = DB::table('project_role_user')
                    ->where('project_id', $team->project_id)
                    ->where('role_id', Role::ROLE_MANAGER)
                    ->where('user_id', '!=', $request->user_id) // Exclude the current manager
                    ->exists();

                if (!$otherManagers) {
                    return $this->error('You must assign another manager before demoting the current manager.', 403);
                }

                // Demote the current manager to a member
                DB::table('project_role_user')
                    ->where('user_id', $request->user_id)
                    ->where('project_id', $team->project_id)
                    ->whereNull('team_id')
                    ->delete();

                // Add the user as a member of the team
                DB::table('project_role_user')->insert([
                    'user_id' => $request->user_id,
                    'role_id' => Role::ROLE_MEMBER,
                    'project_id' => $team->project_id,
                    'team_id' => $teamId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return $this->success([], 'Manager demoted to member successfully.');
            }

            // Update the user's role to member
            DB::table('project_role_user')
                ->where('user_id', $request->user_id)
                ->where('project_id', $team->project_id)
                ->where('team_id', $teamId)
                ->update(['role_id' => Role::ROLE_MEMBER]);

            return $this->success([], 'User role updated to member successfully.');
        }

        return $this->error('Invalid role.', 422);
    }

    public function leaveTeam(Request $request, $teamId)
    {
        // Get the authenticated user
        $user = Auth::user();
        
        // Find the team
        $team = Team::find($teamId);
        if (!$team) {
            return $this->error('Team not found.', 404);
        }
        
        // Check if the user is a member or leader of the team
        $userRole = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('project_id', $team->project_id)
            ->where(function ($query) use ($teamId) {
                $query->where('team_id', $teamId)
                    ->orWhereNull('team_id'); // Include manager (team_id = null)
            })
            ->value('role_id');
            
        if (!$userRole) {
            return $this->error('You are not associated with this project or team.', 403);
        }
        
        // Check if the user is the manager
        if ($userRole === Role::ROLE_MANAGER) {
            return $this->error('Manager cannot leave the team without assigning a new manager first.', 403);
        }
        
        // Check if the user has incomplete tasks assigned only to them
        $incompleteTasksAssignedOnlyToUser = DB::table('task_members')
            ->join('tasks', 'task_members.task_id', '=', 'tasks.id')
            ->where('tasks.team_id', $teamId)
            ->where('task_members.user_id', $user->id)
            ->whereIn('tasks.status', [Task::STATUS_PENDING, Task::STATUS_IN_PROGRESS, Task::STATUS_IN_REVIEW])
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('task_members as tm')
                    ->whereRaw('tm.task_id = tasks.id')
                    ->where('tm.user_id', '<>', Auth::id()); // Check if other users are assigned to the task
            })
            ->exists();
            
        if ($incompleteTasksAssignedOnlyToUser) {
            return $this->error('You cannot leave the team because you have incomplete tasks assigned only to you.', 403);
        }
        
        // Remove the user from the team
        DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('project_id', $team->project_id)
            ->where('team_id', $teamId)
            ->delete();
            
        // Remove the user from all tasks related to this team
        DB::table('task_members')
            ->where('user_id', $user->id)
            ->where('team_id', $teamId)
            ->delete();
        
        // Check if the user is still associated with the project in another team
        $stillInProject = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('project_id', $team->project_id)
            ->exists();
        
        return $this->success([
            'still_in_project' => $stillInProject
        ], 'You have successfully left the team.');
    }

    public function getMyTeams(Request $request)
    {
        // Get the authenticated user
        $user = Auth::user();

        // Step 1: Get all projects where the user is a manager
        $managedProjects = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('role_id', Role::ROLE_MANAGER)
            ->pluck('project_id');

        // Step 2: Get all teams where the user is a member or leader, including project details
        $teams = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->whereIn('role_id', [Role::ROLE_MEMBER, Role::ROLE_LEADER])
            ->join('teams', 'project_role_user.team_id', '=', 'teams.id')
            ->join('projects', 'teams.project_id', '=', 'projects.id') // Join projects table
            ->select(
                'teams.*',
                'projects.id as project_id', // Include project details
                'projects.name as project_name',
                'projects.description as project_description',
                'project_role_user.role_id',
                'project_role_user.team_id',
                'projects.status as project_status', // Include project status if needed
                'projects.created_at as project_created_at', // Include project created_at if needed
                'projects.updated_at as project_updated_at'  // Include project updated_at if needed
            )
            ->get();

        // Step 3: If the user is a project manager, include all teams in the projects they manage
        if ($managedProjects->isNotEmpty()) {
            // Get all teams from the projects the user manages
            $managedTeams = Team::whereIn('project_id', $managedProjects)
                ->join('projects', 'teams.project_id', '=', 'projects.id') // Join projects table
                ->select(
                    'teams.*',
                    'projects.id as project_id', // Include project details
                    'projects.name as project_name',
                    'projects.description as project_description',
                    'projects.status as project_status',
                    'projects.created_at as project_created_at',
                    'projects.updated_at as project_updated_at'
                )
                ->get();

            // Add the manager role to these teams, and set team_id to null if it's a manager
            $managedTeams = $managedTeams->map(function ($team) {
                $team->role = 'manager'; // Set role as manager
                return $team;
            });

            // Merge the managed teams with the teams where the user is a member or leader
            $teams = $teams->merge($managedTeams);
        }

        // Step 4: Format the response to include the user's role in each team and the is_manager flag inside the project
        $formattedTeams = $teams->map(function ($team) use ($managedProjects, $user) {
            // Determine if the user is a project manager
            $isProjectManager = $managedProjects->contains($team->project_id);

            // If the user is a project manager, set the role to 'manager' for all teams in the managed projects
            if ($isProjectManager) {
                $role = 'manager';
            } else {
                // Otherwise, determine the user's role in the team
                $role = $team->role_id == Role::ROLE_LEADER ? 'leader' : ($team->role_id == Role::ROLE_MANAGER ? 'manager' : 'member');
            }

            // Safely handle the project object and always set is_manager flag
            $project = [
                'id' => $team->project_id, // Ensure project_id is never null
                'name' => $team->project_name,
                'description' => $team->project_description,
                'status' => $team->project_status,
                'created_at' => $team->project_created_at,
                'updated_at' => $team->project_updated_at,
                'is_manager' => $isProjectManager,  // Add is_manager flag to project object
            ];

            // Add the role and project details to the team object
            $team->role = $role;
            $team->hasAccess = true;  // Assuming true for all teams
            $team->project = $project;

            // Remove unnecessary fields
            unset($team->project_id, $team->project_name, $team->project_description, $team->project_status, $team->project_created_at, $team->project_updated_at);

            return $team;
        });

        // Step 5: Remove duplicates (in case a user is both a manager and a member/leader of a team)
        $formattedTeams = $formattedTeams->unique('id');

        return $this->success([
            'teams' => $formattedTeams,
        ], 'User teams retrieved successfully.');
    }

    // Get Team Users
    public function getTeamUsers($teamId)
    {
        // Get the authenticated user
        $user = Auth::user();

        // Find the team
        $team = Team::find($teamId);
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
            ->where('team_id', $teamId)
            ->where('role_id', Role::ROLE_LEADER)
            ->exists();

        if (!$isManager && !$isLeader) {
            return $this->error('You are not authorized to view this team\'s members.', 403);
        }

        // Get users in the team (members and leader)
        $teamUsers = DB::table('project_role_user')
            ->where('team_id', $teamId)
            ->whereIn('role_id', [Role::ROLE_MEMBER, Role::ROLE_LEADER])
            ->join('users', 'project_role_user.user_id', '=', 'users.id')
            ->select('users.id', 'users.name', 'users.email', 'project_role_user.role_id')
            ->get();

        // Get the project manager (if any)
        $projectManager = DB::table('project_role_user')
            ->where('project_id', $team->project_id)
            ->where('role_id', Role::ROLE_MANAGER)
            ->whereNull('team_id') // Manager has team_id = null
            ->join('users', 'project_role_user.user_id', '=', 'users.id')
            ->select('users.id', 'users.name', 'users.email', 'project_role_user.role_id')
            ->first();

        // Convert the project manager to a collection if it exists
        $projectManagerCollection = $projectManager ? collect([$projectManager]) : collect();

        // Combine team users and project manager into a single collection
        $allUsers = $teamUsers->merge($projectManagerCollection);

        // Format the response
        $formattedUsers = $allUsers->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role_id == Role::ROLE_MANAGER ? 'manager' : ($user->role_id == Role::ROLE_LEADER ? 'leader' : 'member'),
            ];
        });

        return $this->success(['users' => $formattedUsers], 'Team users retrieved successfully.');
    }
}
