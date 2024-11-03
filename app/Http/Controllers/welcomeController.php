<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Traits\ResponseTrait;

class welcomeController extends Controller
{
    use ResponseTrait;

    public function welcome() {
        return $this->success(null, 'Welcome At Brainmate APIs');
    }
}
