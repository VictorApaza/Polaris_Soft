@extends('layouts.app')
@section('title', 'Usuarios')

@section('content')
    <nav class="crumb" aria-label="Ruta"><a href="{{ url('/dashboard') }}">Inicio</a> › <b>Usuarios</b></nav>

    <div class="head">
        <div>
            <h1>Gestión de usuarios</h1>
            <p>Administrar usuarios y roles del sistema.</p>
        </div>
        <button type="button" class="btn" id="btn-nuevo-usuario"><svg class="i"><use href="#i-user-plus"/></svg> Nuevo usuario</button>
    </div>

    @if (session('status')) <div class="flash" role="status">{{ session('status') }}</div> @endif
    @if (session('error')) <div class="flash err" role="alert">{{ session('error') }}</div> @endif

    <div class="banner">
        <span class="ic"><svg class="i"><use href="#i-shield"/></svg></span>
        <div><b>Control de acceso basado en roles (RBAC) · Sprint 1</b>
            <small>Define qué módulos puede ver cada usuario según su rol: administrador, docente o personal de control.</small></div>
        <span class="right"><svg class="i sm"><use href="#i-check"/></svg> Layout RBAC activo</span>
    </div>

    <section class="card" style="margin-top:16px">
        <form class="toolbar two" method="GET" action="{{ route('usuarios.index') }}" style="margin:0;padding:14px 16px;align-items:center">
            <label class="search">
                <svg class="i"><use href="#i-search"/></svg>
                <input type="search" name="buscar" value="{{ request('buscar') }}" placeholder="Buscar por nombre o correo…" aria-label="Buscar usuario">
            </label>
            <span class="chip">Total registrados <b>{{ $usuarios->count() }} usuarios</b></span>
        </form>

        <div class="scroll">
            <table>
                <thead>
                    <tr><th>Nombre</th><th>Correo institucional</th><th>Rol</th><th>Estado</th><th style="text-align:right">Acciones</th></tr>
                </thead>
                <tbody>
                @forelse ($usuarios as $u)
                    @php
                        $payload = ['name' => $u->name, 'email' => $u->email, 'rol' => $u->rol, 'estado' => $u->estado];
                        $url = route('usuarios.update', $u);
                        $activo = $u->estado === 'activo';
                    @endphp
                    <tr @style(['opacity:.75' => ! $activo])>
                        <td>
                            <span class="place" style="gap:12px">
                                <span class="avatar" @style(['background:var(--soft);color:var(--muted)' => ! $activo])>{{ $u->iniciales }}</span>
                                <span><b>{{ $u->name }}</b><span class="sub">ID-{{ str_pad($u->id, 4, '0', STR_PAD_LEFT) }}</span></span>
                            </span>
                        </td>
                        <td class="mail">{{ $u->email }}</td>
                        <td><span class="tag {{ $u->rol }}">{{ $u->rol_etiqueta }}</span></td>
                        <td><span class="badge {{ $activo ? 'activo' : 'inactivo' }}">{{ $activo ? 'Activo' : 'Inactivo' }}</span></td>
                        <td>
                            <div class="actions">
                                <button type="button" class="link-btn" data-edit="{{ json_encode($payload) }}" data-url="{{ $url }}" data-id="{{ $u->id }}">Editar</button>
                                <button type="button" class="link-btn muted" data-edit="{{ json_encode($payload) }}" data-url="{{ $url }}" data-id="{{ $u->id }}" data-pass="1">Contraseña</button>
                                @if (! $u->is(auth()->user()))
                                    <form method="POST" action="{{ route('usuarios.estado', $u) }}">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="link-btn {{ $activo ? 'danger' : 'ok' }}">{{ $activo ? 'Desactivar' : 'Reactivar' }}</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">No se encontraron usuarios. <a href="{{ route('usuarios.index') }}">Limpiar búsqueda</a></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="foot">
            <span>Mostrando {{ $usuarios->count() }} usuarios registrados en el sistema</span>
            <span>Última sincronización: {{ now()->format('d/m/Y H:i') }}</span>
        </div>
    </section>

    {{-- ================= MODAL: registrar / editar usuario ================= --}}
    <dialog id="modal-usuario">
        <form method="POST" action="{{ route('usuarios.store') }}" autocomplete="off">
            @csrf
            <input type="hidden" name="_method" value="PUT" disabled>
            <input type="hidden" name="_form" value="usuario">
            <input type="hidden" name="_editing" value="{{ old('_editing') }}">

            <div class="m-head">
                <span class="ic"><svg class="i"><use href="#i-user-plus"/></svg></span>
                <div><b data-title>Registrar nuevo usuario</b><small>Credenciales de acceso</small></div>
                <button type="button" class="icon-btn x" aria-label="Cerrar" onclick="this.closest('dialog').close()"><svg class="i"><use href="#i-x"/></svg></button>
            </div>

            <div class="m-body">
                <div class="field">
                    <label for="u-name">Nombre completo <span class="req">*</span></label>
                    <input id="u-name" name="name" value="{{ old('name') }}" placeholder="Ej: Lic. Carlos Choque" required>
                    @error('name')<p class="err">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="u-email">Correo institucional <span class="req">*</span></label>
                    <input id="u-email" type="email" name="email" value="{{ old('email') }}" placeholder="usuario@umss.edu" required>
                    @error('email')<p class="err">{{ $message }}</p>@enderror
                </div>
                <div class="row2">
                    <div class="field">
                        <label for="u-password">Contraseña <span class="req">*</span></label>
                        <input id="u-password" type="password" name="password" placeholder="Mínimo 8 caracteres" autocomplete="new-password">
                        @error('password')<p class="err">{{ $message }}</p>@enderror
                    </div>
                    <div class="field">
                        <label for="u-password2">Confirmar contraseña <span class="req">*</span></label>
                        <input id="u-password2" type="password" name="password_confirmation" placeholder="Repita la contraseña" autocomplete="new-password">
                    </div>
                </div>
                <p class="muted" id="u-pass-hint" style="font-size:12px;margin-top:-6px" hidden>Deja la contraseña vacía si no quieres cambiarla.</p>
                <div class="field">
                    <label for="u-rol">Rol de usuario <span class="req">*</span></label>
                    <select id="u-rol" name="rol" data-default="docente" required>
                        @foreach ($roles as $v => $t)
                            <option value="{{ $v }}" @selected(old('rol', 'docente') === $v)>{{ $t }}</option>
                        @endforeach
                    </select>
                    @error('rol')<p class="err">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label>Estado del usuario <span class="req">*</span></label>
                    <div class="radios">
                        <label><input type="radio" name="estado" value="activo" @checked(old('estado', 'activo') === 'activo')> Activo</label>
                        <label><input type="radio" name="estado" value="inactivo" @checked(old('estado') === 'inactivo')> Inactivo</label>
                    </div>
                    @error('estado')<p class="err">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="m-foot">
                <button type="button" class="btn ghost" onclick="this.closest('dialog').close()">Cancelar</button>
                <button type="submit" class="btn" data-submit><svg class="i sm"><use href="#i-check"/></svg> <span data-submit-text>Crear usuario</span></button>
            </div>
        </form>
    </dialog>
@endsection

@push('scripts')
<script>
    const MODAL = 'modal-usuario';
    const hint = document.getElementById('u-pass-hint');
    const txt = document.querySelector('[data-submit-text]');
    const emailField = document.getElementById('u-email');
    const roleField = document.getElementById('u-rol');

    const actualizarPatronCorreo = () => {
        const esDocente = roleField.value === 'docente';
        emailField.placeholder = esDocente ? 'usuario@umss.edu' : 'usuario@universidad.edu';

        if (esDocente) {
            emailField.pattern = '[^@]+@umss\\.edu';
            emailField.title = 'Use un correo institucional @umss.edu.';
        } else {
            emailField.removeAttribute('pattern');
            emailField.removeAttribute('title');
        }
    };

    roleField.addEventListener('change', actualizarPatronCorreo);

    document.getElementById('btn-nuevo-usuario').addEventListener('click', () => {
        hint.hidden = true; txt.textContent = 'Crear usuario';
        abrirForm(MODAL, { url: '{{ route('usuarios.store') }}', method: 'POST', title: 'Registrar nuevo usuario' });
        actualizarPatronCorreo();
    });

    document.querySelectorAll('[data-edit]').forEach(b => b.addEventListener('click', () => {
        hint.hidden = false; txt.textContent = 'Guardar cambios';
        abrirForm(MODAL, {
            url: b.dataset.url, method: 'PUT', editing: b.dataset.id,
            values: JSON.parse(b.dataset.edit), title: b.dataset.pass ? 'Cambiar contraseña' : 'Editar usuario',
        });
        actualizarPatronCorreo();
        if (b.dataset.pass) document.getElementById('u-password').focus();
    }));

    @if ($errors->any() && old('_form') === 'usuario')
        hint.hidden = {{ old('_editing') ? 'false' : 'true' }};
        abrirForm(MODAL, {
            keep: true,
            url: '{{ old('_editing') ? url('usuarios/'.old('_editing')) : route('usuarios.store') }}',
            method: '{{ old('_editing') ? 'PUT' : 'POST' }}',
            title: '{{ old('_editing') ? 'Editar usuario' : 'Registrar nuevo usuario' }}',
        });
        actualizarPatronCorreo();
    @endif
</script>
@endpush
