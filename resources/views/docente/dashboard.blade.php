@extends('layouts.app')
@section('title', 'Inicio')

@section('content')
    <nav class="crumb" aria-label="Ruta"><b>Inicio</b></nav>

    <div class="head">
        <div>
            <h1>Panel del docente</h1>
            <p>Bienvenido, {{ auth()->user()->name }}. Aquí tienes tus materias y lo que necesitas para tus exámenes.</p>
        </div>
        <span class="chip"><span class="dot"></span> Periodo actual <b>Semestre {{ $periodo }}</b></span>
    </div>

    @include('partials.flash')

    @unless ($docente)
        <div class="banner">
            <span class="ic"><svg class="i"><use href="#i-info"/></svg></span>
            <div><b>Tu cuenta todavía no está vinculada a una ficha de docente</b>
                <small>Pídele al administrador que registre tu ficha con tu mismo nombre y apellido. Mientras tanto no verás materias ni exámenes.</small></div>
        </div>
    @endunless

    <section class="stats">
        <div class="stat"><div><small>Mis materias</small><strong>{{ $stats['materias'] }}</strong></div><span class="ic"><svg class="i"><use href="#i-book"/></svg></span></div>
        <div class="stat"><div><small>Mis grupos</small><strong>{{ $stats['grupos'] }}</strong></div><span class="ic"><svg class="i"><use href="#i-users"/></svg></span></div>
        <div class="stat"><div><small>Mis estudiantes</small><strong>{{ $stats['estudiantes'] }}</strong></div><span class="ic"><svg class="i"><use href="#i-students"/></svg></span></div>
        <div class="stat"><div><small>Exámenes próximos</small><strong>{{ $stats['examenes'] }}</strong></div><span class="ic"><svg class="i"><use href="#i-calendar"/></svg></span></div>
    </section>

    <div class="section-title"><span><svg class="i sm"><use href="#i-arrow"/></svg> Tareas frecuentes</span></div>
    <section class="tareas-guia">
        <a href="{{ route('docente.examenes') }}">
            <span class="ic"><svg class="i"><use href="#i-exams"/></svg></span>
            <div><b>Habilitar estudiantes</b><small>Elige el examen y quién puede rendirlo</small></div>
            <svg class="i go"><use href="#i-arrow"/></svg>
        </a>
        <a href="{{ route('estudiantes.importar.form') }}">
            <span class="ic"><svg class="i"><use href="#i-user-plus"/></svg></span>
            <div><b>Importar mis estudiantes</b><small>Con la plantilla CSV o un Excel</small></div>
            <svg class="i go"><use href="#i-arrow"/></svg>
        </a>
        <a href="{{ route('en-vivo.index') }}">
            <span class="ic soft"><svg class="i"><use href="#i-eye"/></svg></span>
            <div><b>Seguimiento en vivo</b><small>Quién ya entró y quién falta (parte de Reportes)</small></div>
            <svg class="i go"><use href="#i-arrow"/></svg>
        </a>
        <a href="{{ route('fotos.cargar') }}">
            <span class="ic soft"><svg class="i"><use href="#i-id"/></svg></span>
            <div><b>Cargar fotos</b><small>Una por una o en un ZIP</small></div>
            <svg class="i go"><use href="#i-arrow"/></svg>
        </a>
        <a href="{{ route('reportes.index') }}">
            <span class="ic soft"><svg class="i"><use href="#i-dashboard"/></svg></span>
            <div><b>Reportes e incidencias</b><small>Resultados y faltas de cada examen</small></div>
            <svg class="i go"><use href="#i-arrow"/></svg>
        </a>
    </section>

    @include('partials.proximos-examenes')
@endsection
