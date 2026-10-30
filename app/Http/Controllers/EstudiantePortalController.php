<?php

namespace App\Http\Controllers;

use App\Models\AccesoLog;
use App\Models\Estudiante;
use App\Models\Habilitacion;
use App\Models\TransparenciaIngreso;
use App\Services\PortalEstudianteService;
use App\Services\QrEstudianteService;
use App\Services\ValidadorFotoRostro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

/**
 * Portal del estudiante: entra con su código universitario + su cédula de identidad.
 * Ve a qué aula va, su QR, y sube su foto actual dentro del plazo.
 */
class EstudiantePortalController extends Controller
{
    private const MAX_INTENTOS = 5;
    private const SEGUNDOS_BLOQUEO = 300;

    public function showLogin(Request $request)
    {
        if ($request->session()->has('estudiante_id')) {
            return redirect()->route('estudiante.panel');
        }

        return view('estudiante.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'codigo_universitario' => ['required', 'string', 'max:30'],
            'documento_identidad' => ['required', 'string', 'max:100'],
        ], [
            'codigo_universitario.required' => 'Ingresa tu código universitario.',
            'documento_identidad.required' => 'Ingresa tu contraseña (si aún no la cambiaste, es tu cédula de identidad).',
        ]);

        $codigo = trim($data['codigo_universitario']);
        $ci = trim($data['documento_identidad']);
        $clave = 'estudiante|'.$codigo.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($clave, self::MAX_INTENTOS)) {
            AccesoLog::anotar('ESTUDIANTE', $codigo, false, 'BLOQUEADO');
            $minutos = (int) ceil(RateLimiter::availableIn($clave) / 60);

            return back()->withErrors(['codigo_universitario' => "Demasiados intentos. Intenta de nuevo en {$minutos} minuto(s)."])->onlyInput('codigo_universitario');
        }

        // La contraseña es la cédula hasta que el estudiante la cambie desde su panel.
        $estudiante = Estudiante::where('codigo_universitario', $codigo)->first();
        if ($estudiante && ! $this->claveCorrecta($estudiante, $ci)) {
            $estudiante = null;
        }

        if (! $estudiante) {
            RateLimiter::hit($clave, self::SEGUNDOS_BLOQUEO);
            AccesoLog::anotar('ESTUDIANTE', $codigo, false, 'DATOS_INCORRECTOS');

            // Mensaje genérico: no se revela si el código existe.
            return back()->withErrors(['codigo_universitario' => 'El código universitario o la contraseña no coinciden.'])->onlyInput('codigo_universitario');
        }

        if (strtoupper((string) $estudiante->estado) === 'INACTIVO') {
            AccesoLog::anotar('ESTUDIANTE', $codigo, false, 'INACTIVO', $estudiante->getKey());

            return back()->withErrors(['codigo_universitario' => 'Tu cuenta no está activa. Consulta con la administración.'])->onlyInput('codigo_universitario');
        }

        RateLimiter::clear($clave);
        $request->session()->regenerate();
        $request->session()->put('estudiante_id', $estudiante->getKey());
        AccesoLog::anotar('ESTUDIANTE', $codigo, true, 'OK', $estudiante->getKey());

        return redirect()->route('estudiante.panel');
    }

    /** ¿Es correcta la clave? Con contraseña propia se compara el hash; si no, vale la cédula. */
    private function claveCorrecta(Estudiante $estudiante, string $clave): bool
    {
        if (filled($estudiante->password)) {
            return Hash::check($clave, $estudiante->password);
        }

        return hash_equals((string) $estudiante->documento_identidad, $clave);
    }

    /** El estudiante cambia su contraseña (la primera vez la actual es su cédula). */
    public function cambiarPassword(Request $request)
    {
        /** @var Estudiante $estudiante */
        $estudiante = $request->attributes->get('estudiante');

        $data = $request->validate([
            'password_actual' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'max:100', 'confirmed', 'different:password_actual'],
        ], [
            'password_actual.required' => 'Escribe tu contraseña actual (si nunca la cambiaste, es tu cédula).',
            'password.required' => 'Escribe la contraseña nueva.',
            'password.min' => 'La contraseña nueva debe tener al menos 8 caracteres.',
            'password.confirmed' => 'La confirmación no coincide con la contraseña nueva.',
            'password.different' => 'La contraseña nueva debe ser distinta de la actual.',
        ]);

        if (! $this->claveCorrecta($estudiante, $data['password_actual'])) {
            return back()->withErrors(['password_actual' => 'La contraseña actual no es correcta.'])->with('abrir_password', true);
        }

        $estudiante->forceFill([
            'password' => Hash::make($data['password']),
            'password_cambiada_at' => now(),
        ])->save();

        return back()->with('status', 'Tu contraseña se cambió correctamente. La próxima vez entra con la nueva.');
    }

    public function logout(Request $request)
    {
        $request->session()->forget('estudiante_id');
        $request->session()->regenerateToken();

        return redirect()->route('estudiante.login');
    }

    public function panel(Request $request, PortalEstudianteService $portal)
    {
        /** @var Estudiante $estudiante */
        $estudiante = $request->attributes->get('estudiante');
        $examenes = $portal->examenesDe($estudiante);

        $habilitaciones = Habilitacion::with('motivo')
            ->where('estudiante_id', $estudiante->getKey())
            ->whereIn('examen_id', $examenes->pluck('id'))
            ->get()->keyBy('examen_id');

        $ingresos = TransparenciaIngreso::where('estudiante_id', $estudiante->getKey())
            ->whereIn('examen_id', $examenes->pluck('id'))
            ->pluck('fecha_hora_ingreso', 'examen_id');

        $tarjetas = $examenes->map(function ($x) use ($portal, $habilitaciones, $ingresos) {
            $h = $habilitaciones->get($x->id);

            return [
                'examen' => $x,
                'abierto' => $portal->portalAbierto($x),
                'puede_foto' => $portal->puedeSubirFoto($x),
                'estado' => $h ? $h->estado : 'sin_evaluar',     // habilitado | inhabilitado | sin_evaluar
                'motivo' => $h?->motivo?->descripcion,
                'observacion' => $h?->observacion,
                'ingreso' => $ingresos->get($x->id),
            ];
        });

        return view('estudiante.panel', [
            'estudiante' => $estudiante,
            'tarjetas' => $tarjetas,
            'puedeSubirFoto' => (bool) $portal->examenQuePermiteFoto($examenes),
            'examenFoto' => $portal->examenQuePermiteFoto($examenes),
            'proximaApertura' => $examenes->first(fn ($x) => ! $portal->portalAbierto($x)),
        ]);
    }

    public function subirFoto(Request $request, PortalEstudianteService $portal)
    {
        /** @var Estudiante $estudiante */
        $estudiante = $request->attributes->get('estudiante');

        if (! $portal->examenQuePermiteFoto($portal->examenesDe($estudiante))) {
            return back()->with('error', 'Ahora no puedes subir tu foto: el plazo no está abierto o ya venció.');
        }

        $request->validate([
            'foto' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:'.config('sciem.foto_max_kb'), 'dimensions:min_width=200,min_height=200'],
        ], [
            'foto.required' => 'Elige una foto.',
            'foto.image' => 'El archivo debe ser una imagen.',
            'foto.mimes' => 'La foto debe ser JPG o PNG.',
            'foto.max' => 'La foto pesa demasiado (máximo '.(config('sciem.foto_max_kb') / 1024).' MB).',
            'foto.dimensions' => 'La foto es muy pequeña: mínimo 200 x 200 píxeles.',
        ]);

        $archivo = $request->file('foto');

        // Revisión automática: si la foto está borrosa, oscura, con lentes oscuros o sin rostro visible, no se guarda.
        $revision = app(ValidadorFotoRostro::class)->validar($archivo->getRealPath());
        if (! $revision['ok']) {
            return back()->with('error', $revision['motivo']);
        }

        $nombre = $estudiante->getKey().'_'.time().'.'.$archivo->extension();
        $ruta = $archivo->storeAs('fotos_estudiantes', $nombre, 'local');

        if ($estudiante->foto_path) {
            Storage::disk('local')->delete($estudiante->foto_path);
        }

        $estudiante->forceFill([
            'foto_path' => $ruta,
            'foto_estado' => 'PENDIENTE',
            'foto_observacion' => null,
            'foto_subida_at' => now(),
            'foto_revisada_at' => null,
            'foto_revisada_por' => null,
        ])->save();

        return back()->with('status', 'Tu foto se subió correctamente. Un docente la revisará.');
    }

    /** El estudiante ve su propia foto. */
    public function fotoPropia(Request $request)
    {
        /** @var Estudiante $estudiante */
        $estudiante = $request->attributes->get('estudiante');

        abort_unless($estudiante->foto_path && Storage::disk('local')->exists($estudiante->foto_path), 404);

        return Storage::disk('local')->response($estudiante->foto_path, null, ['Cache-Control' => 'private, max-age=3600']);
    }

    /** Su código QR (solo si el portal de un examen suyo está abierto y está habilitado). */
    public function qr(Request $request, PortalEstudianteService $portal, QrEstudianteService $qr)
    {
        /** @var Estudiante $estudiante */
        $estudiante = $request->attributes->get('estudiante');
        $examenes = $portal->examenesDe($estudiante)->filter(fn ($x) => $portal->portalAbierto($x));

        $habilitado = Habilitacion::where('estudiante_id', $estudiante->getKey())
            ->whereIn('examen_id', $examenes->pluck('id'))
            ->where('estado', 'habilitado')->exists();

        if (! $habilitado) {
            return redirect()->route('estudiante.panel')->with('error', 'Tu código QR estará disponible cuando estés habilitado y se abra el portal de tu examen.');
        }

        return view('estudiantes.qr', [
            'estudiante' => $estudiante,
            'token' => $qr->generarToken($estudiante),
            'publico' => true,
            'volverPanel' => route('estudiante.panel'),
        ]);
    }
}
