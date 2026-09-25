<?php

namespace App\Http\Controllers;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Http\Request;

class UsuarioController extends Controller
{
    public function index(Request $request)
    {
        $usuarios = Usuario::query()
            ->with('roles')
            ->buscar($request->string('buscar')->toString())
            ->when($request->filled('rol'), fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('rol.id_rol', $request->rol)))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', strtoupper($request->estado)))
            ->orderBy('apellidos')
            ->paginate(5)
            ->withQueryString();

        $stats = [
            'total'    => Usuario::count(),
            'activos'  => Usuario::where('estado', 'ACTIVO')->count(),
            'inactivos'=> Usuario::where('estado', '<>', 'ACTIVO')->count(),
        ];

        $roles = Rol::orderBy('nombre')->get();

        return view('usuarios.index', compact('usuarios', 'stats', 'roles'));
    }

    public function create()
    {
        $roles = Rol::orderBy('nombre')->get();

        return view('usuarios.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'usuario'   => ['required', 'string', 'max:50', 'unique:usuario,usuario'],
            'password'  => ['required', 'string', 'min:6'],
            'nombres'   => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'estado'    => ['required', 'in:ACTIVO,INACTIVO'],
            'roles'     => ['required', 'array', 'min:1'],
            'roles.*'   => ['exists:rol,id_rol'],
        ]);

        $usuario = Usuario::create([
            'usuario'       => $data['usuario'],
            'password_hash' => bcrypt($data['password']),
            'nombres'       => $data['nombres'],
            'apellidos'     => $data['apellidos'],
            'estado'        => $data['estado'],
        ]);

        $usuario->roles()->sync($data['roles']);

        return redirect()->route('usuarios.index')->with('status', 'Usuario creado.');
    }

    public function edit(Usuario $usuario)
    {
        $roles = Rol::orderBy('nombre')->get();
        $usuario->load('roles');

        return view('usuarios.edit', compact('usuario', 'roles'));
    }

    public function update(Request $request, Usuario $usuario)
    {
        $data = $request->validate([
            'usuario'   => ['required', 'string', 'max:50', 'unique:usuario,usuario,' . $usuario->id_usuario . ',id_usuario'],
            'password'  => ['nullable', 'string', 'min:6'],
            'nombres'   => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'estado'    => ['required', 'in:ACTIVO,INACTIVO'],
            'roles'     => ['required', 'array', 'min:1'],
            'roles.*'   => ['exists:rol,id_rol'],
        ]);

        $usuario->fill([
            'usuario'   => $data['usuario'],
            'nombres'   => $data['nombres'],
            'apellidos' => $data['apellidos'],
            'estado'    => $data['estado'],
        ]);

        if (!empty($data['password'])) {
            $usuario->password_hash = bcrypt($data['password']);
        }

        $usuario->save();
        $usuario->roles()->sync($data['roles']);

        return redirect()->route('usuarios.index')->with('status', 'Usuario actualizado.');
    }

    public function destroy(Usuario $usuario)
    {
        $usuario->roles()->detach();
        $usuario->delete();

        return redirect()->route('usuarios.index')->with('status', 'Usuario eliminado.');
    }
}