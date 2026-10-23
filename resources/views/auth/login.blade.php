<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión · SCIEM</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/sciem.css') }}">
</head>
<body>
@include('partials.icons')

<div class="login-wrap">
    <form class="login" method="POST" action="{{ url('/login') }}" novalidate>
        @csrf

        <div class="lg">
            <span class="logo">S</span>
            <div><b>SCIEM</b><small>Control de Exámenes</small></div>
        </div>

        <h1>Sistema de Control de Exámenes</h1>
        <p class="lead">Acceso al sistema</p>

        @if (session('status'))
            <div class="status" role="status">
                <svg class="i sm"><use href="#i-check"/></svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <div class="notice">
            <svg class="i sm"><use href="#i-info"/></svg>
            <span>Ingrese con su cuenta institucional. Por seguridad, el acceso está restringido a personal no autorizado.</span>
        </div>

        <div class="field">
            <label for="email">Usuario / correo institucional</label>
            <div class="icon-in">
                <svg class="i"><use href="#i-mail"/></svg>
                <input id="email" type="email" name="email" value="{{ old('email') }}"
                       placeholder="usuario@universidad.edu" autocomplete="username" required autofocus>
            </div>
        </div>

        <div class="field">
            <label for="password">Contraseña</label>
            <div class="icon-in">
                <svg class="i"><use href="#i-lock"/></svg>
                <input id="password" type="password" name="password" placeholder="••••••••" autocomplete="current-password" required>
                <button type="button" class="toggle" aria-label="Mostrar contraseña"
                        onclick="const p=document.getElementById('password');p.type=p.type==='password'?'text':'password'">
                    <svg class="i sm"><use href="#i-eye"/></svg>
                </button>
            </div>
        </div>

        <div class="opts">
            <label><input type="checkbox" name="remember" value="1"> Recordarme</label>
            <a href="{{ route('password.request') }}">¿Olvidó su contraseña?</a>
        </div>

        <button class="btn block" type="submit">
            <svg class="i sm"><use href="#i-logout"/></svg> Iniciar sesión
        </button>

        <p style="text-align:center;margin-top:16px;font-size:13px">¿Eres estudiante? <a href="{{ route('estudiante.login') }}" style="color:var(--blue-700);font-weight:600">Entra al portal del estudiante</a></p>

    </form>

    <p class="login-foot">
        Proyecto ISW · Gestión 2026<br>
        SCIEM — Sistema de Control de Exámenes Masivos
    </p>
</div>

{{-- ================= MODAL: error de acceso ================= --}}
<dialog id="modal-login-error">
    <div class="m-head">
        <span class="ic" style="background:var(--danger-bg);color:var(--danger)"><svg class="i"><use href="#i-alert"/></svg></span>
        <div><b>No se pudo iniciar sesión</b><small>Verifique los datos e intente de nuevo</small></div>
        <button type="button" class="icon-btn x" aria-label="Cerrar" onclick="this.closest('dialog').close()"><svg class="i"><use href="#i-x"/></svg></button>
    </div>
    <div class="m-body">
        <p style="color:var(--text)">{{ $errors->first() }}</p>
    </div>
    <div class="m-foot">
        <button type="button" class="btn" onclick="this.closest('dialog').close()">Entendido</button>
    </div>
</dialog>

@if ($errors->any())
    <script>
        document.getElementById('modal-login-error').showModal();
    </script>
@endif
</body>
</html>
