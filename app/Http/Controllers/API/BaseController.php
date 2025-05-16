<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

class BaseController extends Controller
{
    /**
     * Send a success response
     *
     * @param string $message
     * @param array|null $data
     * @param int $code
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendResponse(string $message, ?array $data = null, int $code = 200)
    {
        $response = [
            'status' => 'success',
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $code);
    }

    /**
     * Send an error response
     *
     * @param string $message
     * @param array|null $errors
     * @param int $code
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendError(string $message, ?array $errors = null, int $code = 400)
    {
        $response = [
            'status' => 'error',
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }
}
