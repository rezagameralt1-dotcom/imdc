<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        $q = DB::table('messages')->select('id', 'sender_id', 'receiver_id', 'safe_room_id', 'body', 'created_at');

        if ($sid = $request->query('sender_id')) {
            $q->where('sender_id', (int) $sid);
        }
        if ($rid = $request->query('receiver_id')) {
            $q->where('receiver_id', (int) $rid);
        }
        if ($room = $request->query('safe_room_id')) {
            $q->where('safe_room_id', (int) $room);
        }

        $q->orderByDesc('id');

        return ApiResponse::success($q->paginate(min((int) $request->query('per_page', 20), 100)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'sender_id' => 'required|integer|exists:users,id',
            'receiver_id' => 'nullable|integer|exists:users,id',
            'safe_room_id' => 'nullable|integer|exists:safe_rooms,id',
            'body' => 'required|string|min:1',
        ]);

        // At least one of receiver_id or safe_room_id must be present
        if (empty($data['receiver_id']) && empty($data['safe_room_id'])) {
            return ApiResponse::error('Either receiver_id or safe_room_id must be provided', 422);
        }

        $id = DB::table('messages')->insertGetId([
            'sender_id' => $data['sender_id'],
            'receiver_id' => $data['receiver_id'] ?? null,
            'safe_room_id' => $data['safe_room_id'] ?? null,
            'body' => $data['body'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Optional: write to audit
        DB::table('audit_logs')->insert([
            'event' => 'message.created',
            'user_id' => $data['sender_id'],
            'payload' => json_encode(['message_id' => $id]),
            'created_at' => now(),
        ]);

        return ApiResponse::success(['id' => $id], 201);
    }

    public function destroy(int $id)
    {
        $deleted = DB::table('messages')->where('id', $id)->delete();
        if ($deleted === 0) {
            return ApiResponse::error('Message not found', 404);
        }

        return ApiResponse::success();
    }
}
