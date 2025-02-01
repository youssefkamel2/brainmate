<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

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

        // Attempt to connect to the database
        try {
            // Attempt a query to test the connection
            $results = DB::select('SELECT 1');
            
            // If successful, log and return success message
            Log::info('Database connection successful.');
            return response()->json([
                'message' => 'Database connection successful!',
                'env_variables' => $envVariables
            ], 200);
        } catch (Exception $e) {
            // If there is an error, log the error message and return the error details
            Log::error('Database connection failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'Database connection failed',
                'error' => $e->getMessage(),
                'env_variables' => $envVariables
            ], 500);
        }
    }
}
