<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\ConversationParticipant;
use App\Http\Resources\V1\MessageResource;
use App\Events\MessageSent;
use App\Helpers\FileUploadHelper;
use App\Http\Resources\V1\ConversationResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MessageController extends BaseController
{
    /**
     * Get messages in a conversation
     */
    public function index(Request $request, $conversationId)
    {
        $validator = Validator::make($request->all(), [
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        try {
            $conversation = Conversation::find($conversationId);

            if (!$conversation) {
                return $this->sendError('Conversation not found', null, 404);
            }

            // Check if user is participant
            if (!$conversation->hasParticipant($request->user()->id)) {
                return $this->sendError('You are not a participant in this conversation', null, 403);
            }

            $perPage = $request->get('per_page', 20);
            $messages = $conversation->messages()
                ->with(['user', 'replyToMessage.user'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return $this->sendResponse('Messages retrieved successfully', [
                'messages' => MessageResource::collection($messages->items()),
                'pagination' => [
                    'current_page' => $messages->currentPage(),
                    'last_page' => $messages->lastPage(),
                    'per_page' => $messages->perPage(),
                    'total' => $messages->total(),
                    'has_more' => $messages->hasMorePages(),
                ]
            ]);
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve messages', $e->getMessage(), 500);
        }
    }

    /**
     * Send a message with optional file upload
     */
    public function store(Request $request, $conversationId)
    {
        // Dynamic validation based on message type
        $rules = [
            'type' => 'required|in:text,image,video,audio,file,location',
            'reply_to_message_id' => 'nullable|exists:messages,id',
        ];

        // Add specific validation based on type
        if ($request->type === 'text') {
            $rules['message'] = 'required|string|max:4000';
        } elseif (in_array($request->type, ['image', 'video', 'audio', 'file'])) {
            $rules['file'] = 'required|file';
            $rules['message'] = 'nullable|string|max:1000'; // Optional caption

            // Add file-specific validation
            $this->addFileValidationRules($rules, $request->type);
        } elseif ($request->type === 'location') {
            $rules['latitude'] = 'required|numeric|between:-90,90';
            $rules['longitude'] = 'required|numeric|between:-180,180';
            $rules['address'] = 'nullable|string|max:500';
            $rules['message'] = 'nullable|string|max:1000';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        try {
            $conversation = Conversation::find($conversationId);

            if (!$conversation) {
                return $this->sendError('Conversation not found', null, 404);
            }

            // Check if user is participant
            if (!$conversation->hasParticipant($request->user()->id)) {
                return $this->sendError('You are not a participant in this conversation', null, 403);
            }

            $messageData = [
                'conversation_id' => $conversationId,
                'user_id' => $request->user()->id,
                'type' => $request->type,
                'reply_to_message_id' => $request->reply_to_message_id,
            ];

            // Handle different message types
            if ($request->type === 'text') {
                $messageData['message'] = $request->message;
                $messageData['metadata'] = null;
            } elseif (in_array($request->type, ['image', 'video', 'audio', 'file'])) {
                // Handle file upload
                $metadata = $this->handleFileUpload($request->file('file'), $request->type, $request->user()->id);
                $messageData['message'] = $request->message ?: null; // Optional caption
                $messageData['metadata'] = $metadata;
            } elseif ($request->type === 'location') {
                $messageData['message'] = $request->message ?: null;
                $messageData['metadata'] = [
                    'latitude' => $request->latitude,
                    'longitude' => $request->longitude,
                    'address' => $request->address,
                ];
            }

            // Create message
            $message = Message::create($messageData);

            // Update conversation's last message time
            $conversation->update([
                'last_message_at' => now(),
            ]);

            // Load relationships for response
            $message->load(['user', 'replyToMessage.user']);

            // Broadcast the message
            broadcast(new MessageSent($message))->toOthers();

            return $this->sendResponse('Message sent successfully', [
                'message' => new MessageResource($message)
            ], 201);
        } catch (\Exception $e) {
            return $this->sendError('Failed to send message', $e->getMessage(), 500);
        }
    }

    /**
     * Add file validation rules based on type
     */
    private function addFileValidationRules(&$rules, $type)
    {
        $maxSizes = FileUploadHelper::getMaxFileSizes();
        $allowedTypes = FileUploadHelper::getAllowedMimeTypes();

        $maxSizeKB = $maxSizes[$type] * 1024; // Convert MB to KB
        $mimeTypes = implode(',', $allowedTypes[$type]);

        $rules['file'] .= "|max:$maxSizeKB|mimes:" . str_replace('/', ',', str_replace(['application/', 'image/', 'video/', 'audio/'], '', $mimeTypes));

        // Specific rules for each type
        switch ($type) {
            case 'image':
                $rules['file'] .= '|image|dimensions:max_width=4000,max_height=4000';
                break;
            case 'video':
                // Add video-specific rules if needed
                break;
            case 'audio':
                // Add audio-specific rules if needed
                break;
        }
    }

    /**
     * Handle file upload
     */
    private function handleFileUpload($file, $type, $userId)
    {
        // Validate file type
        if (!FileUploadHelper::validateFileType($file, $type)) {
            throw new \Exception('Invalid file type for ' . $type);
        }

        // Upload file and get metadata
        return FileUploadHelper::uploadMessageFile($file, $type, $userId);
    }

       /**
     * Mark messages as read
     */
    public function markAsRead(Request $request, $conversationId)
    {
        try {
            $conversation = Conversation::find($conversationId);

            if (!$conversation) {
                return $this->sendError('Conversation not found', null, 404);
            }

            // Check if user is participant
            $participant = ConversationParticipant::where('conversation_id', $conversationId)
                ->where('user_id', $request->user()->id)
                ->where('is_active', true)
                ->first();

            if (!$participant) {
                return $this->sendError('You are not a participant in this conversation', null, 403);
            }

            // Update last read timestamp
            $participant->update([
                'last_read_at' => now(),
            ]);

            return $this->sendResponse('Messages marked as read');
        } catch (\Exception $e) {
            return $this->sendError('Failed to mark messages as read', $e->getMessage(), 500);
        }
    }

    /**
     * Delete a message
     */
    public function destroy(Request $request, $conversationId, $messageId)
    {
        try {
            $message = Message::where('id', $messageId)
                ->where('conversation_id', $conversationId)
                ->where('user_id', $request->user()->id)
                ->first();

            if (!$message) {
                return $this->sendError('Message not found or you do not have permission to delete it', null, 404);
            }

            $message->update([
                'is_deleted' => true,
                'deleted_at' => now(),
            ]);

            return $this->sendResponse('Message deleted successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to delete message', $e->getMessage(), 500);
        }
    }

    /**
     * Send a direct message (creates conversation if needed)
     */
    public function sendDirectMessage(Request $request)
    {
        // Dynamic validation based on message type
        $rules = [
            'recipient_id' => 'required|exists:users,id|different:user_id',
            'type' => 'required|in:text,image,video,audio,file,location',
        ];

        // Add specific validation based on type
        if ($request->type === 'text') {
            $rules['message'] = 'required|string|max:4000';
        } elseif (in_array($request->type, ['image', 'video', 'audio', 'file'])) {
            $rules['file'] = 'required|file';
            $rules['message'] = 'nullable|string|max:1000'; // Optional caption

            // Add file-specific validation
            $this->addFileValidationRules($rules, $request->type);
        } elseif ($request->type === 'location') {
            $rules['latitude'] = 'required|numeric|between:-90,90';
            $rules['longitude'] = 'required|numeric|between:-180,180';
            $rules['address'] = 'nullable|string|max:500';
            $rules['message'] = 'nullable|string|max:1000';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        try {
            $currentUser = $request->user();
            $recipientId = $request->recipient_id;

            // Check if private conversation already exists
            $conversation = Conversation::where('type', 'private')
                ->whereHas('participants', function ($query) use ($currentUser) {
                    $query->where('user_id', $currentUser->id)->where('is_active', true);
                })
                ->whereHas('participants', function ($query) use ($recipientId) {
                    $query->where('user_id', $recipientId)->where('is_active', true);
                })
                ->first();

            // Create conversation if it doesn't exist
            if (!$conversation) {
                $conversation = Conversation::create([
                    'type' => 'private',
                    'created_by' => $currentUser->id,
                ]);

                // Add participants
                ConversationParticipant::create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $currentUser->id,
                    'role' => 'member',
                    'joined_at' => now(),
                ]);

                ConversationParticipant::create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $recipientId,
                    'role' => 'member',
                    'joined_at' => now(),
                ]);
            }

            $messageData = [
                'conversation_id' => $conversation->id,
                'user_id' => $currentUser->id,
                'type' => $request->type,
            ];

            // Handle different message types
            if ($request->type === 'text') {
                $messageData['message'] = $request->message;
                $messageData['metadata'] = null;
            } elseif (in_array($request->type, ['image', 'video', 'audio', 'file'])) {
                // Handle file upload
                $metadata = $this->handleFileUpload($request->file('file'), $request->type, $currentUser->id);
                $messageData['message'] = $request->message ?: null; // Optional caption
                $messageData['metadata'] = $metadata;
            } elseif ($request->type === 'location') {
                $messageData['message'] = $request->message ?: null;
                $messageData['metadata'] = [
                    'latitude' => $request->latitude,
                    'longitude' => $request->longitude,
                    'address' => $request->address,
                ];
            }

            // Create message
            $message = Message::create($messageData);

            // Update conversation's last message time
            $conversation->update([
                'last_message_at' => now(),
            ]);

            // Load relationships for response
            $message->load(['user']);

            // Broadcast the message
            broadcast(new MessageSent($message))->toOthers();

            return $this->sendResponse('Message sent successfully', [
                'message' => new MessageResource($message),
                'conversation' => new ConversationResource($conversation->load(['users']))
            ], 201);
        } catch (\Exception $e) {
            return $this->sendError('Failed to send message', $e->getMessage(), 500);
        }
    }
}

