<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Portal del estudiante · SCIEM</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/sciem.css') }}">
</head>
<body>
@include('partials.icons')

<div class="login-wrap">
    <form class="login" method="POST" action="{{ route('estudiante.login.enviar') }}" novalidate>
        @csrf

        <div class="lg">
            <span class="logo">S</span>
            <div><b>SCIEM</b><small>Portal del estudiante</small></div>
        </div>

        <h1>Portal del estudiante</h1>
        <p class="lead">Consulta tu aula y sube tu foto para el examen</p>

        <div class="notice">
            <svg class="i sm"><use href="#i-info"/></svg>
            <span>Entra con tu <b>código universitario</b> y tu <b>contraseña</b> (si aún no la cambiaste, es tu <b>cédula de identidad</b>). El portal se abre {{ config('sciem.portal_abre_horas') }} horas antes de tu examen.</span>
        </div>

        @if ($errors->any())
            <div class="flash err" role="alert" style="margin:0 0 14px">{{ $errors->first() }}</div>
        @endif

        <div class="field">
            <label for="codigo">Código universitario</label>
            <div class="icon-in">
                <svg class="i"><use href="#i-id"/></svg>
                <input id="codigo" type="text" name="codigo_universitario" value="{{ old('codigo_universitario') }}"
                       inputmode="numeric" autocomplete="off" placeholder="Ej. 202400123" required autofocus>
            </div>
        </div>

        <div class="field">
            <label for="ci">Contraseña <small style="font-weight:400">(o cédula de identidad)</small></label>
            <div class="icon-in">
                <svg class="i"><use href="#i-lock"/></svg>
                <input id="ci" type="password" name="documento_identidad" autocomplete="current-password" placeholder="Tu contraseña o tu cédula (sin complemento)" required>
            </div>
        </div>

        <button class="btn block" type="submit">
            <svg class="i sm"><use href="#i-logout"/></svg> Entrar
        </button>

        <p style="text-align:center;margin-top:16px;font-size:12px"><a href="{{ route('login') }}" style="color:var(--blue-700)">Soy personal (docente, control o administrador)</a></p>
    </form>

    <p class="login-foot">
        Proyecto ISW · Gestión 2026<br>
        SCIEM — Sistema de Control de Exámenes Masivos
    </p>
</div>
</body>
</html>
