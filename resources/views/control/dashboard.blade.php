@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
    <nav class="crumb" aria-label="Ruta"><a href="{{ url('/dashboard') }}">Inicio</a> › <b>Dashboard</b></nav>
    <div class="head">
        <div>
            <h1>Panel de control</h1>
            <p>Control de acceso y verificación durante los exámenes.</p>
        </div>
    </div>
    <div class="banner">
        <span class="ic"><svg class="i"><use href="#i-info"/></svg></span>
        <div><b>Bienvenido, {{ auth()->user()->name }}</b>
            <small>Este módulo estará disponible en los próximos sprints.</small></div>
    </div>

    <div class="actions" style="margin-top: 1rem;">
        <a href="{{ route('verificacion.index') }}" class="btn btn-primary">Ir a verificación de ingreso</a>
    </div>
@endsection
