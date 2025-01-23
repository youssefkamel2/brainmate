<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use Illuminate\Http\Request;
use App\Traits\ResponseTrait;
use Illuminate\Support\Facades\Validator;

class WorkspaceController extends Controller
{
    use ResponseTrait;

    /**
     * Get all workspaces.
     */
    public function index()
    {
        $workspaces = Workspace::all();

        return $this->success(['workspaces' => $workspaces], 'Workspaces retrieved successfully.');
    }

    /**
     * Create a new workspace.
     */
    public function create(Request $request)
    {
        // Validate the request data
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048', // Validate each image
            'location' => 'required|string|max:255',
            'map_url' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'amenities' => 'nullable|string|max:255',
            'rating' => 'nullable|numeric|between:0,5',
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'wifi' => 'nullable|boolean',
            'coffee' => 'nullable|boolean',
            'meetingroom' => 'nullable|boolean',
            'silentroom' => 'nullable|boolean',
            'amusement' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }

        // Upload images and store their paths
        $imagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $fileName = time() . '_' . $image->getClientOriginalName(); // Unique file name
                $image->move(public_path('uploads/workspaces'), $fileName); // Store in public/uploads/workspaces
                $imagePaths[] = $fileName;
            }
        }

        // Create the workspace
        $workspace = Workspace::create([
            'name' => $request->name,
            'images' => implode(',', $imagePaths), // Store image paths as a comma-separated string
            'location' => $request->location,
            'map_url' => $request->map_url,
            'phone' => $request->phone,
            'amenities' => $request->amenities,
            'rating' => $request->rating,
            'price' => $request->price,
            'description' => $request->description,
            'wifi' => $request->wifi ?? false,
            'coffee' => $request->coffee ?? false,
            'meetingroom' => $request->meetingroom ?? false,
            'silentroom' => $request->silentroom ?? false,
            'amusement' => $request->amusement ?? false,
        ]);

        return $this->success(['workspace' => $workspace], 'Workspace created successfully.', 201);
    }
}