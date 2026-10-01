<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'SCIEM') · SCIEM</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/sciem.css') }}">
    @stack('styles')
</head>
<body>
@include('partials.icons')

@php
    $u = auth()->user();
    $es = fn (string ...$roles) => in_array($u->rol, $roles, true);
@endphp

<div class="app">
    {{-- ================= SIDEBAR ================= --}}
    <aside class="sidebar">
        <div class="brand">
            <span class="logo">S</span>
            <div><b>SCIEM</b><small>Control de Exámenes</small></div>
        </div>

        <nav class="nav" aria-label="Módulos principales">
            <h3>Módulos principales</h3>
            <a href="{{ url('/dashboard') }}" @class(['active' => request()->is('*dashboard*')])>
                <svg class="i"><use href="#i-dashboard"/></svg> Dashboard</a>
            @if ($es('administrador'))
                <a href="{{ route('estudiantes.index') }}" @class(['active' => request()->routeIs('estudiantes.*', 'asignaciones.*')])>
                    <svg class="i"><use href="#i-students"/></svg> Estudiantes</a>
                <a href="{{ route('examenes.index') }}" @class(['active' => request()->routeIs('examenes.*')])>
                    <svg class="i"><use href="#i-exams"/></svg> Exámenes</a>
                <a href="{{ route('materias.index') }}" @class(['active' => request()->routeIs('materias.*')])>
                    <svg class="i"><use href="#i-exams"/></svg> Materias</a>
                <a href="{{ route('docentes.index') }}" @class(['active' => request()->routeIs('docentes.*')])>
                    <svg class="i"><use href="#i-users"/></svg> Docentes</a>
                <a href="{{ route('grupos.index') }}" @class(['active' => request()->routeIs('grupos.*')])>
                    <svg class="i"><use href="#i-dashboard"/></svg> Grupos</a>
                <a href="{{ route('usuarios.index') }}" @class(['active' => request()->routeIs('usuarios.*')])>
                    <svg class="i"><use href="#i-users"/></svg> Usuarios</a>
            @endif
        </nav>

        <div class="side-foot">
            <b>Versión Sprint 1.0</b><br>
            Proyecto ISW · Gestión 2026
        </div>
    </aside>

    <div>
        {{-- ================= TOPBAR ================= --}}
        <header class="topbar">
            <h2>SCIEM <span class="muted" style="font-weight:500">– Sistema de Control de Exámenes Masivos</span></h2>
            <div class="user">
                <span class="avatar">{{ $u->iniciales }}</span>
                <div class="who">
                    <b>{{ $u->name }}</b>
                    <span class="role">{{ $u->rol_etiqueta }}</span>
                </div>
                <span class="sep"></span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="logout" type="submit"><svg class="i"><use href="#i-logout"/></svg> Cerrar sesión</button>
                </form>
            </div>
        </header>

        <main>
            @yield('content')
        </main>
    </div>
</div>

<script src="{{ asset('js/sciem.js') }}"></script>
@stack('scripts')
</body>
</html>
