<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ModelTestController extends Controller
{
    public function testModels(Request $request)
    {
        // Fetching environment variables to check if they are properly set
        $envVariables = [
            'APP_ENV' => env('APP_ENV', 'no'),
            'DB_CONNECTION' => env('DB_CONNECTION', 'no conn'),
            'DB_HOST' => env('DB_HOST', 'nohost'),
            'DB_PORT' => env('DB_PORT', 'noDB_PORT'),
            'DB_DATABASE' => env('DB_DATABASE', 'nodb'),
            'DB_USERNAME' => env('DB_USERNAME', 'nous'),
            'DB_PASSWORD' => env('DB_PASSWORD', 'nopw'),
            'DB_CHARSET' => env('DB_CHARSET', 'noch'),
            'DB_COLLATION' => env('DB_COLLATION', 'noco'),

        ];

        // Log the environment variables to check in logs
        Log::info('Environment Variables:', $envVariables);

        // Return the environment variables for testing
        return response()->json($envVariables, 200);
    }
}
