{{-- Encabezado común de los reportes de un examen (Sprint 5): ruta, título, descargas y navegación entre reportes. --}}
@php
    $consulta = request()->except(['formato', 'page']);
    $reportes = [
        'reportes.asistencia' => 'Asistencia',
        'reportes.ausentes' => 'Ausentes',
        'reportes.horarios' => 'Horarios de ingreso',
        'reportes.intentos' => 'Intentos indebidos',
    ];
@endphp
<nav class="crumb" aria-label="Ruta">
    <a href="{{ url('/dashboard') }}">Inicio</a> › <a href="{{ route('reportes.index') }}">Reportes</a> ›
    <a href="{{ route('reportes.show', $examen) }}">{{ $examen->nombre }}</a> › <b>{{ $titulo }}</b>
</nav>

<div class="head">
    <div>
        <h1>{{ $titulo }}</h1>
        <p>{{ $descripcion }}</p>
        <p style="margin-top:2px">{{ $examen->nombre }} · {{ $examen->carrera }} · {{ $examen->fecha->format('d/m/Y') }} {{ $examen->hora }} · Aula {{ $examen->ambiente }}</p>
    </div>
    <div class="head-actions no-print">
        <a class="btn" href="{{ route($ruta, [$examen] + $consulta + ['formato' => 'pdf']) }}"><svg class="i sm"><use href="#i-download"/></svg> Descargar PDF</a>
        <a class="btn ghost" href="{{ route($ruta, [$examen] + $consulta + ['formato' => 'xlsx']) }}"><svg class="i sm"><use href="#i-download"/></svg> Descargar Excel</a>
    </div>
</div>

<div class="chips no-print" aria-label="Reportes del examen">
    @foreach ($reportes as $nombre => $etiqueta)
        <a class="chip {{ $ruta === $nombre ? 'activo' : '' }}" href="{{ route($nombre, $examen) }}">{{ $etiqueta }}</a>
    @endforeach
    <a class="chip" href="{{ route('reportes.show', $examen) }}">Reporte general</a>
    <a class="chip" href="{{ route('en-vivo.examen', $examen) }}">Control en vivo</a>
</div>

@include('partials.flash')
