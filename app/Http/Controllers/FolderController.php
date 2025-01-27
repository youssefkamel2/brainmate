<?php

namespace App\Http\Controllers;

use App\Models\Folder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Traits\ResponseTrait;
use Illuminate\Support\Facades\Validator;

class FolderController extends Controller
{
    use ResponseTrait;

    /**
     * Get all folders for the authenticated user.
     */
    public function index()
    {
        $user = Auth::user();

        $folders = Folder::where('user_id', $user->id)->get();

        return $this->success(['folders' => $folders], 'Folders retrieved successfully');
    }

    /**
     * Create a new folder for the authenticated user.
     */
    public function create(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $folder = Folder::create([
            'user_id' => $user->id,
            'name' => $request->name,
        ]);

        return $this->success(['folder' => $folder], 'Folder created successfully', 201);
    }

    /**
     * Get a specific folder for the authenticated user.
     */
    public function show($id)
    {
        $user = Auth::user();
    
        $folder = Folder::find($id);
    
        if (!$folder) {
            return $this->error('Folder not found', 404);
        }
    
        // Ensure the folder belongs to the user
        if ($folder->user_id !== $user->id) {
            return $this->error('Unauthorized', 403);
        }
    
        return $this->success(['folder' => $folder], 'Folder retrieved successfully');
    }

    /**
     * Update a specific folder for the authenticated user.
     */
    public function update(Request $request, Folder $folder)
    {
        $user = Auth::user();

        // Ensure the folder belongs to the user
        if ($folder->user_id !== $user->id) {
            return $this->error('Unauthorized', 403);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
        ]);

        $folder->update($request->all());

        return $this->success(['folder' => $folder], 'Folder updated successfully');
    }

    /**
     * Delete a specific folder for the authenticated user.
     */
    public function delete($id)
    {
        $user = Auth::user();
    
        $folder = Folder::find($id);
    
        if (!$folder) {
            return $this->error('Folder not found', 404);
        }
    
        // Ensure the folder belongs to the user
        if ($folder->user_id !== $user->id) {
            return $this->error('Unauthorized', 403);
        }
    
        $folder->delete();
    
        return $this->success(null, 'Folder deleted successfully');
    }
}
