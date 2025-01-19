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
     * Add a note to favorites for the authenticated user.
     */
    public function create(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'note_id' => 'required|exists:personal_notes,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }

        $existingFavorite = Favorite::where('user_id', $user->id)
            ->where('note_id', $request->note_id)
            ->first();

        if ($existingFavorite) {
            return $this->error('Note is already in favorites', 400);
        }

        $favorite = Favorite::create([
            'user_id' => $user->id,
            'note_id' => $request->note_id,
        ]);

        $favorite->load(['note.folder']);

        return $this->success($favorite, 'Note added to favorites successfully', 201);
    }

    /**
     * Remove a note from favorites for the authenticated user.
     */
    public function delete($id)
    {
        $user = Auth::user();

        $favorite = Favorite::find($id);

        if (!$favorite) {
            return $this->error('Favorite not found', 404);
        }

        if ($favorite->user_id !== $user->id) {
            return $this->error('Unauthorized', 403);
        }

        $favorite->delete();

        return $this->success(null, 'Note removed from favorites successfully');
    }
}
