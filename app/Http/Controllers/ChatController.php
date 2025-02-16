<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\Role;
use App\Models\Team;
use Illuminate\Http\Request;
use App\Traits\ResponseTrait;
use App\Models\ProjectRoleUser;
use App\Traits\MemberColorTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Validator;

class ChatController extends Controller
{
    use ResponseTrait, MemberColorTrait;

    // Get teams that the user can chat in
    public function getChatTeams()
    {
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

            // Fetch the latest message for the team
            $latestMessage = Chat::where('team_id', $team->id)
                ->orderBy('created_at', 'desc')
                ->first();

            $team->role = $role;
            $team->has_access = true;
            $team->project = $project;
            $team->last_message = $latestMessage ? [
                'message' => $latestMessage->message,
                'timestamp' => $latestMessage->created_at,
                'sender' => $latestMessage->sender ? [
                    'id' => $latestMessage->sender->id,
                    'name' => $latestMessage->sender->name,
                    'color' => $this->getMemberColor($latestMessage->sender->id), // Add color for the sender
                ] : null,
            ] : null;

            unset($team->project_id, $team->project_name, $team->project_description, $team->project_status, $team->project_created_at, $team->project_updated_at);

            return $team;
        });

        $formattedTeams = $formattedTeams->unique('id');

        // Step 6: Sort teams by the last message sent
        $formattedTeams = $formattedTeams->sortByDesc(function ($team) {
            return $team->last_message ? $team->last_message['timestamp'] : $team->created_at;
        });

        return $this->success([
            'teams' => $formattedTeams->values(),
        ], 'Teams retrieved successfully.');
    }

    // Get messages for a specific team
    public function getMessages($teamId)
    {
        $user = Auth::user();

        // Check if the user has access to the team
        if (!$this->userHasAccessToTeam($user, $teamId)) {
            return $this->error('You do not have access to this team.', 403);
        }

        $messages = Chat::with('sender')->where('team_id', $teamId)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Add color for each sender in the messages
        $messages->getCollection()->transform(function ($message) {
            $message->sender->color = $this->getMemberColor($message->sender->id);
            return $message;
        });

        return $this->success($messages, 'Messages retrieved successfully.');
    }

    // Send a message
    public function sendMessage(Request $request)
    {
        $user = Auth::user();

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

        // Check if the user has access to the team
        if (!$this->userHasAccessToTeam($user, $request->team_id)) {
            return $this->error('You do not have access to this team.', 403);
        }

        // Create the chat message
        $chat = Chat::create([
            'sender_id' => $user->id,
            'receiver_id' => $request->receiver_id,
            'team_id' => $request->team_id,
            'message' => $request->message,
            'type' => $request->type,
            'media' => $request->media
        ]);

        $color = $this->getMemberColor($chat->sender->id);

        // Eager load the sender relationship
        $chat->load('sender');

        // Broadcast the message to the team's channel
        Broadcast::channel('team.' . $request->team_id, function ($user) {
            return true;
        });

        // Trigger the Pusher event
        broadcast(new \App\Events\NewChatMessage($chat, $color))->toOthers();

        // Trigger the Pusher event for the last message update
        broadcast(new \App\Events\LastMessageUpdated($chat, $color))->toOthers();

        // Format the response to include the sender object and color
        $responseData = [
            'id' => $chat->id,
            'sender_id' => $chat->sender_id,
            'receiver_id' => $chat->receiver_id,
            'team_id' => $chat->team_id,
            'message' => $chat->message,
            'type' => $chat->type,
            'media' => $chat->media,
            'created_at' => $chat->created_at,
            'updated_at' => $chat->updated_at,
            'sender' => [
                'id' => $chat->sender->id,
                'name' => $chat->sender->name,
                'color' => $color,
            ],
        ];

        return $this->success($responseData, 'Message sent successfully.', 201);
    }

    /**
     * Check if the user has access to the team.
     */
    private function userHasAccessToTeam($user, $teamId)
    {
        $isMemberOrLeader = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('team_id', $teamId)
            ->whereIn('role_id', [Role::ROLE_MEMBER, Role::ROLE_LEADER])
            ->exists();

        $isProjectManager = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('role_id', Role::ROLE_MANAGER)
            ->whereExists(function ($query) use ($teamId) {
                $query->select(DB::raw(1))
                    ->from('teams')
                    ->whereColumn('teams.project_id', 'project_role_user.project_id')
                    ->where('teams.id', $teamId);
            })
            ->exists();

        return $isMemberOrLeader || $isProjectManager;
    }

    /**
     * Get a fixed color for a user based on their ID.
     */
    private function getMemberColor($userId)
    {
        // Predefined list of colors, including WhatsApp-like colors
        $colors = [
            '#FF6633', '#FFB399', '#FF33FF', '#FFFF99', '#00B3E6', 
            '#E6B333', '#3366E6', '#999966', '#99FF99', '#B34D4D',
            '#FF6666', '#66FF66', '#6666FF', '#FF66FF', '#66FFFF',
            '#FFCC66', '#CCFF66', '#66CCFF', '#CC66FF', '#FF6666',
            '#FFCCCC', '#CCFFCC', '#CCCCFF', '#FFCCFF', '#CCFFFF',
            '#FF9966', '#99FF66', '#6699FF', '#9966FF', '#FF6699',
            '#FFCC99', '#99FFCC', '#99CCFF', '#CC99FF', '#FF99CC',
            '#FF6666', '#66FF66', '#6666FF', '#FF66FF', '#66FFFF',
            '#FFCC66', '#CCFF66', '#66CCFF', '#CC66FF', '#FF6666',
            '#FFCCCC', '#CCFFCC', '#CCCCFF', '#FFCCFF', '#CCFFFF',
            '#FF9966', '#99FF66', '#6699FF', '#9966FF', '#FF6699',
            '#FFCC99', '#99FFCC', '#99CCFF', '#CC99FF', '#FF99CC',
            '#DCF8C6', '#ECE5DD', '#D4E7F7', '#F5F5F5', '#F8F8F8',
            '#E5F6FB', '#F0F8FF', '#F0FFF0', '#FFF0F5', '#F5F5DC',
            '#F0E68C', '#E6E6FA', '#FFFACD', '#ADD8E6', '#F08080',
            '#E0FFFF', '#90EE90', '#D3D3D3', '#FFB6C1', '#FFA07A',
            '#20B2AA', '#87CEFA', '#778899', '#B0C4DE', '#FFFFE0',
            '#00FF00', '#32CD32', '#FAFAD2', '#FF00FF', '#800080',
            '#FF0000', '#FF4500', '#DA70D6', '#EEE8AA', '#98FB98',
            '#AFEEEE', '#DB7093', '#FFEFD5', '#FFDAB9', '#CD853F',
            '#FFC0CB', '#DDA0DD', '#B0E0E6', '#FF6347', '#FFA500',
            '#FFD700', '#6A5ACD', '#7B68EE', '#00FA9A', '#48D1CC',
            '#C71585', '#191970', '#F5FFFA', '#FFE4E1', '#FFE4B5',
            '#FFDEAD', '#000080', '#FDF5E6', '#808000', '#6B8E23',
            '#FFA500', '#FF4500', '#DA70D6', '#EEE8AA', '#98FB98',
            '#AFEEEE', '#DB7093', '#FFEFD5', '#FFDAB9', '#CD853F',
            '#FFC0CB', '#DDA0DD', '#B0E0E6', '#FF6347', '#FFA500',
            '#FFD700', '#6A5ACD', '#7B68EE', '#00FA9A', '#48D1CC',
            '#C71585', '#191970', '#F5FFFA', '#FFE4E1', '#FFE4B5',
            '#FFDEAD', '#000080', '#FDF5E6', '#808000', '#6B8E23',
        ];

        // Use a hash function to map user_id to a color
        $index = $userId % count($colors);
        return $colors[$index];
    }
}
