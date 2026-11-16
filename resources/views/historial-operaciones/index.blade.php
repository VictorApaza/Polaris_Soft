@extends('layouts.app')
@section('title', 'Historial de operaciones')

@section('content')
    <style>
        .historial-toolbar { display:grid; grid-template-columns:repeat(5,minmax(125px,1fr)); gap:10px; align-items:end; }
        .historial-toolbar label { display:grid; gap:5px; color:var(--muted); font-size:12px; }
        .historial-toolbar input, .historial-toolbar select { width:100%; min-width:0; }
        .historial-acciones { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
        .historial-detalle { min-width:200px; max-width:360px; white-space:normal; }
        .historial-examen { min-width:150px; }
        .badge.error { background:var(--danger-bg); color:var(--danger); }
        .solo-lectura { color:var(--muted); font-size:12px; border:1px solid var(--line); border-radius:20px; padding:7px 10px; white-space:nowrap; }
        @media (max-width:900px) { .historial-toolbar { grid-template-columns:repeat(2,minmax(0,1fr)); } }
        @media (max-width:560px) { .historial-toolbar { grid-template-columns:1fr; } }
    </style>

    <nav class="crumb" aria-label="Ruta">
        <a href="{{ url('/dashboard') }}">Inicio</a> › <b>Historial de operaciones</b>
    </nav>

    <div class="head">
        <div>
            <h1>Historial de operaciones</h1>
            <p>Quién hizo qué y cuándo, para auditar el uso del sistema.</p>
        </div>
        <span class="solo-lectura">Solo lectura. No se edita ni se elimina.</span>
    </div>

    @include('partials.flash')

    <section class="stats three">
        <div class="stat"><div><small>Registros totales</small><strong>{{ number_format($totalRegistros, 0, ',', '.') }}</strong></div><span class="ic"><svg class="i"><use href="#i-shield"/></svg></span></div>
        <div class="stat ok"><div><small>Operaciones hoy</small><strong>{{ number_format($operacionesHoy, 0, ',', '.') }}</strong></div><span class="ic"><svg class="i"><use href="#i-check"/></svg></span></div>
        <div class="stat"><div><small>Errores de registro</small><strong style="{{ $errores ? 'color:var(--danger)' : '' }}">{{ number_format($errores, 0, ',', '.') }}</strong></div><span class="ic" style="background:var(--danger-bg);color:var(--danger)"><svg class="i"><use href="#i-alert"/></svg></span></div>
    </section>

    <form class="card historial-toolbar" method="GET" action="{{ route('historial-operaciones.index') }}" style="margin-top:16px;padding:14px 16px">
        <label>Desde<input type="date" name="desde" value="{{ $filtros['desde'] ?? '' }}"></label>
        <label>Hasta<input type="date" name="hasta" value="{{ $filtros['hasta'] ?? '' }}"></label>
        <label>Usuario
            <select name="usuario">
                <option value="">Todos</option>
                @foreach ($usuarios as $usuario)
                    <option value="{{ $usuario }}" @selected(($filtros['usuario'] ?? '') === $usuario)>{{ $usuario }}</option>
                @endforeach
            </select>
        </label>
        <label>Acción
            <select name="accion">
                <option value="">Todas</option>
                @foreach ($acciones as $accion)
                    <option value="{{ $accion }}" @selected(($filtros['accion'] ?? '') === $accion)>{{ $accion }}</option>
                @endforeach
            </select>
        </label>
        <label>Examen
            <select name="examen_id">
                <option value="">Todos</option>
                @foreach ($examenes as $examen)
                    <option value="{{ $examen->examen_id }}" @selected((string) ($filtros['examen_id'] ?? '') === (string) $examen->examen_id)>
                        {{ $examen->examen_nombre ?: 'Examen #'.$examen->examen_id }}
                    </option>
                @endforeach
            </select>
        </label>
        <div class="historial-acciones" style="grid-column:1/-1">
            <button class="btn" type="submit"><svg class="i"><use href="#i-search"/></svg> Filtrar</button>
            <a class="btn ghost" href="{{ route('historial-operaciones.index') }}">Limpiar</a>
            <a class="btn ghost" href="{{ route('historial-operaciones.exportar', request()->query()) }}"><svg class="i"><use href="#i-download"/></svg> Exportar CSV</a>
        </div>
    </form>

    <section class="card" style="margin-top:16px">
        <div class="scroll">
            <table>
                <thead><tr><th>Fecha y hora</th><th>Usuario</th><th>Acción</th><th>Detalle</th><th>Examen</th><th>Reg.</th></tr></thead>
                <tbody>
                @forelse ($registros as $registro)
                    <tr class="{{ $registro->estado === 'error' ? 'alerta-fila' : '' }}">
                        <td class="num strong">{{ $registro->fecha_hora->copy()->timezone(config('sciem.zona_horaria'))->format('d/m H:i:s') }}</td>
                        <td>{{ $registro->usuario_nombre }}<span class="sub">{{ \App\Models\User::ROLES[$registro->usuario_rol] ?? ucfirst($registro->usuario_rol) }}</span></td>
                        <td><span class="badge {{ $registro->estado === 'error' ? 'error' : 'habilitado' }}">{{ $registro->accion }}</span></td>
                        <td class="historial-detalle" @if ($registro->error) title="{{ $registro->error }}" @endif>{{ $registro->detalle }}</td>
                        <td class="historial-examen">{{ $registro->examen_nombre ?: '—' }}</td>
                        <td class="num">{{ $registro->registros_afectados }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">Todavía no hay operaciones registradas.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="foot"><span>{{ $registros->total() }} registro(s)</span><span>{{ $registros->links() }}</span></div>
    </section>
@endsection
