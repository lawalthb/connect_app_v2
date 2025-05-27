<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Models\Call;
use App\Models\CallParticipant;
use App\Models\Conversation;
use App\Models\Message;
use App\Helpers\AgoraHelper;
use App\Events\CallInitiated;
use App\Events\CallAnswered;
use App\Events\CallEnded;
use App\Events\CallMissed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class CallController extends BaseController
{
    /**
     * Initiate a call
     */
    public function initiate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'conversation_id' => 'required|exists:conversations,id',
            'call_type' => 'required|in:audio,video',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        try {
            $user = $request->user();
            $conversation = Conversation::with('users')->findOrFail($request->conversation_id);

            // Check if user is part of the conversation
            if (!$conversation->users->contains($user->id)) {
                return $this->sendError('You are not a participant in this conversation', null, 403);
            }

            // Check if there's already an active call
            if ($conversation->hasActiveCall()) {
                return $this->sendError('There is already an active call in this conversation', null, 409);
            }

            DB::beginTransaction();

            // Create call
            $call = Call::create([
                'conversation_id' => $conversation->id,
                'initiated_by' => $user->id,
                'call_type' => $request->call_type,
                'status' => 'initiated',
                'agora_channel_name' => Call::generateChannelName(),
                'started_at' => now(),
            ]);

            // Get all conversation participants except the caller
            $participants = $conversation->users->where('id', '!=', $user->id);
            $allParticipantIds = $conversation->users->pluck('id')->toArray();

            // Generate Agora tokens for all participants (including caller)
            $tokens = AgoraHelper::generateTokensForUsers($call->agora_channel_name, $allParticipantIds);

            // Save tokens to call
            $call->agora_tokens = $tokens;
            $call->save();

            // Create call participant records
            foreach ($conversation->users as $participant) {
                $participantStatus = $participant->id === $user->id ? 'joined' : 'invited';
                $tokenData = $tokens[$participant->id] ?? null;

                CallParticipant::create([
                    'call_id' => $call->id,
                    'user_id' => $participant->id,
                    'status' => $participantStatus,
                    'agora_token' => $tokenData['token'] ?? null,
                    'agora_uid' => $tokenData['agora_uid'] ?? null,
                    'invited_at' => now(),
                    'joined_at' => $participant->id === $user->id ? now() : null,
                ]);
            }

            // Create call message in conversation
            Message::create([
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'message' => 'Call started',
                'type' => 'call_started',
                'metadata' => [
                    'call_id' => $call->id,
                    'call_type' => $call->call_type,
                ],
            ]);

            DB::commit();

            // Broadcast call initiated event
            broadcast(new CallInitiated($call, $conversation, $user))->toOthers();

            return $this->sendResponse('Call initiated successfully', [
                'call' => $this->formatCallData($call->fresh(['participants.user', 'initiator'])),
                'agora_config' => [
                    'app_id' => AgoraHelper::getAppId(),
                    'channel_name' => $call->agora_channel_name,
                    'token' => $tokens[$user->id]['token'],
                    'uid' => $tokens[$user->id]['agora_uid'],
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Failed to initiate call', $e->getMessage(), 500);
        }
    }

    /**
     * Answer a call
     */
    public function answer(Request $request, $callId)
    {
        try {
            $user = $request->user();
            $call = Call::with(['participants.user', 'conversation'])->findOrFail($callId);

            // Check if user is a participant
            $participant = $call->participants->where('user_id', $user->id)->first();
            if (!$participant) {
                return $this->sendError('You are not a participant in this call', null, 403);
            }

            // Check if call is still active
            if (!$call->isActive()) {
                return $this->sendError('Call is no longer active', null, 409);
            }

            DB::beginTransaction();

            // Update participant status
            $participant->update([
                'status' => 'joined',
                'joined_at' => now(),
            ]);

            // Update call status to connected if this is the first answer
            if ($call->status === 'initiated') {
                $call->update([
                    'status' => 'connected',
                    'connected_at' => now(),
                ]);
            }

            DB::commit();

            // Broadcast call answered event
            broadcast(new CallAnswered($call, $user))->toOthers();

            return $this->sendResponse('Call answered successfully', [
                'call' => $this->formatCallData($call->fresh(['participants.user', 'initiator'])),
                'agora_config' => [
                    'app_id' => AgoraHelper::getAppId(),
                    'channel_name' => $call->agora_channel_name,
                    'token' => $participant->agora_token,
                    'uid' => $participant->agora_uid,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Failed to answer call', $e->getMessage(), 500);
        }
    }

    /**
     * End a call
     */
    public function end(Request $request, $callId)
    {
        try {
            $user = $request->user();
            $call = Call::with(['participants.user', 'conversation'])->findOrFail($callId);

            // Check if user is a participant
            $participant = $call->participants->where('user_id', $user->id)->first();
            if (!$participant) {
                return $this->sendError('You are not a participant in this call', null, 403);
            }

            DB::beginTransaction();

            $now = now();

            // Update participant
            if ($participant->status === 'joined' && !$participant->left_at) {
                $participant->update([
                    'status' => 'left',
                    'left_at' => $now,
                ]);
                $participant->updateDuration();
            }

            // End the call if initiated by caller or if all participants left
            $activeParticipants = $call->participants()->where('status', 'joined')->count();

            if ($call->initiated_by === $user->id || $activeParticipants <= 1) {
                $call->update([
                    'status' => 'ended',
                    'ended_at' => $now,
                    'end_reason' => 'ended_by_caller',
                ]);

                // Update all remaining participants
                $call->participants()->where('status', 'joined')->update([
                    'status' => 'left',
                    'left_at' => $now,
                ]);

                $call->updateDuration();

                // Create call ended message
                Message::create([
                    'conversation_id' => $call->conversation_id,
                    'user_id' => $user->id,
                    'message' => 'Call ended',
                    'type' => 'call_ended',
                    'metadata' => [
                        'call_id' => $call->id,
                        'call_type' => $call->call_type,
                        'duration' => $call->duration,
                        'formatted_duration' => $call->formatted_duration,
                    ],
                ]);
            }

            DB::commit();

            // Broadcast call ended event
            broadcast(new CallEnded($call, $user))->toOthers();

            return $this->sendResponse('Call ended successfully', [
                'call' => $this->formatCallData($call->fresh(['participants.user', 'initiator'])),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Failed to end call', $e->getMessage(), 500);
        }
    }

    /**
     * Reject a call
     */
    public function reject(Request $request, $callId)
    {
        try {
            $user = $request->user();
            $call = Call::with(['participants.user', 'conversation'])->findOrFail($callId);

            // Check if user is a participant
            $participant = $call->participants->where('user_id', $user->id)->first();
            if (!$participant) {
                return $this->sendError('You are not a participant in this call', null, 403);
            }

            // Check if call can be rejected
            if (!in_array($call->status, ['initiated', 'ringing'])) {
                return $this->sendError('Call cannot be rejected at this time', null, 409);
            }

            DB::beginTransaction();

            // Update participant status
            $participant->update([
                'status' => 'rejected',
                'left_at' => now(),
            ]);

            // Check if all participants rejected (for group calls)
            $activeParticipants = $call->participants()
                ->whereNotIn('status', ['rejected', 'missed'])
                ->where('user_id', '!=', $call->initiated_by)
                ->count();

            if ($activeParticipants === 0) {
                $call->update([
                    'status' => 'missed',
                    'ended_at' => now(),
                    'end_reason' => 'rejected',
                ]);

                // Create missed call message
                Message::create([
                    'conversation_id' => $call->conversation_id,
                    'user_id' => $call->initiated_by,
                    'message' => 'Missed call',
                    'type' => 'call_missed',
                    'metadata' => [
                        'call_id' => $call->id,
                        'call_type' => $call->call_type,
                    ],
                ]);

                broadcast(new CallMissed($call))->toOthers();
            }

            DB::commit();

            return $this->sendResponse('Call rejected successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Failed to reject call', $e->getMessage(), 500);
        }
    }

    /**
     * Get call history for a conversation
     */
    public function history(Request $request, $conversationId)
    {
        try {
            $user = $request->user();
            $conversation = Conversation::findOrFail($conversationId);

            // Check if user is part of the conversation
            if (!$conversation->users->contains($user->id)) {
                return $this->sendError('You are not a participant in this conversation', null, 403);
            }

            $calls = $conversation->calls()
                ->with(['participants.user', 'initiator'])
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return $this->sendResponse('Call history retrieved successfully', [
                'calls' => $calls->items(),
                'pagination' => [
                    'current_page' => $calls->currentPage(),
                    'total_pages' => $calls->lastPage(),
                    'total_items' => $calls->total(),
                    'per_page' => $calls->perPage(),
                ],
            ]);

        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve call history', $e->getMessage(), 500);
        }
    }

    /**
     * Get user's recent calls
     */
    public function recentCalls(Request $request)
    {
        try {
            $user = $request->user();

            $calls = Call::whereHas('participants', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->with(['participants.user', 'initiator', 'conversation'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

            return $this->sendResponse('Recent calls retrieved successfully', [
                'calls' => $calls->items(),
                'pagination' => [
                    'current_page' => $calls->currentPage(),
                    'total_pages' => $calls->lastPage(),
                    'total_items' => $calls->total(),
                    'per_page' => $calls->perPage(),
                ],
            ]);

        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve recent calls', $e->getMessage(), 500);
        }
    }

    /**
     * Format call data for API response
     */
    private function formatCallData(Call $call): array
    {
        return [
            'id' => $call->id,
            'conversation_id' => $call->conversation_id,
            'call_type' => $call->call_type,
            'status' => $call->status,
            'duration' => $call->duration,
            'formatted_duration' => $call->formatted_duration,
            'started_at' => $call->started_at?->toISOString(),
            'connected_at' => $call->connected_at?->toISOString(),
            'ended_at' => $call->ended_at?->toISOString(),
            'end_reason' => $call->end_reason,
            'initiator' => [
                'id' => $call->initiator->id,
                'name' => $call->initiator->name,
                'username' => $call->initiator->username,
                'profile_url' => $call->initiator->profile_url,
            ],
            'participants' => $call->participants->map(function ($participant) {
                return [
                    'user_id' => $participant->user_id,
                    'name' => $participant->user->name,
                    'username' => $participant->user->username,
                    'profile_url' => $participant->user->profile_url,
                    'status' => $participant->status,
                    'joined_at' => $participant->joined_at?->toISOString(),
                    'left_at' => $participant->left_at?->toISOString(),
                    'duration' => $participant->duration,
                ];
            }),
        ];
    }
}
