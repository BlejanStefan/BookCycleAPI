<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


class AuthController extends Controller
{
    /**
     * Registra un nuevo usuario y le inicia sesión automáticamente.
     */
    public function register(Request $request)
    {
        // 1. Validamos todos los campos del formulario
        $validator = Validator::make($request->all(), [
            'username'        => 'required|string|max:255|unique:users,username',
            'email'           => 'required|string|email|max:255|unique:users,email',
            'password'        => 'required|string|min:6',
            'municipality_id' => 'required|exists:municipalities,id',
            'avatar'          => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Max 2MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // 2. Gestión de la foto de Avatar
        $avatarUrl = null;
        if ($request->hasFile('avatar')) {
            // Guarda la foto en: storage/app/public/avatars/
            $path = $request->file('avatar')->store('avatars', 'public');

            // Genera la URL pública completa dinámica basada en tu APP_URL del .env
            $avatarUrl = asset('storage/' . $path);
        }

        // 3. Crear el registro en la tabla USERS
        $user = User::create([
            'username'        => $request->username,
            'email'           => $request->email,
            'password'        => Hash::make($request->password),
            'municipality_id' => $request->municipality_id,
            'avatar'          => $avatarUrl,
            'rating'          => 5.0,
        ]);

        // 4. DELEGACIÓN AUTOMÁTICA: Generamos token y respuesta usando el formato unificado
        $token = $user->createToken('mobile_auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Usuario registrado e inicio de sesión exitoso.',
            'user'    => $this->formatUserResponse($user), // 👈 Mismos datos que el login
            'token'   => $token
        ], 201);
    }

    /**
     * Inicio de sesión tradicional.
     */
    public function login(Request $request)
    {
        // 1. Validar los datos de entrada
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

        // 2. Buscar al usuario por email
        $user = User::where('email', $request->email)->first();

        // 3. Verificar si el usuario existe y la contraseña es correcta
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Las credenciales introducidas son incorrectas.'
            ], 401);
        }

        // 4. Crear el token de Sanctum
        $token = $user->createToken('mobile_auth_token')->plainTextToken;

        // 5. Responder unificando el formato
        return response()->json([
            'success' => true,
            'message' => '¡Inicio de sesión exitoso!',
            'user'    => $this->formatUserResponse($user), // 👈 ¡Ahora sí viaja el avatar aquí!
            'token'   => $token
        ], 200);
    }

    /**
     * Cierra la sesión revocando el token actual.
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
     * 🛠️ Método privado helper para estructurar el perfil del usuario.
     * Así garantizas que cualquier cambio en los campos afecte a Login y Register por igual.
     */
    private function formatUserResponse(User $user)
    {
        return [
            'id'       => $user->id,
            'username' => $user->username,
            'email'    => $user->email,
            'avatar'   => $user->avatar, // 📸 Destino resuelto para tu componente de React Native
            'rating'   => $user->rating,
        ];
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user(); // Obtenemos el usuario autenticado por el Token

        // 1. Validar dinámicamente los datos de entrada
        $validator = Validator::make($request->all(), [
            'username'        => 'nullable|string|max:255|unique:users,username,' . $user->id,
            'email'           => 'nullable|string|email|max:255|unique:users,email,' . $user->id,
            'municipality_id' => 'nullable|exists:municipalities,id',
            'avatar'          => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'old_password'    => 'required_with:new_password', // Si hay nueva, la antigua es obligatoria
            'new_password'    => 'nullable|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // 2. Actualizar campos básicos si se han enviado
        if ($request->filled('username')) {
            $user->username = $request->username;
        }
        if ($request->filled('email')) {
            $user->email = $request->email;
        }
        if ($request->filled('municipality_id')) {
            $user->municipality_id = $request->municipality_id;
        }

        // 3. Verificar y actualizar contraseña si se solicita
        if ($request->filled('new_password')) {
            if (!Hash::check($request->old_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'errors' => ['old_password' => ['La contraseña antigua no coincide.']]
                ], 422);
            }
            $user->password = Hash::make($request->new_password);
        }

        // 4. Gestión del nuevo Avatar (y eliminación del antiguo si existía)
        if ($request->hasFile('avatar')) {
            // 1. Conseguir el archivo subido
            $file = $request->file('avatar');

            // 2. Limpiar el username (eliminar espacios, acentos y pasarlo a minúsculas)
            // Ejemplo: "Loki 123" se convierte en "loki-123"
            $safeUsername = Str::slug($user->username ?? 'avatar');

            // 3. Obtener el timestamp actual (ejemplo: 1716915600)
            $timestamp = time();

            // 4. Obtener la extensión original del archivo (png, jpg, webp, etc.)
            $extension = $file->getClientOriginalExtension();

            // 5. Construir el nombre formal: loki-123-1716915600.png
            $fileName = "{$safeUsername}-{$timestamp}.{$extension}";

            // 6. [Opcional] Borrar el avatar antiguo para no acumular basura en el servidor
            if ($user->avatar) {
                // Extrae el nombre del archivo de la URL guardada si es necesario
                $oldPath = str_replace(url('storage/'), '', $user->avatar);
                Storage::disk('public')->delete($oldPath);
            }

            // 7. Guardar el archivo en la carpeta 'avatars' dentro del disco público con el nuevo nombre
            $path = $file->storeAs('avatars', $fileName, 'public');

            // 8. Actualizar la URL completa del avatar en el objeto del usuario
            $user->avatar = url('storage/' . $path);
        }

        // 5. Guardar los cambios en la base de datos
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Perfil actualizado correctamente.',
            'user'    => $this->formatUserResponse($user) // Usamos tu método unificado
        ], 200);
    }
}
