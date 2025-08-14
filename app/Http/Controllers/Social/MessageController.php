<?php

namespace App\Http\Controllers\Social;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\SafeRoom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{
    public function index(SafeRoom $room)
    {
        $this->authorizeRoom($room);
        $items = $room->messages()->with(['from', 'to'])->latest()->paginate(50);

        return response()->json($items);
    }

    public function store(Request $request, SafeRoom $room)
    {
        $this->authorizeRoom($room);
        $data = $request->validate([
            'to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'body' => ['required', 'string', 'max:4000'],
            'meta' => ['array'],
        ]);
        $data['from_user_id'] = Auth::id();
        $data['safe_room_id'] = $room->id;
        $msg = Message::create($data);
        event(new MessageSent($msg));

        return response()->json($msg, 201);
    }

    protected function authorizeRoom(SafeRoom $room): void
    {
        if ($room->owner_id !== Auth::id()) {
            abort(403, 'Forbidden room access');
        }
    }
}
