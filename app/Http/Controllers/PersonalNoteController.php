<?php

namespace App\Http\Controllers;

use App\Models\Trash;
use App\Models\Favorite;
use App\Models\PersonalNote;
use Illuminate\Http\Request;
use App\Traits\ResponseTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PersonalNoteController extends Controller
{
    use ResponseTrait;

    /**
     * Get all personal notes for the authenticated user.
     */
    public function index()
    {
        $user = Auth::user();

        $notes = PersonalNote::where('user_id', $user->id)->with('folder')->get();

        $notes->each(function ($note) use ($user) {
            $note->isFavorite = Favorite::where('user_id', $user->id)
                ->where('note_id', $note->id)
                ->exists();
        });

        return $this->success($notes, 'Personal notes retrieved successfully');
    }

    /**
     * Create a new personal note for the authenticated user.
     */
    public function create(Request $request)
    {
        $user = Auth::user();

        // Validate the request data
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'folder_id' => 'required|exists:folders,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }

        $note = PersonalNote::create([
            'user_id' => $user->id,
            'folder_id' => $request->folder_id,
            'title' => $request->title,
            'content' => $request->content,
            'date' => $request->date,
        ]);

        return $this->success($note, 'Personal note created successfully', 201);
    }

    /**
     * Get a specific personal note for the authenticated user.
     */
    public function show($id)
    {
        $user = Auth::user();

        // Get the note (including soft-deleted notes)
        $note = PersonalNote::withTrashed()->with('folder')->find($id);

        if (!$note) {
            return $this->error('Personal note not found', 404);
        }

        // Check if the note belongs to the authenticated user
        if ($note->user_id !== $user->id) {
            return $this->error('Unauthorized', 403);
        }

        // Add isFavorite field
        $note->isFavorite = Favorite::where('user_id', $user->id)
            ->where('note_id', $note->id)
            ->exists();

        return $this->success(['note' => $note], 'Personal note retrieved successfully');
    }

    /**
     * Update a specific personal note for the authenticated user.
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();

        $note = PersonalNote::with('folder')->find($id);

        if (!$note) {
            return $this->error('Personal note not found', 404);
        }

        if ($note->user_id !== $user->id) {
            return $this->error('Unauthorized', 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'content' => 'nullable|string',
            'folder_id' => 'nullable|exists:folders,id',
            'date' => 'sometimes|date',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }

        $note->update($request->all());

        $note->isFavorite = Favorite::where('user_id', $user->id)
            ->where('note_id', $note->id)
            ->exists();

        return $this->success($note, 'Personal note updated successfully');
    }

    /**
     * Delete a specific personal note for the authenticated user.
     */
    public function delete($id)
    {
        $user = Auth::user();

        $note = PersonalNote::find($id);

        if (!$note) {
            return $this->error('Personal note not found', 404);
        }

        if ($note->user_id !== $user->id) {
            return $this->error('Unauthorized', 403);
        }

        // Move the note to the trash
        Trash::create([
            'user_id' => $user->id,
            'note_id' => $note->id,
        ]);

        $note->delete();

        return $this->success(null, 'Personal note moved to trash successfully');
    }

    public function getNotesByFolder($folder_id)
    {
        $user = Auth::user();

        $notes = PersonalNote::where('user_id', $user->id)
            ->where('folder_id', $folder_id)
            ->with('folder')
            ->get();

        $notes->each(function ($note) use ($user) {
            $note->isFavorite = Favorite::where('user_id', $user->id)
                ->where('note_id', $note->id)
                ->exists();
        });

        return $this->success($notes, 'Notes in folder retrieved successfully');
    }
}
