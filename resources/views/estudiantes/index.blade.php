@extends('layouts.app')
@section('title', 'Estudiantes')

@section('content')
    <nav class="crumb" aria-label="Ruta"><a href="{{ url('/dashboard') }}">Inicio</a> › <b>Estudiantes</b></nav>

    <div class="head">
        <div>
            <h1>Gestión de estudiantes</h1>
            <p>Registrar y administrar estudiantes que participarán en los exámenes.</p>
        </div>
        <button type="button" class="btn" id="btn-nuevo-estudiante">
            <svg class="i"><use href="#i-user-plus"/></svg> Nuevo estudiante
        </button>
    </div>

    @if (session('status')) <div class="flash" role="status">{{ session('status') }}</div> @endif

    {{-- Resumen --}}
    <section class="stats three">
        <div class="stat">
            <div><small>Matrícula total</small><strong>{{ number_format($stats['total']) }}</strong></div>
            <span class="ic"><svg class="i"><use href="#i-users"/></svg></span>
        </div>
        <div class="stat ok">
            <div><small>Habilitados / activos</small><strong>{{ number_format($stats['habilitados']) }}</strong></div>
            <span class="ic"><svg class="i"><use href="#i-check"/></svg></span>
        </div>
        <div class="stat off">
            <div><small>Inactivos / observados</small><strong>{{ number_format($stats['inactivos']) }}</strong></div>
            <span class="ic"><svg class="i"><use href="#i-info"/></svg></span>
        </div>
    </section>

    {{-- Filtros --}}
    <form class="toolbar" method="GET" action="{{ route('estudiantes.index') }}">
        <label class="search">
            <svg class="i"><use href="#i-search"/></svg>
            <input type="search" name="buscar" value="{{ request('buscar') }}"
                   placeholder="Buscar estudiante por código, CI o apellidos…" aria-label="Buscar estudiante">
        </label>
        <select name="carrera" aria-label="Carrera" onchange="this.form.submit()">
            <option value="">Carrera (todas)</option>
            @foreach ($carreras as $carrera)
                <option value="{{ $carrera }}" @selected(request('carrera') === $carrera)>{{ $carrera }}</option>
            @endforeach
        </select>
        <select name="estado" aria-label="Estado" onchange="this.form.submit()">
            <option value="">Estado (todos)</option>
            <option value="activo" @selected(request('estado') === 'activo')>Activo</option>
            <option value="observado" @selected(request('estado') === 'observado')>Observado</option>
            <option value="inactivo" @selected(request('estado') === 'inactivo')>Inactivo</option>
        </select>
    </form>

    {{-- Tabla --}}
    <section class="card" style="margin-top:16px">
        <div class="scroll">
            <table>
                <thead>
                    <tr>
                        <th>Código universitario</th><th>Nombre completo</th><th>CI / DNI</th>
                        <th>Carrera</th><th>Correo institucional</th><th>Estado</th>
                        <th style="text-align:right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($estudiantes as $e)
                    @php
                        $payload = $e->only(['codigo_universitario', 'documento_identidad', 'nombres', 'apellidos', 'carrera', 'correo_institucional', 'estado']);
                        $url = route('estudiantes.update', $e);
                    @endphp
                    <tr>
                        <td class="strong num">{{ $e->codigo_universitario }}</td>
                        <td class="strong">{{ $e->nombre_completo }}</td>
                        <td class="num">{{ $e->documento_identidad }}</td>
                        <td>{{ $e->carrera }}</td>
                        <td class="mail">{{ $e->correo_institucional }}</td>
                        <td><span class="badge {{ strtolower($e->estado) }}">{{ ucfirst(strtolower($e->estado)) }}</span></td>
                        <td>
                            <div class="actions">
                                <button type="button" class="icon-btn" title="Ver" aria-label="Ver"
                                        data-edit="{{ json_encode($payload) }}" data-url="{{ $url }}" data-id="{{ $e->getKey() }}" data-mode="ver">
                                    <svg class="i"><use href="#i-eye"/></svg></button>
                                <button type="button" class="icon-btn" title="Editar" aria-label="Editar"
                                        data-edit="{{ json_encode($payload) }}" data-url="{{ $url }}" data-id="{{ $e->getKey() }}">
                                    <svg class="i"><use href="#i-edit"/></svg></button>
                                <a class="icon-btn" href="{{ route('asignaciones.create', $e) }}" title="Asignar materia, grupo y docente" aria-label="Asignar materia, grupo y docente a {{ $e->nombre_completo }}">
                                    <svg class="i"><use href="#i-exams"/></svg></a>
                                <form method="POST" action="{{ $url }}"
                                      onsubmit="return confirm('¿Eliminar a este estudiante? Esta acción no se puede deshacer.')">
                                    @csrf @method('DELETE')
                                    <button class="icon-btn danger" type="submit" title="Eliminar" aria-label="Eliminar">
                                        <svg class="i"><use href="#i-trash"/></svg></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">No hay estudiantes con esos filtros.
                        <a href="{{ route('estudiantes.index') }}">Limpiar filtros</a></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="foot">
            <p>Mostrando {{ $estudiantes->firstItem() ?? 0 }}–{{ $estudiantes->lastItem() ?? 0 }} de {{ number_format($estudiantes->total()) }} registros</p>
            @if ($estudiantes->hasPages())
                @php $actual = $estudiantes->currentPage(); $ultima = $estudiantes->lastPage(); @endphp
                <nav class="pager" aria-label="Paginación">
                    @if ($estudiantes->onFirstPage()) <span class="disabled">‹</span>
                    @else <a href="{{ $estudiantes->previousPageUrl() }}" aria-label="Anterior">‹</a> @endif

                    @foreach (range(max(1, $actual - 2), min($ultima, $actual + 2)) as $p)
                        @if ($p === $actual) <span class="current" aria-current="page">{{ $p }}</span>
                        @else <a href="{{ $estudiantes->url($p) }}">{{ $p }}</a> @endif
                    @endforeach

                    @if ($estudiantes->hasMorePages()) <a href="{{ $estudiantes->nextPageUrl() }}" aria-label="Siguiente">›</a>
                    @else <span class="disabled">›</span> @endif
                </nav>
            @endif
        </div>
    </section>

    {{-- ================= MODAL: registrar / editar estudiante ================= --}}
    <dialog id="modal-estudiante">
        <form method="POST" action="{{ route('estudiantes.store') }}">
            @csrf
            <input type="hidden" name="_method" value="PUT" disabled>
            <input type="hidden" name="_form" value="estudiante">
            <input type="hidden" name="_editing" value="{{ old('_editing') }}">

            <div class="m-head">
                <span class="ic"><svg class="i"><use href="#i-user-plus"/></svg></span>
                <div><b data-title>Registrar estudiante</b><small>Datos del padrón académico</small></div>
                <button type="button" class="icon-btn x" aria-label="Cerrar" onclick="this.closest('dialog').close()"><svg class="i"><use href="#i-x"/></svg></button>
            </div>

            <div class="m-body">
                <div class="row2">
                    <div class="field">
                        <label for="e-codigo">Código universitario <span class="req">*</span></label>
                        <input id="e-codigo" name="codigo_universitario" value="{{ old('codigo_universitario') }}" placeholder="2024-00034" required>
                        @error('codigo_universitario')<p class="err">{{ $message }}</p>@enderror
                    </div>
                    <div class="field">
                        <label for="e-ci">CI / DNI <span class="req">*</span></label>
                        <input id="e-ci" name="documento_identidad" value="{{ old('documento_identidad') }}" placeholder="7123456" required>
                        @error('documento_identidad')<p class="err">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="row2">
                    <div class="field">
                        <label for="e-nombres">Nombres <span class="req">*</span></label>
                        <input id="e-nombres" name="nombres" value="{{ old('nombres') }}" placeholder="Gabriel Fernando" required>
                        @error('nombres')<p class="err">{{ $message }}</p>@enderror
                    </div>
                    <div class="field">
                        <label for="e-apellidos">Apellidos <span class="req">*</span></label>
                        <input id="e-apellidos" name="apellidos" value="{{ old('apellidos') }}" placeholder="Romero Silva" required>
                        @error('apellidos')<p class="err">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="field">
                    <label for="e-carrera">Carrera <span class="req">*</span></label>
                    <select id="e-carrera" name="carrera" required>
                        <option value="">Seleccione la carrera…</option>
                        @foreach ($carreras as $c)
                            <option value="{{ $c }}" @selected(old('carrera') === $c)>{{ $c }}</option>
                        @endforeach
                    </select>
                    @error('carrera')<p class="err">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="e-correo">Correo institucional</label>
                    <input id="e-correo" type="email" name="correo_institucional" value="{{ old('correo_institucional') }}" placeholder="estudiante@universidad.edu">
                    @error('correo_institucional')<p class="err">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label>Estado <span class="req">*</span></label>
                    <div class="radios three">
                        @foreach (['ACTIVO' => 'Activo', 'OBSERVADO' => 'Observado', 'INACTIVO' => 'Inactivo'] as $v => $t)
                            <label><input type="radio" name="estado" value="{{ $v }}" @checked(old('estado', 'ACTIVO') === $v)> {{ $t }}</label>
                        @endforeach
                    </div>
                    @error('estado')<p class="err">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="m-foot">
                <button type="button" class="btn ghost" onclick="this.closest('dialog').close()">Cancelar</button>
                <button type="submit" class="btn" data-submit><svg class="i sm"><use href="#i-check"/></svg> Guardar estudiante</button>
            </div>
        </form>
    </dialog>
@endsection

@push('scripts')
<script>
    const MODAL = 'modal-estudiante';
    document.getElementById('btn-nuevo-estudiante').addEventListener('click', () =>
        abrirForm(MODAL, { url: '{{ route('estudiantes.store') }}', method: 'POST', title: 'Registrar estudiante' }));

    document.querySelectorAll('[data-edit]').forEach(b => b.addEventListener('click', () => {
        const ver = b.dataset.mode === 'ver';
        abrirForm(MODAL, {
            url: b.dataset.url, method: 'PUT', editing: b.dataset.id, ro: ver,
            values: JSON.parse(b.dataset.edit), title: ver ? 'Detalle del estudiante' : 'Editar estudiante',
        });
    }));

    @if ($errors->any() && old('_form') === 'estudiante')
        abrirForm(MODAL, {
            keep: true,
            url: '{{ old('_editing') ? url('estudiantes/'.old('_editing')) : route('estudiantes.store') }}',
            method: '{{ old('_editing') ? 'PUT' : 'POST' }}',
            title: '{{ old('_editing') ? 'Editar estudiante' : 'Registrar estudiante' }}',
        });
    @endif
</script>
@endpush
