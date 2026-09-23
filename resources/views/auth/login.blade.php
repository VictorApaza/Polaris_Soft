@extends('layouts.auth')

@section('title', 'Iniciar sesión')

@section('content')
    <main class="login-shell">
        <section class="login-panel" aria-labelledby="login-title">
            <div class="login-card">
                <div class="brand-mark" aria-hidden="true">
                    <span class="brand-shield">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 19 6v5.5c0 4.5-3 7.7-7 9.5-4-1.8-7-5-7-9.5V6l7-3Z"/><path d="m9 12 2 2 4-4"/></svg>
                    </span>
                    <span>
                        <strong>SCIEM</strong>
                        <small>CONTROL DE EXÁMENES</small>
                    </span>
                </div>

                <header class="login-heading">
                    <h1 id="login-title">Sistema de Control de Exámenes</h1>
                    <p>Acceso al sistema</p>
                </header>

                @if ($errors->any())
                    <div class="alert" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                <div class="security-note">
                    <svg class="note-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 19 6v5.5c0 4.5-3 7.7-7 9.5-4-1.8-7-5-7-9.5V6l7-3Z"/><path d="m9 12 2 2 4-4"/></svg>
                    <span>Autenticación segura para coordinadores, auditores de aula y personal de verificación de acceso.</span>
                </div>

                <form class="login-form" method="POST" action="{{ url('/login') }}">
                    @csrf
                    <div class="field-group">
                        <label for="email">Usuario o correo institucional</label>
                        <div class="input-wrap">
                            <svg class="input-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c.7-3.4 3.1-5.2 7-5.2s6.3 1.8 7 5.2"/></svg>
                            <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="usuario@universidad.edu" autocomplete="username" required autofocus>
                        </div>
                    </div>

                    <div class="field-group">
                        <label for="password">Contraseña</label>
                        <div class="input-wrap">
                            <svg class="input-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                            <input id="password" name="password" type="password" placeholder="••••••••••••" autocomplete="current-password" required>
                            <button class="password-toggle" type="button" data-password-toggle aria-label="Mostrar contraseña">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.2-5 9.5-5 9.5 5 9.5 5-3.2 5-9.5 5-9.5-5-9.5-5Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="form-options">
                        <label class="remember-option">
                            <input type="checkbox" name="remember" value="1">
                            <span>Recordarme</span>
                        </label>
                        <a href="{{ url('/forgot-password') }}">¿Olvidaste tu contraseña?</a>
                    </div>

                    <button class="submit-button" type="submit">
                        <span aria-hidden="true">&#10132;</span>
                        Iniciar sesión
                    </button>
                </form>
            </div>

            <p class="institution">Universidad Mayor de San Simon · Facultad de Ingeniería de Sistemas</p>
            <p class="version">SCIEM V1.0 · CONTROL Y VALIDACIÓN BIOMÉTRICA DE CONVOCATORIAS</p>
        </section>
    </main>
@endsection
