@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
    <nav class="crumb" aria-label="Ruta"><a href="{{ url('/dashboard') }}">Inicio</a> › <b>Dashboard</b></nav>
    <div class="head">
        <div>
            <h1>Panel del docente</h1>
            <p>Consulta de tus materias, grupos y estudiantes asignados.</p>
        </div>
    </div>
    <div class="banner">
        <span class="ic"><svg class="i"><use href="#i-info"/></svg></span>
        <div><b>Bienvenido, {{ auth()->user()->name }}</b>
            <small>Desde aquí puedes habilitar o inhabilitar estudiantes para cada examen.</small></div>
    </div>
    <p style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap">
        <a class="btn" href="{{ route('docente.examenes') }}">Habilitar estudiantes y revisar fotos</a>
        <a class="btn ghost" href="{{ route('buscador.index') }}">Buscar estudiantes</a>
        <a class="btn ghost" href="{{ route('reportes.index') }}">Reportes</a>
    </p>
@endsection
