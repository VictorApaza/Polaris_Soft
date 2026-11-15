@extends('layouts.app')
@section('title', 'Inicio')

@section('content')
    <nav class="crumb" aria-label="Ruta"><b>Inicio</b></nav>

    <div class="head">
        <div>
            <h1>Panel del personal de control</h1>
            <p>Todo lo que necesitas el día del examen: verificar, ver quién falta y registrar situaciones.</p>
        </div>
        <span class="chip"><span class="dot"></span> Periodo actual <b>Semestre {{ $periodo }}</b></span>
    </div>

    @include('partials.flash')

    <section class="stats three">
        <div class="stat"><div><small>Exámenes de hoy</small><strong>{{ $stats['hoy'] }}</strong></div><span class="ic"><svg class="i"><use href="#i-calendar"/></svg></span></div>
        <div class="stat"><div><small>Exámenes próximos</small><strong>{{ $stats['examenes'] }}</strong></div><span class="ic"><svg class="i"><use href="#i-exams"/></svg></span></div>
        <div class="stat"><div><small>Periodo</small><strong style="font-size:24px">{{ $periodo }}</strong></div><span class="ic"><svg class="i"><use href="#i-info"/></svg></span></div>
    </section>

    <div class="section-title"><span><svg class="i sm"><use href="#i-arrow"/></svg> Tareas frecuentes</span></div>
    <section class="tareas-guia">
        <a href="{{ route('verificacion.index') }}">
            <span class="ic"><svg class="i"><use href="#i-check"/></svg></span>
            <div><b>Verificar ingreso</b><small>Por código, cédula o QR con cámara</small></div>
            <svg class="i go"><use href="#i-arrow"/></svg>
        </a>
        <a href="{{ route('en-vivo.index') }}">
            <span class="ic"><svg class="i"><use href="#i-eye"/></svg></span>
            <div><b>Control en vivo</b><small>Ingresaron, pendientes, expulsados y situaciones</small></div>
            <svg class="i go"><use href="#i-arrow"/></svg>
        </a>
        <a href="{{ route('transparencia.index') }}">
            <span class="ic soft"><svg class="i"><use href="#i-shield"/></svg></span>
            <div><b>Transparencia</b><small>Registro de las autorizaciones emitidas</small></div>
            <svg class="i go"><use href="#i-arrow"/></svg>
        </a>
    </section>

    @include('partials.proximos-examenes')
@endsection
