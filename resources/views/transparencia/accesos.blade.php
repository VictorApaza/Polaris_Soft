@extends('layouts.app')
@section('title', 'Accesos al sistema')

@section('content')
    @php $zona = config('sciem.zona_horaria'); @endphp

    <nav class="crumb" aria-label="Ruta">
        <a href="{{ url('/dashboard') }}">Inicio</a> › <a href="{{ route('transparencia.index') }}">Transparencia</a> › <b>Accesos al sistema</b>
    </nav>

    <div class="head">
        <div>
            <h1>Accesos al sistema</h1>
            <p>Quién entró correctamente y quién falló al iniciar sesión (contraseña incorrecta, cuenta inexistente o bloqueada). Nunca se guarda la contraseña escrita.</p>
        </div>
        <a class="btn ghost" href="{{ route('transparencia.index') }}">‹ Transparencia de ingreso</a>
    </div>

    <section class="stats three">
        <div class="stat ok"><div><small>Ingresos correctos (24 h)</small><strong>{{ $totalExitosos24h }}</strong></div><span class="ic"><svg class="i"><use href="#i-check"/></svg></span></div>
        <div class="stat"><div><small>Intentos fallidos (24 h)</small><strong style="{{ $totalFallidos24h ? 'color:var(--danger)' : '' }}">{{ $totalFallidos24h }}</strong></div><span class="ic" style="background:var(--danger-bg);color:var(--danger)"><svg class="i"><use href="#i-alert"/></svg></span></div>
        <div class="stat"><div><small>Registros mostrados</small><strong>{{ $registros->total() }}</strong></div><span class="ic"><svg class="i"><use href="#i-shield"/></svg></span></div>
    </section>

    <form class="toolbar" method="GET" action="{{ route('transparencia.accesos') }}" style="margin-top:16px;padding:14px 16px;background:#fff;border:1px solid var(--line);border-radius:var(--radius);display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <label class="search" style="flex:1;min-width:220px">
            <svg class="i"><use href="#i-search"/></svg>
            <input type="search" name="q" value="{{ $q }}" placeholder="Buscar por correo o código…" aria-label="Buscar" autocomplete="off">
        </label>
        <select name="resultado" onchange="this.form.submit()" aria-label="Resultado">
            <option value="">Todos los resultados</option>
            <option value="exitosos" @selected($resultado === 'exitosos')>Solo correctos</option>
            <option value="fallidos" @selected($resultado === 'fallidos')>Solo fallidos</option>
        </select>
        <select name="tipo" onchange="this.form.submit()" aria-label="Tipo de usuario">
            <option value="">Personal y estudiantes</option>
            <option value="PERSONAL" @selected(request('tipo') === 'PERSONAL')>Personal</option>
            <option value="ESTUDIANTE" @selected(request('tipo') === 'ESTUDIANTE')>Estudiantes</option>
        </select>
        <button class="btn ghost" type="submit">Buscar</button>
    </form>

    <section class="card" style="margin-top:16px">
        <div class="scroll">
            <table>
                <thead><tr><th>Fecha y hora</th><th>Tipo</th><th>Usuario / código</th><th>Resultado</th><th>IP de origen</th></tr></thead>
                <tbody>
                @forelse ($registros as $r)
                    <tr class="{{ $r->exitoso ? '' : 'alerta-fila' }}">
                        <td class="num strong">{{ $r->fecha_hora->copy()->timezone($zona)->format('d/m/Y H:i:s') }}</td>
                        <td>{{ $r->tipo === 'ESTUDIANTE' ? 'Estudiante' : 'Personal' }}</td>
                        <td>{{ $r->identificador }}</td>
                        <td>
                            <span class="badge {{ $r->exitoso ? 'habilitado' : 'inhabilitado' }}">{{ \App\Models\AccesoLog::RESULTADOS[$r->resultado] ?? $r->resultado }}</span>
                        </td>
                        <td class="num muted" title="{{ $r->user_agent }}">{{ $r->ip ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">Todavía no hay accesos registrados.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="foot"><span>{{ $registros->total() }} registro(s)</span><span>{{ $registros->links() }}</span></div>
    </section>
@endsection
