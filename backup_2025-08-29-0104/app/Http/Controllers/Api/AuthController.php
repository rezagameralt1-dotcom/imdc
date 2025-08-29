<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    // POST /api/auth/register
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['required','email','max:255','unique:users,email'],
            'password' => ['required', Password::min(8)->mixedCase()->numbers()->letters()],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        // Give default role
        if (method_exists($user, 'assignRole')) {
            $user->assignRole('User');
        } elseif (method_exists($user, 'roles')) {
            try { $user->roles()->attach(optional(\App\Models\Role::where('name','User')->first())->id); } catch (\Throwable $e) {}
        }

        return response()->json(['success' => true, 'data' => ['id' => $user->id], 'trace_id' => uniqid()], 201);
    }

    // POST /api/auth/token  {email, password, device_name}
    public function issueToken(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required','email'],
            'password' => ['required','string'],
            'device_name' => ['required','string','max:255'],
        ]);

        $user = User::where('email', $validated['email'])->first();
        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json(['success' => false, 'error' => 'Invalid credentials', 'trace_id' => uniqid()], 422);
        }

        // Abilities can be passed as array (optional)
        $abilities = $request->input('abilities', ['*']);
        $token = $user->createToken($validated['device_name'], $abilities);
        return response()->json([
            'success' => true,
            'data' => [
                'type' => 'Bearer',
                'token' => $token->plainTextToken,
                'user'  => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => method_exists($user, 'roles') ? $user->roles()->pluck('name') : [],
                ]
            ],
            'trace_id' => uniqid()
        ]);
    }

    // POST /api/auth/logout
    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }
        return response()->json(['success' => true, 'data' => ['message' => 'logged out'], 'trace_id' => uniqid()]);
    }

    // GET /api/auth/me
    public function me(Request $request)
    {
        $user = $request->user();
        return response()->json(['success' => true, 'data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => method_exists($user, 'roles') ? $user->roles()->pluck('name') : [],
        ], 'trace_id' => uniqid()]);
    }
}
