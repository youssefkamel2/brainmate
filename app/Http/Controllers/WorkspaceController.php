<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Traits\ResponseTrait;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{

    use ResponseTrait;
    /**
     * Get all workspaces.
     */
    public function index()
    {
        $workspaces = Workspace::all();

        return $this->success(['workspaces' => $workspaces], 'workspaces reterived successfully.');
    }
}