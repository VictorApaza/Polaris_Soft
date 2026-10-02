<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UsuarioController extends Controller
{
    public function index(Request $request)
    {
        $usuarios = User::query()
            ->when($request->filled('buscar'), fn ($q) => $q->where(function ($w) use ($request) {
                $w->where('name', 'like', "%{$request->buscar}%")
                  ->orWhere('email', 'like', "%{$request->buscar}%");
            }))
            ->orderBy('name')
            ->get();

        return view('usuarios.index', ['usuarios' => $usuarios, 'roles' => User::ROLES]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => [
                'required', 'email', 'max:150',
                ...($request->input('rol') === 'docente' ? ['regex:/^[^@\s]+@umss\.edu$/i'] : []),
                'unique:users,email',
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'rol' => ['required', Rule::in(array_keys(User::ROLES))],
            'estado' => ['required', Rule::in(['activo', 'inactivo'])],
        ], $this->mensajes());

        User::create($data); // el cast 'hashed' del modelo cifra la contraseña

        return redirect()->route('usuarios.index')->with('status', 'Usuario creado correctamente.');
    }

    public function update(Request $request, User $usuario)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => [
                'required', 'email', 'max:150',
                ...($request->input('rol') === 'docente' ? ['regex:/^[^@\s]+@umss\.edu$/i'] : []),
                Rule::unique('users', 'email')->ignore($usuario->id),
            ],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'rol' => ['required', Rule::in(array_keys(User::ROLES))],
            'estado' => ['required', Rule::in(['activo', 'inactivo'])],
        ], $this->mensajes());

        if (empty($data['password'])) {
            unset($data['password']); // no cambiar la contraseña si se deja vacía
        }

        if ($usuario->is($request->user()) && ($data['estado'] !== 'activo' || $data['rol'] !== 'administrador')) {
            return back()->withInput()->withErrors([
                'estado' => 'No puedes desactivar ni quitarte el rol de administrador a ti mismo.',
            ]);
        }

        $usuario->update($data);

        return redirect()->route('usuarios.index')->with('status', 'Usuario actualizado correctamente.');
    }

    /** Desactivar / reactivar cuenta. */
    public function cambiarEstado(Request $request, User $usuario)
    {
        if ($usuario->is($request->user())) {
            return back()->with('error', 'No puedes desactivar tu propia cuenta.');
        }

        $usuario->update(['estado' => $usuario->estado === 'activo' ? 'inactivo' : 'activo']);

        return back()->with('status', $usuario->estado === 'activo' ? 'Usuario reactivado.' : 'Usuario desactivado.');
    }

    private function mensajes(): array
    {
        return [
            'email.unique' => 'Ya existe un usuario con ese correo.',
            'email.regex' => 'El correo de los docentes debe terminar en @umss.edu.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ];
    }
}
