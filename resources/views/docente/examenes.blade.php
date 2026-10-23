@extends('layouts.app')
@section('title', 'Exámenes')

@section('content')
    <nav class="crumb" aria-label="Ruta"><a href="{{ url('/dashboard') }}">Inicio</a> › <b>Exámenes</b></nav>

    <div class="head">
        <div>
            <h1>Habilitación de estudiantes</h1>
            <p>Elige un examen para habilitar o inhabilitar a sus estudiantes y registrar el motivo.</p>
        </div>
    </div>

    @include('partials.flash')

    <section class="card" style="margin-top:20px">
        <form class="toolbar two" method="GET" action="{{ route('docente.examenes') }}" style="margin:0;padding:14px 16px;align-items:center">
            <label class="search">
                <svg class="i"><use href="#i-search"/></svg>
                <input type="search" name="buscar" value="{{ request('buscar') }}" placeholder="Buscar por asignatura o carrera…" aria-label="Buscar examen">
            </label>
            <select name="estado" onchange="this.form.submit()" aria-label="Estado">
                <option value="">Todos los estados</option>
                @foreach (\App\Models\Examen::ESTADOS as $valor => $etiqueta)
                    <option value="{{ $valor }}" @selected(request('estado') === $valor)>{{ $etiqueta }}</option>
                @endforeach
            </select>
        </form>

        <div class="scroll">
            <table>
                <thead>
                    <tr><th>Asignatura</th><th>Carrera</th><th>Fecha y hora</th><th>Ambiente</th><th>Estado</th><th>Habilitados</th><th style="text-align:right">Acciones</th></tr>
                </thead>
                <tbody>
                @forelse ($examenes as $e)
                    @php $c = $conteos->get($e->id, collect()); @endphp
                    <tr>
                        <td class="strong">{{ $e->asignatura->nombre ?? $e->materia->nombre ?? '—' }}</td>
                        <td>{{ $e->carrera ?: '—' }}</td>
                        <td class="num">{{ $e->fecha->format('d/m/Y') }} {{ $e->hora }}</td>
                        <td>{{ $e->ambiente }}</td>
                        <td><span class="badge {{ $e->estado === 'finalizado' ? 'inactivo' : 'activo' }}">{{ \App\Models\Examen::ESTADOS[$e->estado] ?? $e->estado }}</span></td>
                        <td class="num">{{ $c->get('habilitado', 0) }} hab. · {{ $c->get('inhabilitado', 0) }} inhab.</td>
                        <td>
                            <div class="actions">
                                <a class="link-btn ok" href="{{ route('habilitaciones.index', $e) }}">Habilitar / inhabilitar</a>
                                <a class="link-btn" href="{{ route('examenes.fotos', $e) }}">Fotos</a>
                                <a class="link-btn" href="{{ route('reportes.show', $e) }}">Reporte</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">No hay exámenes registrados.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="foot"><span>{{ $examenes->total() }} examen(es)</span><span>{{ $examenes->links() }}</span></div>
    </section>
@endsection
