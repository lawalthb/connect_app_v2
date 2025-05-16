<?php
namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use App\Models\UserRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ConnectionController extends BaseController
{
    /**
     * Send a connection request.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'receiver_id' => 'required|exists:users,id',
            'social_id' => 'required|exists:social_circles,id',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error', $validator->errors(), 422);
        }

        $sender = $request->user();
        $receiver_id = $request->receiver_id;

        // Check if the user is trying to connect with themselves
        if ($sender->id == $receiver_id) {
            return $this->sendError('You cannot connect with yourself', null, 422);
        }

        // Check if a request already exists
        $existingRequest = UserRequest::where(function($query) use ($sender, $receiver_id) {
            $query->where('sender_id', $sender->id)
                  ->where('receiver_id', $receiver_id);
        })->orWhere(function($query) use ($sender, $receiver_id) {
            $query->where('sender_id', $receiver_id)
                  ->where('receiver_id', $sender->id);
        })->first();

        if ($existingRequest) {
            return $this->sendError('A connection request already exists', null, 422);
        }

        // Create the connection request
        $request = UserRequest::create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiver_id,
            'social_id' => $request->social_id,
            'status' => 'Pending',
            'sender_status' => 'Pending',
            'receiver_status' => 'Pending',
        ]);

        return $this->sendResponse('Connection request sent successfully', [
            'request' => $request,
        ]);
    }

    /**
     * Accept or reject a connection request.
     *
     * @param Request $request
     * @param int $requestId
     * @return \Illuminate\Http\JsonResponse
     */
    public function respondToRequest(Request $request, $requestId)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:Accepted,Rejected',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error', $validator->errors(), 422);
        }

        $connectionRequest = UserRequest::findOrFail($requestId);

        // Check if the authenticated user is the receiver of the request
        if ($connectionRequest->receiver_id != $request->user()->id) {
            return $this->sendError('Unauthorized', null, 403);
        }

        // Update the request status
        $connectionRequest->update([
            'status' => $request->status,
            'sender_status' => $request->status,
            'receiver_status' => $request->status,
        ]);

        return $this->sendResponse('Connection request ' . strtolower($request->status) . ' successfully', [
            'request' => $connectionRequest,
        ]);
    }

    /**
     * Disconnect from a user.
     *
     * @param Request $request
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function disconnect(Request $request, $userId)
    {
        $user = $request->user();

        // Find the connection request
        $connectionRequest = UserRequest::where(function($query) use ($user, $userId) {
            $query->where('sender_id', $user->id)
                  ->where('receiver_id', $userId)
                  ->where('status', 'Accepted');
        })->orWhere(function($query) use ($user, $userId) {
            $query->where('sender_id', $userId)
                  ->where('receiver_id', $user->id)
                  ->where('status', 'Accepted');
        })->first();

        if (!$connectionRequest) {
            return $this->sendError('Connection not found', null, 404);
        }

        // Update the connection status
        if ($connectionRequest->sender_id == $user->id) {
            $connectionRequest->update([
                'sender_status' => 'Disconnect',
                'receiver_status' => 'Disconnect',
                'status' => 'Disconnect',
            ]);
        } else {
            $connectionRequest->update([
                'sender_status' => 'Disconnect',
                'receiver_status' => 'Disconnect',
                'status' => 'Disconnect',
            ]);
        }

        return $this->sendResponse('Disconnected successfully');
    }

    /**
     * Get the authenticated user's connections.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getConnections(Request $request)
    {
        $user = $request->user();
        $connections = $user->connections()->paginate(10);

        return $this->sendResponse('Connections retrieved successfully', [
            'connections' => UserResource::collection($connections),
            'pagination' => [
                'total' => $connections->total(),
                'count' => $connections->count(),
                'per_page' => $connections->perPage(),
                'current_page' => $connections->currentPage(),
                'total_pages' => $connections->lastPage(),
            ],
        ]);
    }

    /**
     * Get the authenticated user's incoming connection requests.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getIncomingRequests(Request $request)
    {
        $user = $request->user();
        $incomingRequests = $user->incomingRequests()->paginate(10);

        return $this->sendResponse('Incoming requests retrieved successfully', [
            'requests' => UserResource::collection($incomingRequests),
            'pagination' => [
                'total' => $incomingRequests->total(),
                'count' => $incomingRequests->count(),
                'per_page' => $incomingRequests->perPage(),
                'current_page' => $incomingRequests->currentPage(),
                'total_pages' => $incomingRequests->lastPage(),
            ],
        ]);
    }
}
