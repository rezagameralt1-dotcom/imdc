<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\PanicCode;
use App\Models\SafeRoom;
use App\Notifications\PanicAlert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

class SafeRoomController extends Controller
{
    public function index()
    {
        $rooms = SafeRoom::where('owner_id', Auth::id())->latest()->get();
        return response()->json($rooms);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:120']
        ]);
        $room = SafeRoom::create([
            'name' => $data['name'],
            'owner_id' => Auth::id(),
            'sealed' => false,
        ]);
        return response()->json($room, 201);
    }

    public function seal(SafeRoom $room)
    {
        $this->authorizeRoom($room);
        $room->update(['sealed' => true]);
        return response()->json($room);
    }

    public function unseal(SafeRoom $room)
    {
        $this->authorizeRoom($room);
        $room->update(['sealed' => false]);
        return response()->json($room);
    }

    public function setPanicCode(Request $request)
    {
        $data = $request->validate([
            'code' => ['required','string','min:4','max:64']
        ]);
        $hash = Hash::make($data['code']);
        PanicCode::updateOrCreate(['user_id' => Auth::id()], ['code_hash' => $hash]);
        return response()->json(['ok' => true]);
    }

    public function triggerPanic(Request $request)
    {
        $data = $request->validate(['code' => ['required','string']]);
        $pc = PanicCode::where('user_id', Auth::id())->first();
        if (!$pc || !Hash::check($data['code'], $pc->code_hash)) {
            abort(403, 'Invalid panic code');
        }
        $pc->last_triggered_at = now();
        $pc->save();
        Notification::route('mail', Auth::user()->email)
            ->notify(new PanicAlert(Auth::user()->email, now()->toISOString()));
        return response()->json(['ok' => true, 'triggered_at' => $pc->last_triggered_at]);
    }

    protected function authorizeRoom(SafeRoom $room): void
    {
        if ($room->owner_id !== Auth::id()) {
            abort(403, 'Forbidden room access');
        }
    }
}
