<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Models\Project;
use App\Models\Reminder;
use App\Models\TaskNote;
use App\Models\Workspace;
use App\Models\Attachment;
use App\Models\TaskMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Traits\ResponseTrait;

class ModelTestController extends Controller
{

    use ResponseTrait;

    public function testModels(Request $request){

        $test = env('DB_DATABASE','no');

        return $this->success($test, 200);


    }
}
