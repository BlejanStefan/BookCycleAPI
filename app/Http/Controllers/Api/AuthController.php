<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


class AuthController extends Controller
{
    /**
     * Registra un nuevo usuario en la plataforma, valida sus credenciales,
     * procesa su avatar y genera un token de autenticación.
     * @param Request $request
     * @return JsonResponse
     */
    public function register(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:255|unique:users',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'municipality_id' => 'required|exists:municipalities,id',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $user = new User([
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'municipality_id' => $request->municipality_id,
        ]);

        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');

            $safeUsername = Str::slug($request->username ?? 'avatar');

            $timestamp = time();

            $extension = $file->getClientOriginalExtension();

            $fileName = "{$safeUsername}-{$timestamp}.{$extension}";

            $path = $file->storeAs('avatars', $fileName, 'public');

            $user->avatar = url('storage/' . $path);
        }

        $user->save();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user'  => $this->formatUserResponse($user),
            'token' => $token,
        ], 201);
    }

    /**
     * Valida las credenciales del usuario, autentica la sesión y genera
     * un token de acceso para la aplicación móvil.
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Las credenciales introducidas son incorrectas.'
            ], 401);
        }

        $token = $user->createToken('mobile_auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => '¡Inicio de sesión exitoso!',
            'user'    => $this->formatUserResponse($user),
            'token'   => $token
        ], 200);
    }

    /**
     * Cierra la sesión del usuario autenticado eliminando el token de acceso actual.
     * @return JsonResponse
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada correctamente. Token eliminado.'
        ], 200);
    }

    /**
     * Formatea los datos del usuario para la respuesta JSON.
     * @param User $user El objeto de modelo del usuario.
     * @return array La estructura de datos formateada para el cliente.
     */
    private function formatUserResponse(User $user)
    {
        return [
            'id'       => $user->id,
            'username' => $user->username,
            'email'    => $user->email,
            'avatar'   => $user->avatar,
            'rating'   => $user->rating,
            'municipality_id' => $user->municipality_id,
        ];
    }
    /**
     * Actualiza el perfil del usuario autenticado, permitiendo modificar
     * información personal, cambiar la contraseña y reemplazar el avatar.
     * * @param Request $request
     * @return JsonResponse
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'username'        => 'nullable|string|max:255|unique:users,username,' . $user->id,
            'email'           => 'nullable|string|email|max:255|unique:users,email,' . $user->id,
            'municipality_id' => 'nullable|exists:municipalities,id',
            'avatar'          => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'old_password'    => 'required_with:new_password',
            'new_password'    => 'nullable|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        if ($request->filled('username')) {
            $user->username = $request->username;
        }
        if ($request->filled('email')) {
            $user->email = $request->email;
        }
        if ($request->filled('municipality_id')) {
            $user->municipality_id = $request->municipality_id;
        }

        if ($request->filled('new_password')) {
            if (!Hash::check($request->old_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'errors' => ['old_password' => ['La contraseña antigua no coincide.']]
                ], 422);
            }
            $user->password = Hash::make($request->new_password);
        }

        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');

            $safeUsername = Str::slug($user->username ?? 'avatar');

            $timestamp = time();

            $extension = $file->getClientOriginalExtension();

            $fileName = "{$safeUsername}-{$timestamp}.{$extension}";

            if ($user->avatar) {
                $oldPath = str_replace(url('storage/'), '', $user->avatar);
                Storage::disk('public')->delete($oldPath);
            }

            $path = $file->storeAs('avatars', $fileName, 'public');

            $user->avatar = url('storage/' . $path);
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Perfil actualizado correctamente.',
            'user'    => $this->formatUserResponse($user) // Usamos tu método unificado
        ], 200);
    }
}
