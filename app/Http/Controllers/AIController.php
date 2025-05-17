<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;

class AIController extends Controller
{
    public function generateTasks(Request $request)
    {
        try {
            // Validate the request data
            $request->validate([
                'prompt' => 'required|string',
            ]);

            // Prepare the data for the Flask API
            $data = [
                'prompt' => $request->prompt,
            ];

            // Send the data to the Flask API
            $client = new Client();
            $response = $client->post('http://127.0.0.1:5000/generate', [
                'json' => $data,
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
            ]);

            // Decode the response
            $result = json_decode($response->getBody(), true);

            // Return the prediction as JSON
            return response()->json($result);
        } catch (\Exception $e) {
            // Handle any exceptions and return a JSON response
            return response()->json([
                'message' => 'An error occurred while processing your request.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function checkHealth()
    {
        try {
            $client = new Client();
            $response = $client->get('http://127.0.0.1:5000/health');

            $result = json_decode($response->getBody(), true);
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
