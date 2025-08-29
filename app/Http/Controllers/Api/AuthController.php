<?php
namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email:rfc,dns|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        if (method_exists($user, 'roles')) {
            $roleModel = \App\Models\Role::firstOrCreate(['name' => 'User']);
            $user->roles()->syncWithoutDetaching([$roleModel->id]);
        }

        return response()->json(['success'=>true,'data'=>[
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles()->pluck('name')->all(),
            ]
        ],'trace_id'=>uniqid()]);
    }

    public function issueToken(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:255'
        ]);

        $user = User::where('email', $request->email)->first();
        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken($request->device_name ?: 'api')->plainTextToken;

        return response()->json(['success'=>true,'data'=>[
            'type' => 'Bearer',
            'token'=> $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles()->pluck('name')->all(),
            ]
        ],'trace_id'=>uniqid()]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }
        return response()->json(['success'=>true,'data'=>['message'=>'logged out'],'trace_id'=>uniqid()]);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        return response()->json(['success'=>true,'data'=>[
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles()->pluck('name')->all(),
            ]
        ],'trace_id'=>uniqid()]);
    }
}
