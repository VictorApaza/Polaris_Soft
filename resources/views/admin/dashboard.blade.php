@extends('layouts.app')
@section('title', 'Inicio')

@section('content')
    <nav class="crumb" aria-label="Ruta"><b>Inicio</b></nav>

    <div class="head">
        <div>
            <h1>Panel del administrador</h1>
            <p>Resumen de la gestión académica y accesos a las tareas más frecuentes.</p>
        </div>
        <span class="chip"><span class="dot"></span> Periodo actual <b>Semestre {{ $periodo }}</b></span>
    </div>

    @include('partials.flash')

    <section class="stats">
        <div class="stat">
            <div><small>Estudiantes registrados</small><strong>{{ number_format($stats['estudiantes']) }}</strong></div>
            <span class="ic"><svg class="i"><use href="#i-students"/></svg></span>
        </div>
        <div class="stat">
            <div><small>Docentes registrados</small><strong>{{ number_format($stats['docentes']) }}</strong></div>
            <span class="ic"><svg class="i"><use href="#i-users"/></svg></span>
        </div>
        <div class="stat">
            <div><small>Usuarios del sistema</small><strong>{{ number_format($stats['usuarios']) }}</strong></div>
            <span class="ic"><svg class="i"><use href="#i-users"/></svg></span>
        </div>
        <div class="stat">
            <div><small>Exámenes próximos</small><strong>{{ number_format($stats['examenes']) }}</strong></div>
            <span class="ic"><svg class="i"><use href="#i-calendar"/></svg></span>
        </div>
    </section>

    <div class="section-title"><span><svg class="i sm"><use href="#i-arrow"/></svg> Tareas frecuentes</span></div>
    <section class="tareas-guia">
        <a href="{{ route('estudiantes.index') }}">
            <span class="ic"><svg class="i"><use href="#i-user-plus"/></svg></span>
            <div><b>Registrar estudiantes</b><small>Uno por uno o importando un archivo</small></div>
            <svg class="i go"><use href="#i-arrow"/></svg>
        </a>
        <a href="{{ route('docentes.index') }}">
            <span class="ic"><svg class="i"><use href="#i-users"/></svg></span>
            <div><b>Registrar docentes</b><small>Uno por uno o importando Excel / CSV</small></div>
            <svg class="i go"><use href="#i-arrow"/></svg>
        </a>
        <a href="{{ route('reportes.index') }}">
            <span class="ic"><svg class="i"><use href="#i-exams"/></svg></span>
            <div><b>Ver exámenes y reportes</b><small>Asistencia, ausentes y horarios</small></div>
            <svg class="i go"><use href="#i-arrow"/></svg>
        </a>
        <a href="{{ route('materias.index') }}">
            <span class="ic soft"><svg class="i"><use href="#i-book"/></svg></span>
            <div><b>Materias y grupos</b><small>Docente a cargo de cada grupo</small></div>
            <svg class="i go"><use href="#i-arrow"/></svg>
        </a>
        <a href="{{ route('usuarios.index') }}">
            <span class="ic soft"><svg class="i"><use href="#i-lock"/></svg></span>
            <div><b>Cuentas y roles</b><small>Solo el administrador cambia roles</small></div>
            <svg class="i go"><use href="#i-arrow"/></svg>
        </a>
        <a href="{{ route('transparencia.index') }}">
            <span class="ic soft"><svg class="i"><use href="#i-shield"/></svg></span>
            <div><b>Transparencia</b><small>Auditoría de ingresos y accesos</small></div>
            <svg class="i go"><use href="#i-arrow"/></svg>
        </a>
    </section>

    @include('partials.proximos-examenes')
@endsection
