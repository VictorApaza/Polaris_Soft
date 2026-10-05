@extends('layouts.app')
@section('title', 'Asignación académica')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/asignacion.css') }}">
@endpush

@section('content')
<div class="asignacion-page">
    <nav class="crumb" aria-label="Ruta">
        <a href="{{ url('/dashboard') }}">Inicio</a> ›
        <a href="{{ route('estudiantes.index') }}">Estudiantes</a> › <b>Asignación académica</b>
    </nav>

    <div class="head">
        <div>
            <h1>Asignar materia, grupo y docente</h1>
            <p>Selecciona la materia y su grupo para asignar el docente correspondiente.</p>
        </div>
    </div>

    @include('partials.flash')

    <section class="card asignacion-card" aria-label="Formulario de asignación">
        <div class="m-head">
            <span class="ic"><svg class="i" aria-hidden="true"><use href="#i-students"/></svg></span>
            <div>
                <b>{{ $estudiante->nombre_completo }}</b>
                <small>Código universitario: {{ $estudiante->codigo_universitario }}</small>
            </div>
        </div>

        <div id="asignacion-aviso" class="aviso" role="status" aria-live="polite"></div>

        <form id="asignacion-form" method="post" action="{{ route('asignaciones.store', $estudiante) }}" novalidate
              data-api-url="{{ url('/api') }}" data-estudiante-id="{{ $estudiante->getKey() }}"
              data-delete-url-template="{{ route('asignaciones.destroy', '__ASIGNACION__') }}">
            @csrf
            <div class="asignacion-campos">
                <div class="field">
                    <label for="materia">Materia</label>
                    <select id="materia" name="materia_id" required disabled aria-describedby="materia-help">
                        <option value="">Selecciona una materia</option>
                    </select>
                    <p class="help" id="materia-help">Cargando materias…</p>
                </div>

                <div class="row2">
                    <div class="field">
                        <label for="grupo">Grupo</label>
                        <select id="grupo" name="grupo_id" required disabled aria-describedby="grupo-help">
                            <option value="">Selecciona un grupo</option>
                        </select>
                        <p class="help" id="grupo-help">Elige primero una materia.</p>
                    </div>

                    <div class="field">
                        <label for="docente">Docente</label>
                        <select id="docente" name="docente_id" required disabled aria-describedby="docente-help">
                            <option value="">Selecciona un docente</option>
                        </select>
                        <p class="help" id="docente-help">Elige primero un grupo.</p>
                    </div>
                </div>
            </div>

            <div class="m-foot">
                <a class="btn ghost" href="{{ route('estudiantes.index') }}">Cancelar</a>
                <button class="btn" type="submit" disabled>Guardar asignación</button>
            </div>
        </form>
    </section>

    <section class="card asignacion-lista" aria-label="Asignaciones actuales">
        <div class="m-head"><b>Asignaciones actuales</b></div>
        <div class="scroll">
            <table id="asignaciones-tabla">
                <thead><tr><th>Materia</th><th>Grupo</th><th>Docente</th><th style="text-align:right">Acción</th></tr></thead>
                <tbody id="asignaciones-filas">
                @forelse ($asignaciones as $asignacion)
                    <tr data-asignacion-id="{{ $asignacion->id }}">
                        <td class="strong">{{ $asignacion->materia->nombre ?? '—' }}</td>
                        <td>{{ $asignacion->grupo->nombre ?? '—' }}</td>
                        <td>{{ $asignacion->docente->nombre_completo ?? 'Sin docente' }}</td>
                        <td>
                            <div class="actions">
                                <form method="POST" action="{{ route('asignaciones.destroy', $asignacion) }}">
                                    @csrf @method('DELETE')
                                    <button class="icon-btn danger" type="button" title="Quitar" aria-label="Quitar {{ $asignacion->materia->nombre ?? 'asignación' }}"
                                            onclick="confirmarEliminar(this.closest('form'), '¿Quitar esta asignación?')">
                                        <svg class="i"><use href="#i-trash"/></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr id="asignaciones-vacio"><td colspan="4" class="empty">Este estudiante aún no tiene materias asignadas.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="foot"><span id="asignaciones-total">{{ $asignaciones->count() }} materias asignadas</span></div>
    </section>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/asignacion-api.js') }}"></script>
<script src="{{ asset('js/asignacion.js') }}"></script>
@endpush
