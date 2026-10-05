@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
    <nav class="crumb" aria-label="Ruta"><a href="{{ url('/dashboard') }}">Inicio</a> › <b>Dashboard</b></nav>

    <div class="head">
        <div>
            <h1>Panel principal</h1>
            <p>Resumen de la gestión académica.</p>
        </div>
        <span class="chip"><span class="dot"></span> Periodo actual <b>Semestre {{ $periodo }}</b></span>
    </div>

    <section class="stats">
        <div class="stat">
            <div><small>Estudiantes registrados</small><strong>{{ number_format($stats['estudiantes']) }}</strong><em class="green">● Habilitados</em></div>
            <span class="ic"><svg class="i"><use href="#i-students"/></svg></span>
        </div>
        <div class="stat">
            <div><small>Docentes registrados</small><strong>{{ number_format($stats['docentes']) }}</strong><em>Semestre {{ $periodo }}</em></div>
            <span class="ic"><svg class="i"><use href="#i-users"/></svg></span>
        </div>
        <div class="stat">
            <div><small>Usuarios del sistema</small><strong>{{ number_format($stats['usuarios']) }}</strong><em>3 roles activos</em></div>
            <span class="ic"><svg class="i"><use href="#i-users"/></svg></span>
        </div>
        <div class="stat">
            <div><small>Exámenes próximos</small><strong>{{ number_format($stats['examenes']) }}</strong><em class="blue">● Programados</em></div>
            <span class="ic"><svg class="i"><use href="#i-calendar"/></svg></span>
        </div>
    </section>

    <div class="section-title">
        <span><svg class="i sm"><use href="#i-arrow"/></svg> Accesos rápidos</span>
        <a href="#">Operaciones frecuentes del Sprint 1</a>
    </div>
    <section class="quick">
        <a href="{{ route('estudiantes.index') }}">
            <span class="ic"><svg class="i"><use href="#i-user-plus"/></svg></span>
            <div><b>+ Registrar estudiante</b><small>Incorporar al padrón académico</small></div>
            <svg class="i go"><use href="#i-arrow"/></svg>
        </a>
        <a href="{{ route('examenes.index') }}">
            <span class="ic"><svg class="i"><use href="#i-exams"/></svg></span>
            <div><b>+ Registrar examen</b><small>Programar fecha, aula y normativa</small></div>
            <svg class="i go"><use href="#i-arrow"/></svg>
        </a>
        <a href="{{ route('usuarios.index') }}">
            <span class="ic soft"><svg class="i"><use href="#i-users"/></svg></span>
            <div><b>Administrar usuarios</b><small>Control de roles, accesos y permisos</small></div>
            <svg class="i go"><use href="#i-arrow"/></svg>
        </a>
    </section>

    <div class="section-title">
        <span><svg class="i sm"><use href="#i-exams"/></svg> Próximos exámenes</span>
        <a href="{{ route('examenes.index') }}">Ver cronograma completo ›</a>
    </div>
    <section class="card">
        <div class="scroll">
            <table>
                <thead>
                    <tr><th>Asignatura</th><th>Fecha</th><th>Hora</th><th>Ambiente</th><th>Estado</th><th style="text-align:right">Acción</th></tr>
                </thead>
                <tbody>
                @forelse ($proximos as $e)
                    <tr>
                        <td class="strong"><span class="with-dot">{{ $e->asignatura->nombre ?? $e->materia->nombre ?? '—' }}</span>
                            <span class="sub" style="margin-left:16px">{{ $e->carrera }}{{ $e->asignatura?->codigo ? ' · Código '.$e->asignatura->codigo : ($e->materia?->sigla ? ' · Código '.$e->materia->sigla : '') }}</span></td>
                        <td class="num">{{ $e->fecha->format('d/m/Y') }}</td>
                        <td class="num">{{ $e->hora }}</td>
                        <td><span class="place"><svg class="i sm"><use href="#i-building"/></svg>{{ $e->ambiente }}</span></td>
                        <td><span class="badge {{ $e->estado }}">{{ \App\Models\Examen::ESTADOS[$e->estado] ?? $e->estado }}</span></td>
                        <td style="text-align:right"><a class="link-btn" href="{{ route('examenes.index', ['buscar' => $e->asignatura->nombre ?? $e->materia->nombre ?? '']) }}">Detalle ›</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">No hay exámenes programados. <a href="{{ route('examenes.index') }}">Registrar un examen</a></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="foot">
            <span>Mostrando los {{ $proximos->count() }} exámenes programados más próximos</span>
            <span class="okline"><svg class="i sm"><use href="#i-check"/></svg> Sincronizado con base de datos central</span>
        </div>
    </section>
@endsection
