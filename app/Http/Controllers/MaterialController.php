<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\Material;
use App\Models\Attachment;
use Illuminate\Http\Request;
use App\Traits\ResponseTrait;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MaterialController extends Controller
{

    use ResponseTrait;

    // Get all materials and task attachments for a team
    public function index($teamId)
    {
        // Ensure the team exists
        $team = Team::findOrFail($teamId);

        // Get materials for the team
        $materials = Material::where('team_id', $teamId)->get();

        // Get task attachments for the team
        $taskAttachments = Attachment::whereHas('task', function ($query) use ($teamId) {
            $query->where('team_id', $teamId);
        })->get();

        // Combine materials and task attachments into a single array
        $attachments = $materials->map(function ($material) {
            return [
                'id' => $material->id,
                'name' => $material->name,
                'media' => $material->media,
                'created_at' => $material->created_at,
                'updated_at' => $material->updated_at,
                'type' => 'material', // Add a type field to distinguish between materials and task attachments
            ];
        })->concat($taskAttachments->map(function ($attachment) {
            return [
                'id' => $attachment->id,
                'name' => $attachment->name,
                'media' => $attachment->media,
                'created_at' => $attachment->created_at,
                'updated_at' => $attachment->updated_at,
                'type' => 'task_attachment', // Add a type field to distinguish between materials and task attachments
            ];
        }));

        // Sort attachments by created_at in descending order
        $attachments = $attachments->sortByDesc('created_at')->values();

        return $this->success(['attachments' => $attachments], 'Attachments retrieved successfully.');
    }

    // Upload a material for a team
    public function store(Request $request, $teamId)
    {
        $user = Auth::user();

        // Validate the request
        $validator = Validator::make($request->all(), [
            'attachments' => 'required|array',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,pdf,doc,docx|max:8048', // Max 8MB per file
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Ensure the team exists
        $team = Team::find($teamId);
        if (!$team) {
            return $this->error('Task not found.', 404);
        }

        $isProjectManager = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('project_id', $team->project_id)
            ->where('role_id', Role::ROLE_MANAGER)
            ->whereNull('team_id') // Manager has team_id = null
            ->exists();

        // Check if the user is part of the team (member or leader)
        $isPartOfTeam = DB::table('project_role_user')
            ->where('user_id', $user->id)
            ->where('team_id', $teamId)
            ->whereIn('role_id', [Role::ROLE_MEMBER, Role::ROLE_LEADER])
            ->exists();

        if (!$isPartOfTeam && !$isProjectManager) {
            return $this->error('You are not part of this team.', 403);
        }

        // Handle attachments
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $attachment) {
                $fileName = time() . '_' . uniqid() . '.' . $attachment->getClientOriginalExtension();
                $attachment->move(public_path('uploads/materials'), $fileName);
                $mediaUrl = 'uploads/materials/' . $fileName;

                // Save material details to the database
                $material = Material::create([
                    'name' => $fileName,
                    'media' => $mediaUrl,
                    'team_id' => $teamId,
                    'uploaded_by' => $user->id,
                ]);

                // Log the material creation
                activity()
                    ->causedBy($user)
                    ->performedOn($material)
                    ->withProperties(['attributes' => $material->getAttributes()])
                    ->event('created')
                    ->log('Material added to team');
            }
        }

        return $this->success([], 'Materials uploaded successfully.');
    }

    // Delete a material or attachment
    public function destroy($attachmentId)
    {

        $user = Auth::user();

        // Find the material or attachment
        $material = Material::find($attachmentId);
        $attachment = Attachment::find($attachmentId);

        // Ensure the user is authorized to delete
        if ($material) {
            // Check if the user is the uploader, leader, or manager
            $isUploader = $material->uploaded_by === $user->id;
            $isManager = DB::table('project_role_user')
                ->where('user_id', $user->id)
                ->where('project_id', $material->team->project_id)
                ->where('role_id', Role::ROLE_MANAGER)
                ->whereNull('team_id') // Manager has team_id = null
                ->exists();

            $isLeader = DB::table('project_role_user')
                ->where('user_id', $user->id)
                ->where('team_id', $material->team_id)
                ->where('role_id', Role::ROLE_LEADER)
                ->exists();

            if (!$isUploader && !$isLeader && !$isManager) {
                return $this->error('You are not authorized to delete this material.', 403);
            }

            // Log the material deletion
            activity()
                ->causedBy($user)
                ->performedOn($material)
                ->withProperties(['attributes' => $material->getAttributes()])
                ->event('deleted')
                ->log('Material removed from team');

            // Delete the file from the server
            if (file_exists(public_path($material->media))) {
                unlink(public_path($material->media));
            }

            // Delete the material record
            $material->delete();
        } elseif ($attachment) {
            // Check if the user is the uploader, leader, or manager
            $isUploader = $attachment->task->members()->where('user_id', $user->id)->exists();
            $isManager = DB::table('project_role_user')
                ->where('user_id', $user->id)
                ->where('project_id', $attachment->task->team->project_id)
                ->where('role_id', Role::ROLE_MANAGER)
                ->whereNull('team_id') // Manager has team_id = null
                ->exists();

            $isLeader = DB::table('project_role_user')
                ->where('user_id', $user->id)
                ->where('team_id', $attachment->task->team_id)
                ->where('role_id', Role::ROLE_LEADER)
                ->exists();

            if (!$isUploader && !$isLeader && !$isManager) {
                return $this->error('You are not authorized to delete this attachment.', 403);
            }

            // Log the attachment deletion
            activity()
                ->causedBy($user)
                ->performedOn($attachment)
                ->withProperties(['attributes' => $attachment->getAttributes()])
                ->event('deleted')
                ->log('Attachment removed from task');

            // Delete the file from the server
            if (file_exists(public_path($attachment->media))) {
                unlink(public_path($attachment->media));
            }

            // Delete the attachment record
            $attachment->delete();
        } else {
            return $this->error('Material or attachment not found.', 404);
        }

        return $this->success([], 'Material or attachment deleted successfully.');
    }
}
