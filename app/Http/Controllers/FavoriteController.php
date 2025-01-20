<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use App\Models\PersonalNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Traits\ResponseTrait;

class FavoriteController extends Controller
{
    use ResponseTrait;

    /**
     * Get all favorite notes for the authenticated user.
     */
    public function index()
    {
        $user = Auth::user();

        $favorites = Favorite::with(['note.folder'])
            ->whereHas('note', function ($query) {
                $query->whereNull('deleted_at'); // Exclude soft-deleted notes
            })
            ->where('user_id', $user->id)
            ->get();

        return $this->success($favorites, 'Favorite notes retrieved successfully');
    }

    /**
     * Toggle a note's favorite status for the authenticated user.
     */
    public function toggleFavorite(Request $request)
    {
        $user = Auth::user();

        // Validate the request data
        $validator = Validator::make($request->all(), [
            'note_id' => 'required|exists:personal_notes,id',
            'flag' => 'required|in:0,1', // flag must be 0 or 1
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }

        $noteId = $request->note_id;
        $flag = $request->flag;

        // Check if the note is already in favorites
        $existingFavorite = Favorite::where('user_id', $user->id)
            ->where('note_id', $noteId)
            ->first();

        if ($flag == 1) {
            // Add to favorites
            if ($existingFavorite) {
                return $this->error('Note is already in favorites', 400);
            }

            $favorite = Favorite::create([
                'user_id' => $user->id,
                'note_id' => $noteId,
            ]);

            $favorite->load(['note.folder']);

            return $this->success($favorite, 'Note added to favorites successfully', 201);
        } else {
            // Remove from favorites
            if (!$existingFavorite) {
                return $this->error('Note is not in favorites', 400);
            }

            $existingFavorite->delete();

            return $this->success(null, 'Note removed from favorites successfully');
        }
    }
}
