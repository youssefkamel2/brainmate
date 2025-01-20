<?php

namespace App\Http\Controllers;

use App\Models\Trash;
use App\Models\PersonalNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Traits\ResponseTrait;

class TrashController extends Controller
{
    use ResponseTrait;

    /**
     * Get all trashed notes for the authenticated user.
     */
    public function index()
    {
        $user = Auth::user();
    
        $trashedNotes = Trash::with(['note' => function ($query) {
            $query->withTrashed()->with('folder');
        }])
        ->whereHas('note', function ($query) {
            $query->withTrashed();
        })
        ->where('user_id', $user->id)
        ->get();
    
        return $this->success($trashedNotes, 'Trashed notes retrieved successfully');
    }

    /**
     * Restore a note from trash for the authenticated user.
     */
    public function restore($id)
    {
        $user = Auth::user();
    
        $trash = Trash::find($id);
    
        if (!$trash) {
            return $this->error('Trashed note not found', 404);
        }
    
        if ($trash->user_id !== $user->id) {
            return $this->error('Unauthorized', 403);
        }
    
        // Restore the soft-deleted note
        $trash->note()->restore();
        // Delete the trash record
        $trash->delete();
    
        return $this->success(null, 'Note restored from trash successfully');
    }

    /**
     * Permanently delete a note from trash for the authenticated user.
     */
    public function delete($id)
    {
        $user = Auth::user();

        $trash = Trash::find($id);

        if (!$trash) {
            return $this->error('Trashed note not found', 404);
        }

        if ($trash->user_id !== $user->id) {
            return $this->error('Unauthorized', 403);
        }

        $trash->note()->forceDelete(); // Delete the associated note
        $trash->delete(); // Delete the trash record

        return $this->success(null, 'Note permanently deleted from trash successfully');
    }

    public function deleteAll()
    {
        $user = Auth::user();
    
        // Debug: Check if the user is authenticated
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }
    
        // Debug: Log the user ID
        \Log::info('User ID: ' . $user->id);
    
        // Fetch all trashed notes for the user
        $trashedNotes = Trash::where('user_id', $user->id)->get();
    
        // Debug: Log the number of trashed notes
        \Log::info('Trashed notes count: ' . $trashedNotes->count());
    
        // If no trashed notes exist, return an error
        if ($trashedNotes->isEmpty()) {
            return $this->error('No trashed notes found', 404);
        }
    
        // Permanently delete each note and its trash record
        foreach ($trashedNotes as $trash) {
            if ($trash->note) {
                $trash->note()->forceDelete(); // Permanently delete the associated note
            }
            $trash->delete(); // Delete the trash record
        }
    
        return $this->success(null, 'All trashed notes permanently deleted successfully');
    }
}