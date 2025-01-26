<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\Project;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Traits\ResponseTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Notifications\TeamInvitationNotification;

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
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
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
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
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
        $team->update($request->only('name'));

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
            return $this->error($validator->errors(), 422);
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
        if (!$invitedUser) {
            return $this->error('User not found.', 404);
        }
        if ($invitedUser->id == $user->id) {
            return $this->error('Cannot Invite This User.', 422);
        }

        if ($request->role_id == Role::ROLE_MANAGER) {
            return $this->error('You Can Invite Users As Leaders And Members Only.', 422);
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
            'invited_user_id' => $invitedUser->id,
            'role_id' => $request->role_id,
            'token' => $token,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Send invitation email
        $project = Project::find($team->project_id);
        $role = Role::find($request->role_id)->name;
        $invitedUser->notify(new TeamInvitationNotification($team, $project, $role, $token));

        return $this->success([], 'Invitation sent successfully.');
    }
    // accept invite
    public function acceptInvitation(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }

        // Find the invitation by token
        $invitation = DB::table('invitations')
            ->where('token', $request->token)
            ->first();

        if (!$invitation) {
            return $this->error('Invalid or expired invitation.', 404);
        }

        // Check if the invitation has already been accepted
        if ($invitation->accepted_at) {
            return $this->error('Invitation has already been accepted.', 400);
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

        return $this->success([], 'Invitation accepted successfully.');
    }

    // join team by code
    public function joinTeam(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'team_code' => 'required|string|exists:teams,team_code',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
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
        $team = Team::with(['project'])->find($teamId);
        if (!$team) {
            return $this->error('Team not found.', 404);
        }

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

        // Add the members to the team object
        $team->all_members = $allMembers;

        return $this->success(['team' => $team], 'Team details retrieved successfully.');
    }

    // // List teams in a project
    public function listTeamsInProject(Request $request, $projectId)
    {
        // Get the authenticated user from the JWT token
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
            return $role->pivot->team_id === null && $role->id === 1; // Assuming role_id 1 is for manager
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
                    ->where('role_id', 2) // Assuming role_id 2 is for leader
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

        return $this->success(['teams' => $teamsWithAccess], 'Teams Retrieved Successfully.');
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
            return $this->error($validator->errors(), 422);
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

        // Remove the user from the team
        DB::table('project_role_user')
            ->where('user_id', $request->user_id)
            ->where('project_id', $team->project_id)
            ->where(function ($query) use ($teamId) {
                $query->where('team_id', $teamId)
                    ->orWhereNull('team_id'); // Include manager (team_id = null)
            })
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
            return $this->error($validator->errors(), 422);
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
            // Remove the current leader
            DB::table('project_role_user')
                ->where('team_id', $teamId)
                ->where('role_id', Role::ROLE_LEADER)
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
                return $this->error('Manager cannot be changed to member without assigning a new manager first.', 403);
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

        // Remove the user from the team
        DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('project_id', $team->project_id)
            ->where('team_id', $teamId)
            ->delete();

        return $this->success([], 'You have successfully left the team.');
    }
}
