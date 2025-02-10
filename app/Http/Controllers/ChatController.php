<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\Role;
use App\Models\Team;
use App\Traits\ResponseTrait;
use Illuminate\Http\Request;
use App\Models\ProjectRoleUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Validator;


class ChatController extends Controller
{
    use ResponseTrait;

    // Get teams that the user can chat in
    public function getChatTeams(){
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
                'projects.id as project_id',
                'projects.name as project_name',
                'projects.description as project_description',
                'project_role_user.role_id',
                'project_role_user.team_id',
                'projects.status as project_status',
                'projects.created_at as project_created_at',
                'projects.updated_at as project_updated_at'
            )
            ->get();

        // Step 3: If the user is a project manager, include all teams in the projects they manage
        if ($managedProjects->isNotEmpty()) {
            // Get all teams from the projects the user manages
            $managedTeams = Team::whereIn('project_id', $managedProjects)
                ->join('projects', 'teams.project_id', '=', 'projects.id') // Join projects table
                ->select(
                    'teams.*',
                    'projects.id as project_id',
                    'projects.name as project_name',
                    'projects.description as project_description',
                    'projects.status as project_status',
                    'projects.created_at as project_created_at',
                    'projects.updated_at as project_updated_at'
                )
                ->get();

            // Add the manager role to these teams, and set team_id to null if it's a manager
            $managedTeams = $managedTeams->map(function ($team) {
                $team->role = 'manager';
                return $team;
            });

            // Merge the managed teams with the teams where the user is a member or leader
            $teams = $teams->merge($managedTeams);
        }

        // Step 4: Format the response to include the user's role in each team and the is_manager flag inside the project
        $formattedTeams = $teams->map(function ($team) use ($managedProjects, $user) {
            $isProjectManager = $managedProjects->contains($team->project_id);

            if ($isProjectManager) {
                $role = 'manager';
            } else {
                $role = $team->role_id == Role::ROLE_LEADER ? 'leader' : ($team->role_id == Role::ROLE_MANAGER ? 'manager' : 'member');
            }

            $project = [
                'id' => $team->project_id,
                'name' => $team->project_name,
                'description' => $team->project_description,
                'status' => $team->project_status,
                'created_at' => $team->project_created_at,
                'updated_at' => $team->project_updated_at,
                'is_manager' => $isProjectManager,
            ];

            $team->role = $role;
            $team->has_access = true;
            $team->project = $project;

            unset($team->project_id, $team->project_name, $team->project_description, $team->project_status, $team->project_created_at, $team->project_updated_at);

            return $team;
        });

        $formattedTeams = $formattedTeams->unique('id');

        // Step 6: Sort teams by the last message sent
        $formattedTeams = $formattedTeams->sortByDesc(function ($team) {
            $latestMessage = Chat::where('team_id', $team->id)
                ->orderBy('created_at', 'desc')
                ->first();

            return $latestMessage ? $latestMessage->created_at : $team->created_at;
        });

        return $this->success([
            'teams' => $formattedTeams->values(),
        ], 'Teams retrieved successfully.');
    }

    // Get messages for a specific team
    public function getMessages($teamId)
    {
        $messages = Chat::where('team_id', $teamId)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return $this->success($messages, 'Messages retrieved successfully.');
    }

    // Send a message
    public function sendMessage(Request $request)
    {
    
        $validator = Validator::make($request->all(), [
            'message' => 'required|string',
            'team_id' => 'nullable|exists:teams,id',
            'receiver_id' => 'nullable|exists:users,id',
            'type' => 'required|in:text,file,image',
            'media' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }
        
        $chat = Chat::create([
            'sender_id' => Auth::id(),
            'receiver_id' => $request->receiver_id,
            'team_id' => $request->team_id,
            'message' => $request->message,
            'type' => $request->type,
            'media' => $request->media
        ]);
    
        // Broadcast the message to the team's channel
        Broadcast::channel('team.' . $request->team_id, function ($user) {
            return true; // Allow all users to listen to the channel
        });
    
        // Trigger the Pusher event
        broadcast(new \App\Events\NewChatMessage($chat))->toOthers();
    
        return $this->success($chat, 'Message sent successfully.', 201);
    }
}
