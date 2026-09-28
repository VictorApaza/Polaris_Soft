<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $datos = $request->validate([
            'email' => [
                'required',
                'email',
            ],

            'password' => [
                'required',
                'string',
            ],
        ]);

        $usuario = User::with('rol')
            ->where('email', $datos['email'])
            ->first();

        if (!$usuario || !Hash::check(
            $datos['password'],
            $usuario->password
        )) {
            return response()->json([
                'message' => 'Correo o contraseña incorrectos.'
            ], 401);
        }

        if (!$usuario->activo ?? false) {
            return response()->json([
                'message' => 'El usuario se encuentra inactivo.'
            ], 403);
        }

        $token = $usuario->createToken(
            'polaris-app'
        )->plainTextToken;

        return response()->json([
            'message' => 'Inicio de sesión exitoso.',

            'token' => $token,

            'user' => [
                'id' => $usuario->id,
                'name' => $usuario->name,
                'email' => $usuario->email,

                'rol' => [
                    'id' => $usuario->rol->id,
                    'nombre' => $usuario->rol->nombre,
                ],

                'estudiante_id' => $usuario->estudiante_id,
                'docente_id' => $usuario->docente_id,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()
            ->currentAccessToken()
            ->delete();

        return response()->json([
            'message' => 'Sesión cerrada correctamente.'
        ]);
    }

    public function me(Request $request)
    {
        $usuario = $request->user()->load('rol');

        return response()->json([
            'id' => $usuario->id,
            'name' => $usuario->name,
            'email' => $usuario->email,

            'rol' => [
                'id' => $usuario->rol->id,
                'nombre' => $usuario->rol->nombre,
            ],

            'estudiante_id' => $usuario->estudiante_id,
            'docente_id' => $usuario->docente_id,
        ]);
    }
}