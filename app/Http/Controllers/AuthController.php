<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /** Máximo de intentos fallidos antes de bloquear temporalmente el acceso. */
    private const MAX_INTENTOS = 3;

    /** Minutos que dura el bloqueo tras agotar los intentos. */
    private const MINUTOS_BLOQUEO = 1;

    /**
     * Muestra el formulario de login.
     */
    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * Procesa el intento de login.
     * Espera del formulario: email, password, remember (checkbox opcional).
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $key = $this->claveIntentos($request);

        // --- Límite de intentos fallidos ---
        if (RateLimiter::tooManyAttempts($key, self::MAX_INTENTOS)) {
            $segundos = RateLimiter::availableIn($key);
            $minutos = (int) ceil($segundos / 60);

            return back()->withErrors([
                'email' => "Demasiados intentos fallidos. Intente nuevamente en {$minutos} minuto(s).",
            ])->onlyInput('email');
        }

        $usuario = User::where('email', $credentials['email'])->first();

        // --- Mensaje específico: usuario no existe ---
        if (! $usuario) {
            RateLimiter::hit($key, self::MINUTOS_BLOQUEO * 60);

            return back()->withErrors([
                'email' => 'No existe ninguna cuenta registrada con ese usuario.',
            ])->onlyInput('email');
        }

        // --- Mensaje específico: cuenta inactiva ---
        if ($usuario->estado !== 'activo') {
            RateLimiter::hit($key, self::MINUTOS_BLOQUEO * 60);

            return back()->withErrors([
                'email' => 'Esta cuenta está inactiva. Contacte a un administrador.',
            ])->onlyInput('email');
        }

        // --- Mensaje específico: contraseña incorrecta ---
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key, self::MINUTOS_BLOQUEO * 60);

            $restantes = self::MAX_INTENTOS - RateLimiter::attempts($key);
            $aviso = $restantes > 0
                ? "Contraseña incorrecta. Le queda(n) {$restantes} intento(s)."
                : 'Contraseña incorrecta.';

            return back()->withErrors(['password' => $aviso])->onlyInput('email');
        }

        // --- Login correcto ---
        RateLimiter::clear($key);
        $request->session()->regenerate();

        return match (Auth::user()->rol) {
            'administrador' => redirect()->intended('/admin/dashboard'),
            'docente' => redirect()->intended('/docente/dashboard'),
            default => redirect()->intended('/control/dashboard'),
        };
    }

    /**
     * Cierra la sesión del usuario autenticado.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    /** Clave única de bloqueo: por correo + IP, para no afectar a otros usuarios desde otra red. */
    private function claveIntentos(Request $request): string
    {
        return Str::lower($request->input('email')).'|'.$request->ip();
    }

    // conexion para postman
    public function loginApi(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $usuario = User::where('email', $credentials['email'])->first();

        if (
            ! $usuario ||
            $usuario->estado !== 'activo' ||
            ! Auth::validate($credentials)
        ) {
            return response()->json([
                'mensaje' => 'Credenciales incorrectas o cuenta inactiva.',
            ], 401);
        }

        if (! in_array($usuario->rol, ['administrador', 'docente'], true)) {
            return response()->json([
                'mensaje' => 'No tienes permiso para acceder a esta API.',
            ], 403);
        }

        $token = $usuario->createToken('postman')->plainTextToken;

        return response()->json([
            'mensaje' => 'Autenticación correcta.',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'usuario' => [
                'id' => $usuario->id,
                'nombre' => $usuario->name,
                'rol' => $usuario->rol,
            ],
        ]);
    }
}
