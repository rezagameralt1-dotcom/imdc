<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthTokenController extends Controller
{
    public function issue(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string'
        ]);

        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_CREDENTIALS',
                    'message' => 'Email or password is incorrect.',
                ],
                'trace_id' => $request->header('X-Trace-Id') ?: null,
            ], 422);
        }

        $device = $data['device_name'] ?? 'cli';
        $plain = $user->createToken($device)->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $plain,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->name,
                ]
            ],
            'trace_id' => $request->header('X-Trace-Id') ?: null,
        ]);
    }
}
