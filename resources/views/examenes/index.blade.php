@extends('layouts.app')
@section('title', 'Exámenes')

@section('content')
    <div class="head" style="align-items:center">
        <nav class="crumb" aria-label="Ruta">
            <a href="{{ url('/dashboard') }}">Inicio</a> › <b>Exámenes</b>
        </nav>

        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
            <span class="chip">
                <span class="dot"></span> Periodo académico vigente
            </span>

            <button type="button" class="btn" id="btn-nuevo-examen">
                <svg class="i"><use href="#i-plus"/></svg>
                Nuevo examen
            </button>
        </div>
    </div>

    @if (session('status'))
        <div class="flash" role="status">
            {{ session('status') }}
        </div>
    @endif

    <section class="hero">
        <div>
            <span class="kicker">Módulo de administración</span>
            <h1>Gestión de exámenes</h1>

            <p>
                Registrar y administrar los exámenes programados,
                normas de admisión y directrices de control de acceso al aula.
            </p>
        </div>

        <div class="mini-stats">
            <div>
                <small>Fechas programadas</small>
                <strong>{{ $stats['programados'] }}</strong>
                <span class="muted" style="font-size:11px">activas</span>
            </div>

            <div>
                <small>Ambientes en uso</small>
                <strong class="g">
                    {{ str_pad($stats['ambientes'], 2, '0', STR_PAD_LEFT) }}
                </strong>
                <span class="muted" style="font-size:11px">aulas</span>
            </div>
        </div>
    </section>

    <form class="toolbar"
          method="GET"
          action="{{ route('examenes.index') }}"
          style="margin-top:16px">

        <label class="search">
            <svg class="i"><use href="#i-search"/></svg>

            <input
                type="search"
                name="buscar"
                value="{{ request('buscar') }}"
                placeholder="Buscar asignatura, código o carrera…"
                aria-label="Buscar examen">
        </label>

        <select name="estado"
                aria-label="Estado"
                onchange="this.form.submit()">

            <option value="">Todos los estados</option>

            @foreach (\App\Models\Examen::ESTADOS as $v => $t)
                <option value="{{ $v }}"
                    @selected(request('estado') === $v)>
                    {{ $t }}
                </option>
            @endforeach
        </select>

        <select name="ambiente"
                aria-label="Ambiente"
                onchange="this.form.submit()">

            <option value="">Todos los ambientes</option>

            @foreach ($ambientes as $a)
                <option value="{{ $a }}"
                    @selected(request('ambiente') === $a)>
                    {{ $a }}
                </option>
            @endforeach
        </select>
    </form>

    <section class="card" style="margin-top:16px">

        <div class="scroll">

            <table>

                <thead>
                    <tr>
                        <th>Asignatura / Carrera</th>
                        <th>Fecha</th>
                        <th>Hora inicio</th>
                        <th>Duración</th>
                        <th>Ambiente / Aula</th>
                        <th>Capacidad / Asign.</th>
                        <th>Estado</th>
                        <th style="text-align:right">Acciones</th>
                    </tr>
                </thead>

                <tbody>

                @forelse ($examenes as $e)

                    @php
                        $payload = [
                            'materia_id' => $e->materia_id,
                            'carrera' => $e->carrera,
                            'fecha' => $e->fecha->format('Y-m-d'),
                            'hora_inicio' => $e->hora_inicio,
                            'duracion_min' => $e->duracion_min,
                            'ambiente' => $e->ambiente,
                            'capacidad' => $e->capacidad,
                            'estado' => $e->estado,
                            'normas_admision' => $e->normas_admision,
                            'normas_salida' => $e->normas_salida,
                        ];

                        $url = route('examenes.update', $e);

                        $asig = $e->asignados;

                        $pct = $e->capacidad > 0
                            ? min(100, round($asig * 100 / $e->capacidad))
                            : 0;
                    @endphp

                    <tr>

                        <td class="strong">

                            {{ $e->materia->nombre ?? '—' }}

                            <span class="sub">
                                {{ $e->materia?->sigla }}

                                {{ $e->carrera ? ' · '.$e->carrera : '' }}
                            </span>

                        </td>

                        <td class="num">
                            {{ $e->fecha->format('d/m/Y') }}
                        </td>

                        <td class="num">
                            {{ $e->hora_inicio }}
                        </td>

                        <td class="num">
                            {{ $e->duracion_min }} min
                        </td>

                        <td>
                            <span class="place">
                                <svg class="i sm">
                                    <use href="#i-building"/>
                                </svg>

                                {{ $e->ambiente }}
                            </span>
                        </td>

                        <td style="min-width:150px">

                            <div class="cap">
                                {{ $asig }}
                                <span>/ {{ $e->capacidad }}</span>
                            </div>

                            <div class="bar">
                                <i class="{{ $e->estado === 'abierto' ? 'g' : '' }}"
                                   style="width:{{ $pct }}%">
                                </i>
                            </div>

                        </td>

                        <td>

                            <span class="badge {{ $e->estado }}">
                                {{ \App\Models\Examen::ESTADOS[$e->estado] ?? $e->estado }}
                            </span>

                        </td>

                        <td>

                            <div class="actions">

                                <button
                                    type="button"
                                    class="icon-btn"
                                    title="Ver"
                                    aria-label="Ver"
                                    data-edit="{{ json_encode($payload) }}"
                                    data-url="{{ $url }}"
                                    data-id="{{ $e->id }}"
                                    data-mode="ver">

                                    <svg class="i">
                                        <use href="#i-eye"/>
                                    </svg>

                                </button>

                                <button
                                    type="button"
                                    class="icon-btn"
                                    title="Editar"
                                    aria-label="Editar"
                                    data-edit="{{ json_encode($payload) }}"
                                    data-url="{{ $url }}"
                                    data-id="{{ $e->id }}"
                                    @disabled($e->estado === 'finalizado')>

                                    <svg class="i">
                                        <use href="#i-edit"/>
                                    </svg>

                                </button>

                                <form method="POST" action="{{ $url }}">

                                    @csrf
                                    @method('DELETE')

                                    <button
                                        class="icon-btn danger"
                                        type="button"
                                        title="Eliminar"
                                        aria-label="Eliminar"
                                        onclick="confirmarEliminar(
                                            this.closest('form'),
                                            '¿Eliminar este examen? Esta acción no se puede deshacer.'
                                        )">

                                        <svg class="i">
                                            <use href="#i-trash"/>
                                        </svg>

                                    </button>

                                </form>

                            </div>

                        </td>

                    </tr>

                @empty

                    <tr>
                        <td colspan="8" class="empty">

                            No hay exámenes con esos filtros.

                            <a href="{{ route('examenes.index') }}">
                                Limpiar filtros
                            </a>

                        </td>
                    </tr>

                @endforelse

                </tbody>

            </table>

        </div>

        <div class="foot">

            <p>
                Mostrando
                {{ $examenes->firstItem() ?? 0 }}–{{ $examenes->lastItem() ?? 0 }}
                de
                {{ $examenes->total() }}
                exámenes registrados
            </p>

            @if ($examenes->hasPages())

                @php
                    $actual = $examenes->currentPage();
                    $ultima = $examenes->lastPage();
                @endphp

                <nav class="pager" aria-label="Paginación">

                    @if ($examenes->onFirstPage())

                        <span class="disabled">‹</span>

                    @else

                        <a href="{{ $examenes->previousPageUrl() }}"
                           aria-label="Anterior">
                            ‹
                        </a>

                    @endif

                    @foreach (
                        range(
                            max(1, $actual - 2),
                            min($ultima, $actual + 2)
                        ) as $p
                    )

                        @if ($p === $actual)

                            <span class="current"
                                  aria-current="page">
                                {{ $p }}
                            </span>

                        @else

                            <a href="{{ $examenes->url($p) }}">
                                {{ $p }}
                            </a>

                        @endif

                    @endforeach

                    @if ($examenes->hasMorePages())

                        <a href="{{ $examenes->nextPageUrl() }}"
                           aria-label="Siguiente">
                            ›
                        </a>

                    @else

                        <span class="disabled">›</span>

                    @endif

                </nav>

            @endif

        </div>

    </section>

    <div class="protocol">

        <svg class="i">
            <use href="#i-shield"/>
        </svg>

        <div>
            <b>Protocolo de asignación automática</b>

            Al registrar un examen, el sistema vincula a los estudiantes
            inscritos en la asignatura según las asignaciones
            (materia, grupo y docente) configuradas, y valida la capacidad
            del ambiente.
        </div>

    </div>


    {{-- ==========================================================
         MODAL: REGISTRAR / EDITAR EXAMEN
         ========================================================== --}}

    <dialog id="modal-examen">

        <form method="POST"
              action="{{ route('examenes.store') }}">

            @csrf

            <input
                type="hidden"
                name="_method"
                value="PUT"
                disabled>

            <input
                type="hidden"
                name="_form"
                value="examen">

            <input
                type="hidden"
                name="_editing"
                value="{{ old('_editing') }}">

            <div class="m-head">

                <span class="ic">
                    <svg class="i">
                        <use href="#i-exams"/>
                    </svg>
                </span>

                <div>
                    <b data-title>Registrar examen</b>
                    <small>
                        Fecha / Programación de evaluación masiva
                    </small>
                </div>

                <button
                    type="button"
                    class="icon-btn x"
                    aria-label="Cerrar"
                    onclick="this.closest('dialog').close()">

                    <svg class="i">
                        <use href="#i-x"/>
                    </svg>

                </button>

            </div>

            <div class="m-body">

                <div class="row2">

                    <div class="field">

                        <label for="x-materia">
                            Asignatura <span class="req">*</span>
                        </label>

                        <select
                            id="x-materia"
                            name="materia_id"
                            required>

                            <option value="">
                                Seleccione asignatura…
                            </option>

                            @foreach ($materias as $m)

                                <option
                                    value="{{ $m->id }}"
                                    @selected(
                                        (string) old('materia_id')
                                        ===
                                        (string) $m->id
                                    )>

                                    {{ $m->nombre }}

                                    {{ $m->sigla ? ' ('.$m->sigla.')' : '' }}

                                </option>

                            @endforeach

                        </select>

                        @error('materia_id')
                            <p class="err">{{ $message }}</p>
                        @enderror

                    </div>

                    <div class="field">

                        <label for="x-carrera">
                            Carrera <i>Opcional</i>
                        </label>

                        <input
                            id="x-carrera"
                            name="carrera"
                            value="{{ old('carrera') }}"
                            placeholder="Ingeniería de Sistemas"
                            pattern="[\p{L}\s]+"
                            title="Solo letras"
                            oninput="soloLetras(this)">

                        @error('carrera')
                            <p class="err">{{ $message }}</p>
                        @enderror

                    </div>

                </div>


                <div class="row2">

                    <div class="field">

                        <label for="x-fecha">
                            Fecha <span class="req">*</span>
                        </label>

                        <input
                            id="x-fecha"
                            type="date"
                            name="fecha"
                            value="{{ old('fecha') }}"
                            required>

                        @error('fecha')
                            <p class="err">{{ $message }}</p>
                        @enderror

                    </div>


                    <div class="field">

                        <label for="x-hora">
                            Hora de inicio <span class="req">*</span>
                        </label>

                        <input
                            id="x-hora"
                            type="time"
                            name="hora_inicio"
                            value="{{ old('hora_inicio') }}"
                            required>

                        @error('hora_inicio')
                            <p class="err">{{ $message }}</p>
                        @enderror

                    </div>

                </div>


                <div class="row2">

                    <div class="field">

                        <label for="x-dur">
                            Duración (minutos)
                            <span class="req">*</span>
                        </label>

                        <input
                            id="x-dur"
                            type="number"
                            name="duracion_min"
                            min="15"
                            max="480"
                            step="5"
                            value="{{ old('duracion_min', 90) }}"
                            data-default="90"
                            required>

                        @error('duracion_min')
                            <p class="err">{{ $message }}</p>
                        @enderror

                    </div>


                    <div class="field">

                        <label for="x-amb">
                            Ambiente / Aula
                            <span class="req">*</span>
                        </label>

                        <input
                            id="x-amb"
                            name="ambiente"
                            value="{{ old('ambiente') }}"
                            placeholder="Edificio Central 102A"
                            required>

                        @error('ambiente')
                            <p class="err">{{ $message }}</p>
                        @enderror

                    </div>

                </div>


                <div class="row2">

                    <div class="field">

                        <label for="x-cap">
                            Capacidad del ambiente
                            <span class="req">*</span>
                        </label>

                        <input
                            id="x-cap"
                            type="number"
                            name="capacidad"
                            min="1"
                            max="5000"
                            value="{{ old('capacidad') }}"
                            placeholder="120"
                            required>

                        @error('capacidad')
                            <p class="err">{{ $message }}</p>
                        @enderror

                    </div>


                    <div class="field">

                        <label for="x-estado">
                            Estado <span class="req">*</span>
                        </label>

                        <select
                            id="x-estado"
                            name="estado"
                            data-default="programado"
                            required>

                            @foreach (\App\Models\Examen::ESTADOS as $v => $t)

                                <option
                                    value="{{ $v }}"
                                    @selected(
                                        old('estado', 'programado') === $v
                                    )>

                                    {{ $t }}

                                </option>

                            @endforeach

                        </select>

                        @error('estado')
                            <p class="err">{{ $message }}</p>
                        @enderror

                    </div>

                </div>


                <div class="field">

                    <label for="x-adm">
                        Normas generales de admisión
                        <i>Por defecto</i>
                    </label>

                    <textarea
                        id="x-adm"
                        name="normas_admision"
                        rows="3"
                        placeholder="Presentar CI físico original y Credencial Universitaria vigente 15 minutos antes de la hora señalada.">{{ old('normas_admision') }}</textarea>

                    @error('normas_admision')
                        <p class="err">{{ $message }}</p>
                    @enderror

                </div>


                <div class="field">

                    <label for="x-sal">
                        Normas particulares de salida
                        <i>Opcional</i>
                    </label>

                    <textarea
                        id="x-sal"
                        name="normas_salida"
                        rows="3"
                        placeholder="Se permite el uso de calculadora científica no programable. Prohibido el uso de celulares.">{{ old('normas_salida') }}</textarea>

                    @error('normas_salida')
                        <p class="err">{{ $message }}</p>
                    @enderror

                </div>

            </div>


            <div class="m-foot">

                <button
                    type="button"
                    class="btn ghost"
                    onclick="this.closest('dialog').close()">

                    Cancelar

                </button>

                <button
                    type="submit"
                    class="btn"
                    data-submit>

                    <svg class="i sm">
                        <use href="#i-check"/>
                    </svg>

                    Guardar examen

                </button>

            </div>

        </form>

    </dialog>

@endsection


@push('scripts')

<script>

    const MODAL = 'modal-examen';


    /*
    |--------------------------------------------------------------------------
    | NUEVO EXAMEN
    |--------------------------------------------------------------------------
    */

    document
        .getElementById('btn-nuevo-examen')
        .addEventListener('click', () => {

            abrirForm(MODAL, {
                url: '{{ route('examenes.store') }}',
                method: 'POST',
                title: 'Registrar examen'
            });

        });


    /*
    |--------------------------------------------------------------------------
    | VER / EDITAR EXAMEN
    |--------------------------------------------------------------------------
    */

    document
        .querySelectorAll('[data-edit]')
        .forEach(b => {

            b.addEventListener('click', () => {

                const ver = b.dataset.mode === 'ver';

                abrirForm(MODAL, {

                    url: b.dataset.url,

                    method: 'PUT',

                    editing: b.dataset.id,

                    ro: ver,

                    values: JSON.parse(b.dataset.edit),

                    title: ver
                        ? 'Detalle del examen'
                        : 'Editar examen',

                });

            });

        });


    /*
    |--------------------------------------------------------------------------
    | VOLVER A ABRIR EL MODAL SI EXISTEN ERRORES
    |--------------------------------------------------------------------------
    */

    @if ($errors->any() && old('_form') === 'examen')

        abrirForm(MODAL, {

            keep: true,

            url: '{{ old('_editing')
                ? url('examenes/'.old('_editing'))
                : route('examenes.store') }}',

            method: '{{ old('_editing') ? 'PUT' : 'POST' }}',

            title: '{{ old('_editing')
                ? 'Editar examen'
                : 'Registrar examen' }}',

        });

    @endif


    /*
    |--------------------------------------------------------------------------
    | PREVENIR DOBLE CLIC EN "GUARDAR EXAMEN"
    |--------------------------------------------------------------------------
    |
    | Cuando el usuario hace clic una vez:
    |
    |   1. Se deshabilita el botón.
    |   2. Cambia el texto a "Guardando...".
    |   3. Se evita que vuelva a enviar el formulario.
    |
    | IMPORTANTE:
    | Esto solamente evita el doble clic accidental en el navegador.
    | La protección real contra duplicados debe estar también en Laravel
    | y en la base de datos mediante UNIQUE.
    |
    */

    const formularioExamen = document.querySelector('#modal-examen form');

    if (formularioExamen) {

        formularioExamen.addEventListener('submit', function (event) {

            const botonGuardar = this.querySelector('[data-submit]');

            if (!botonGuardar) {
                return;
            }

            /*
             * Si ya fue enviado anteriormente, detenemos
             * cualquier segundo intento.
             */
            if (this.dataset.enviando === 'true') {

                event.preventDefault();

                return;
            }

            /*
             * Marcamos el formulario como enviado.
             */
            this.dataset.enviando = 'true';

            /*
             * Deshabilitamos el botón para evitar
             * múltiples clics.
             */
            botonGuardar.disabled = true;

            /*
             * Cambiamos visualmente el contenido.
             */
            botonGuardar.innerHTML = `
                <svg class="i sm">
                    <use href="#i-check"/>
                </svg>
                Guardando...
            `;

        });

    }

</script>

@endpush

