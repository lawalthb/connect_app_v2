<?php
namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Resources\V1\MessageResource;
use App\Http\Resources\V1\UserResource;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MessageController extends BaseController
{
    /**
     * Send a message.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function send(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required_without:file',
            'file' => 'required_without:message|file|max:10240', // 10MB max
            'type' => 'required|string|in:text,image,video,audio',
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error', $validator->errors(), 422);
        }

        $sender = $request->user();
        $receiverId = $request->user_id;

        // Check if the users are connected
        $connected = $sender->connections()->where('id', $receiverId)->exists();

        if (!$connected) {
            return $this->sendError('You are not connected with this user', null, 403);
        }

        $messageData = [
            'sender_id' => $sender->id,
            'receiver_id' => $receiverId,
            'type' => $request->type,
        ];

        if ($request->hasFile('file')) {
            $messageData['message_url'] = 'uploads/message/';
            $messageData['message'] = $request->file('file')->store('messages', 'public');
        } else {
            $messageData['message'] = $request->message;
        }

        $message = Message::create($messageData);

        // Create a notification for the receiver
        $receiver = User::find($receiverId);
        $notification = [
            'notification' => $sender->name . ' sent you a message',
            'notification_title' => 'New Message',
            'notification_type' => 'new_message',
            'object_id' => $message->id,
            'user_id' => $receiverId,
            'sender_id' => $sender->id,
        ];

        \App\Models\Notification::create($notification);

        return $this->sendResponse('Message sent successfully', [
            'message' => new MessageResource($message),
        ]);
    }

    /**
     * Get messages with a specific user.
     *
     * @param Request $request
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMessages(Request $request, $userId)
    {
        $user = $request->user();
        $lastId = $request->query('last_id', 0);

        // Check if the users are connected
        $connected = $user->connections()->where('id', $userId)->exists();

        if (!$connected) {
            return $this->sendError('You are not connected with this user', null, 403);
        }

        // Get messages
        $messages = Message::where(function($query) use ($user, $userId) {
            $query->where('sender_id', $user->id)
                  ->where('receiver_id', $userId);
        })->orWhere(function($query) use ($user, $userId) {
            $query->where('sender_id', $userId)
                  ->where('receiver_id', $user->id);
        });

        if ($lastId > 0) {
            $messages->where('id', '<', $lastId);
        }

        $messages = $messages->orderBy('id', 'desc')
            ->limit(20)
            ->get()
            ->reverse();

        // Mark messages as received
        Message::where('receiver_id', $user->id)
            ->where('sender_id', $userId)
            ->where('receive_flag', 'N')
            ->update(['receive_flag' => 'Y']);

        return $this->sendResponse('Messages retrieved successfully', [
            'messages' => MessageResource::collection($messages),
        ]);
    }

    /**
     * Get the list of users with whom the authenticated user has exchanged messages.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMessageUsers(Request $request)
    {
        $user = $request->user();

        // Get users with whom the authenticated user has exchanged messages
        $messageUsers = User::whereIn('id', function($query) use ($user) {
            $query->select('sender_id')
                  ->from('messages')
                  ->where('receiver_id', $user->id)
                  ->where('deleted_flag', 'N')
                  ->union(
                      \DB::table('messages')
                          ->select('receiver_id')
                          ->where('sender_id', $user->id)
                          ->where('deleted_flag', 'N')
                  );
        })->get();

        // Get connected users with whom the authenticated user has not exchanged messages
        $connectedUsers = $user->connections()
            ->whereNotIn('id', $messageUsers->pluck('id'))
            ->get();

        // Combine the two collections
        $allUsers = $messageUsers->merge($connectedUsers);

        // Add subscription flag
        foreach ($allUsers as $user) {
            $user->subscription_flag = \App\Models\UserSubscription::where('user_id', $user->id)
                ->where('expired_at', '>', now())
                ->exists();
        }

        return $this->sendResponse('Message users retrieved successfully', [
            'users' => UserResource::collection($allUsers),
        ]);
    }
}
