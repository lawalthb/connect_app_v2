<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Http\Resources\V1\ConversationResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ConversationController extends BaseController
{
    /**
     * Get user's conversations
     */
    public function index(Request $request)
    {
        try {
            $conversations = $request->user()
                ->conversations()
                ->with(['users', 'latestMessage.user'])
                ->get();

            return $this->sendResponse('Conversations retrieved successfully', [
                'conversations' => ConversationResource::collection($conversations)
            ]);
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve conversations', $e->getMessage(), 500);
        }
    }

    /**
     * Create a new conversation
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:private,group',
            'participants' => 'required|array|min:1',
            'participants.*' => 'exists:users,id',
            'name' => 'required_if:type,group|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        try {
            $currentUser = $request->user();
            $participants = $request->participants;

            // For private conversations, ensure only 2 participants (including current user)
            if ($request->type === 'private') {
                if (count($participants) !== 1) {
                    return $this->sendError('Private conversations must have exactly 2 participants', null, 422);
                }

                // Check if conversation already exists between these users
                $existingConversation = $this->findExistingPrivateConversation($currentUser->id, $participants[0]);
                if ($existingConversation) {
                    return $this->sendResponse('Conversation already exists', [
                        'conversation' => new ConversationResource($existingConversation->load(['users', 'latestMessage.user']))
                    ]);
                }
            }

            // Create conversation
            $conversation = Conversation::create([
                'type' => $request->type,
                'name' => $request->name,
                'description' => $request->description,
                'created_by' => $currentUser->id,
            ]);

            // Add current user as admin
            ConversationParticipant::create([
                'conversation_id' => $conversation->id,
                'user_id' => $currentUser->id,
                'role' => 'admin',
                'joined_at' => now(),
            ]);

            // Add other participants
            foreach ($participants as $participantId) {
                if ($participantId != $currentUser->id) {
                    ConversationParticipant::create([
                        'conversation_id' => $conversation->id,
                        'user_id' => $participantId,
                        'role' => 'member',
                        'joined_at' => now(),
                    ]);
                }
            }

            $conversation->load(['users', 'latestMessage.user']);

            return $this->sendResponse('Conversation created successfully', [
                'conversation' => new ConversationResource($conversation)
            ], 201);
        } catch (\Exception $e) {
            return $this->sendError('Failed to create conversation', $e->getMessage(), 500);
        }
    }

    /**
     * Get a specific conversation
     */
    public function show(Request $request, $id)
    {
        try {
            $conversation = Conversation::with(['users', 'latestMessage.user'])
                ->whereHas('participants', function ($query) use ($request) {
                    $query->where('user_id', $request->user()->id)->where('is_active', true);
                })
                ->find($id);

            if (!$conversation) {
                return $this->sendError('Conversation not found', null, 404);
            }

            return $this->sendResponse('Conversation retrieved successfully', [
                'conversation' => new ConversationResource($conversation)
            ]);
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve conversation', $e->getMessage(), 500);
        }
    }

    /**
     * Leave a conversation
     */
    public function leave(Request $request, $id)
    {
        try {
            $conversation = Conversation::find($id);

            if (!$conversation) {
                return $this->sendError('Conversation not found', null, 404);
            }

            $participant = ConversationParticipant::where('conversation_id', $id)
                ->where('user_id', $request->user()->id)
                ->where('is_active', true)
                ->first();

            if (!$participant) {
                return $this->sendError('You are not a participant in this conversation', null, 403);
            }

            $participant->update([
                'is_active' => false,
                'left_at' => now(),
            ]);

            return $this->sendResponse('Left conversation successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to leave conversation', $e->getMessage(), 500);
        }
    }

    /**
     * Find existing private conversation between two users
     */
    private function findExistingPrivateConversation($userId1, $userId2)
    {
        return Conversation::where('type', 'private')
            ->whereHas('participants', function ($query) use ($userId1) {
                $query->where('user_id', $userId1)->where('is_active', true);
            })
            ->whereHas('participants', function ($query) use ($userId2) {
                $query->where('user_id', $userId2)->where('is_active', true);
            })
            ->first();
    }
}
