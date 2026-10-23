<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Portal del estudiante') · SCIEM</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/sciem.css') }}">
</head>
<body>
@include('partials.icons')

<header class="portal-top">
    <div class="in">
        <span class="logo">S</span>
        <div><b>SCIEM</b><small>Portal del estudiante</small></div>
        @isset($estudiante)
            <div class="quien">
                <span>{{ $estudiante->nombres }} {{ $estudiante->apellidos }}</span>
                <form method="POST" action="{{ route('estudiante.logout') }}">
                    @csrf
                    <button class="salir" type="submit">Salir</button>
                </form>
            </div>
        @endisset
    </div>
</header>

<main class="portal-main">
    @yield('content')
</main>
</body>
</html>
