<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Usuarios · SCIEM</title>

    <style>
        :root {
            --navy-900: #0f1d4d; --navy-800: #16286a; --blue-700: #1e3a8a; --blue-100: #e6ebf7;
            --bg: #f3f5fb; --line: #e2e8f0; --text: #1e293b; --muted: #64748b;
            --ok: #15803d; --ok-bg: #dcfce7; --warn: #b45309; --warn-bg: #fef3c7;
            --off: #475569; --off-bg: #e5e7eb;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Inter, system-ui, sans-serif; background: var(--bg); color: var(--text); font-size: 14px; }
        a { color: inherit; text-decoration: none; }
        svg.i { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 1.5; stroke-linecap: round; stroke-linejoin: round; }

        .app { display: grid; grid-template-columns: 224px 1fr; min-height: 100vh; }

        .sidebar { background: var(--navy-900); color: #fff; display: flex; flex-direction: column; }
        .brand { padding: 16px; border-bottom: 1px solid rgba(255,255,255,.1); line-height: 1.2; }
        .brand b { font-size: 15px; display: block; }
        .brand small { font-size: 10px; color: rgba(230,235,247,.6); }
        .nav { flex: 1; padding: 20px 12px; }
        .nav h3 { font-size: 11px; font-weight: 600; color: rgba(230,235,247,.5); padding: 0 12px 8px; }
        .nav a { display: flex; align-items: center; gap: 12px; padding: 9px 12px; border-radius: 8px;
                 color: rgba(230,235,247,.7); font-weight: 500; margin-bottom: 4px; }
        .nav a:hover { background: rgba(255,255,255,.06); color: #fff; }
        .nav a.active { background: rgba(255,255,255,.12); color: #fff; }
        .side-foot { padding: 12px 16px; border-top: 1px solid rgba(255,255,255,.1); font-size: 11px; color: rgba(230,235,247,.6); line-height: 1.5; }

        .topbar { height: 56px; background: #fff; border-bottom: 1px solid var(--line); display: flex;
                  align-items: center; justify-content: space-between; padding: 0 24px; }
        .topbar h2 { font-size: 14px; font-weight: 600; }
        .topbar h2 span { font-weight: 400; color: var(--muted); }
        .user { display: flex; align-items: center; gap: 16px; }
        .user .who { text-align: right; line-height: 1.25; }
        .user .who b { display: block; font-size: 13px; }
        .role { display: inline-block; background: var(--blue-700); color: #fff; font-size: 10px; font-weight: 600;
                text-transform: uppercase; padding: 1px 6px; border-radius: 4px; }
        .logout { display: flex; align-items: center; gap: 6px; background: none; border: 0; color: #475569;
                  font: inherit; cursor: pointer; padding: 6px 8px; border-radius: 8px; }
        .logout:hover { background: #f1f5f9; }

        main { padding: 24px 32px; }
        .crumb { font-size: 12px; color: var(--muted); }
        .crumb a:hover { text-decoration: underline; }
        .head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; margin-top: 8px; }
        .head h1 { font-size: 24px; color: var(--navy-900); }
        .head p { color: var(--muted); margin-top: 4px; }
        .btn { display: inline-flex; align-items: center; gap: 8px; background: var(--blue-700); color: #fff;
               font-weight: 600; padding: 10px 16px; border-radius: 8px; border: 0; cursor: pointer; }
        .btn:hover { background: var(--navy-800); }
        a:focus-visible, button:focus-visible, input:focus-visible, select:focus-visible { outline: 2px solid var(--blue-700); outline-offset: 2px; }

        .flash { margin-top: 16px; padding: 8px 16px; border-radius: 8px; background: var(--ok-bg); color: var(--ok); }

        .filters { display: grid; grid-template-columns: 1fr auto auto; gap: 12px; margin-top: 24px; }
        .search { position: relative; }
        .search svg { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; width: 16px; height: 16px; }
        .search input, .filters select { width: 100%; padding: 10px 12px; border: 1px solid var(--line);
                 border-radius: 8px; background: #fff; font: inherit; color: var(--text); }
        .search input { padding-left: 36px; }

        .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 16px; }
        .stat { display: flex; justify-content: space-between; align-items: flex-start; background: #fff;
                border: 1px solid var(--line); border-radius: 12px; padding: 16px; }
        .stat small { font-size: 12px; font-weight: 600; color: var(--muted); }
        .stat strong { display: block; font-size: 30px; margin-top: 4px; font-variant-numeric: tabular-nums; }
        .stat .ic { width: 36px; height: 36px; border-radius: 8px; display: grid; place-items: center; background: var(--blue-100); color: var(--blue-700); }
        .stat.ok small, .stat.ok strong { color: var(--ok); }
        .stat.ok .ic { background: var(--ok-bg); color: var(--ok); }
        .stat.off .ic { background: var(--off-bg); color: var(--off); }

        .card { margin-top: 16px; background: #fff; border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
        .scroll { overflow-x: auto; }
        table { width: 100%; min-width: 860px; border-collapse: collapse; text-align: left; }
        th { font-size: 12px; font-weight: 600; color: var(--muted); padding: 12px 16px; border-bottom: 1px solid var(--line); }
        td { padding: 14px 16px; border-bottom: 1px solid #f1f5f9; }
        tbody tr:hover { background: #f8faff; }
        td.strong { font-weight: 600; color: #0f172a; }
        td.mail { color: var(--muted); }
        .empty { text-align: center; color: var(--muted); padding: 48px 16px; }
        .empty a { color: var(--blue-700); font-weight: 500; }

        .chip { display: inline-block; background: var(--blue-100); color: var(--blue-700); font-size: 11px;
                font-weight: 600; padding: 2px 8px; border-radius: 999px; margin: 0 4px 4px 0; }

        .badge { display: inline-flex; align-items: center; gap: 6px; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 500; }
        .badge::before { content: ""; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
        .badge.activo   { background: var(--ok-bg);  color: var(--ok); }
        .badge.inactivo { background: var(--off-bg); color: var(--off); }

        .actions { display: flex; justify-content: flex-end; gap: 4px; }
        .actions form { display: inline; }
        .icon-btn { width: 32px; height: 32px; display: grid; place-items: center; border-radius: 6px;
                    color: var(--muted); background: none; border: 0; cursor: pointer; }
        .icon-btn:hover { background: #f1f5f9; color: var(--blue-700); }
        .icon-btn.danger:hover { background: #fef2f2; color: #dc2626; }

        .foot { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;
                padding: 12px 16px; border-top: 1px solid var(--line); font-size: 12px; color: var(--muted); }
        .pager { display: flex; gap: 4px; }
        .pager a, .pager span { min-width: 28px; height: 28px; display: grid; place-items: center; border-radius: 6px; font-size: 12px; }
        .pager a:hover { background: #f1f5f9; }
        .pager .current { background: var(--blue-700); color: #fff; font-weight: 600; }
        .pager .disabled { color: #cbd5e1; }

        @media (max-width: 900px) {
            .app { grid-template-columns: 1fr; }
            .sidebar { display: none; }
            .filters, .stats { grid-template-columns: 1fr; }
            main { padding: 20px 16px; }
            .user .who { display: none; }
        }
    </style>
</head>
<body>

<svg width="0" height="0" style="position:absolute" aria-hidden="true">
    <symbol id="i-search" viewBox="0 0 24 24"><path d="m21 21-5.2-5.2m0 0A7.5 7.5 0 1 0 5.2 5.2a7.5 7.5 0 0 0 10.6 10.6Z"/></symbol>
    <symbol id="i-eye" viewBox="0 0 24 24"><path d="M2 12.3a1 1 0 0 1 0-.6C3.4 7.5 7.4 4.5 12 4.5s8.6 3 10 7.2a1 1 0 0 1 0 .6c-1.4 4.2-5.4 7.2-10 7.2S3.4 16.5 2 12.3Z"/><circle cx="12" cy="12" r="3"/></symbol>
    <symbol id="i-edit" viewBox="0 0 24 24"><path d="m16.9 4.5 1.7-1.7a1.9 1.9 0 0 1 2.6 2.6L10.6 16a4.5 4.5 0 0 1-1.9 1.1L6 18l.8-2.7a4.5 4.5 0 0 1 1.1-1.9l9-8.9ZM18 14v4.8A2.2 2.2 0 0 1 15.8 21H5.2A2.2 2.2 0 0 1 3 18.8V8.2A2.2 2.2 0 0 1 5.2 6H10"/></symbol>
    <symbol id="i-trash" viewBox="0 0 24 24"><path d="m14.7 9-.3 9m-4.8 0L9.3 9m9.9-3.2c.3 0 .7.1 1 .2m-1-.2-1.1 13.9a2.2 2.2 0 0 1-2.2 2H8.1a2.2 2.2 0 0 1-2.2-2L4.8 5.8m14.4 0a48 48 0 0 0-3.5-.4m-12 .6 1-.2m0 0a48 48 0 0 1 3.5-.4m7.5 0v-.9c0-1.2-.9-2.2-2.1-2.2a52 52 0 0 0-3.3 0c-1.2 0-2.1 1-2.1 2.2v.9m7.5 0a48.7 48.7 0 0 0-7.5 0"/></symbol>
    <symbol id="i-user-plus" viewBox="0 0 24 24"><path d="M18 7.5v6m3-3h-6m-2.3-1.1a3.4 3.4 0 1 1-6.7 0 3.4 3.4 0 0 1 6.7 0ZM3 19.2v-.1a6.4 6.4 0 0 1 12.8 0v.1A12.3 12.3 0 0 1 9.4 21 12.3 12.3 0 0 1 3 19.2Z"/></symbol>
    <symbol id="i-dashboard" viewBox="0 0 24 24"><rect x="3.75" y="3.75" width="6.75" height="6.75" rx="2"/><rect x="13.5" y="3.75" width="6.75" height="6.75" rx="2"/><rect x="3.75" y="13.5" width="6.75" height="6.75" rx="2"/><rect x="13.5" y="13.5" width="6.75" height="6.75" rx="2"/></symbol>
    <symbol id="i-students" viewBox="0 0 24 24"><path d="M12 3.5 1.6 9.3 12 13.5l10.4-4.2L12 3.5ZM4.3 10.1a60 60 0 0 0-.5 6.4A48.6 48.6 0 0 1 12 20.9a48.6 48.6 0 0 1 8.2-4.4 60 60 0 0 0-.5-6.4"/></symbol>
    <symbol id="i-exams" viewBox="0 0 24 24"><path d="M19.5 14.25v-2.6a3.4 3.4 0 0 0-3.4-3.4h-1.5a1.1 1.1 0 0 1-1.1-1.1v-1.5a3.4 3.4 0 0 0-3.4-3.4H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.6c-.6 0-1.1.5-1.1 1.1v17.3c0 .6.5 1.1 1.1 1.1h12.8c.6 0 1.1-.5 1.1-1.1V11.25a9 9 0 0 0-9-9Z"/></symbol>
    <symbol id="i-users" viewBox="0 0 24 24"><path d="M15 19.1a9.4 9.4 0 0 0 2.6.4 9.3 9.3 0 0 0 4.1-1 4.1 4.1 0 0 0-7.5-2.5M15 19.1v-.1c0-1.1-.3-2.2-.8-3.1M15 19.1v.1A12.3 12.3 0 0 1 8.6 21c-2.3 0-4.5-.6-6.4-1.8v-.1a6.4 6.4 0 0 1 12-3.1M12 6.4a3.4 3.4 0 1 1-6.7 0 3.4 3.4 0 0 1 6.7 0Zm8.2 2.2a2.6 2.6 0 1 1-5.2 0 2.6 2.6 0 0 1 5.2 0Z"/></symbol>
    <symbol id="i-logout" viewBox="0 0 24 24"><path d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"/></symbol>
    <symbol id="i-check" viewBox="0 0 24 24"><path d="m9 12.75 2.25 2.25L15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></symbol>
    <symbol id="i-info" viewBox="0 0 24 24"><path d="M12 11.25v5m0-8h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></symbol>
</svg>

<div class="app">

    <aside class="sidebar">
        <div class="brand">
            <b>SCIEM</b>
            <small>Control de Exámenes</small>
        </div>

        <nav class="nav" aria-label="Módulos principales">
            <h3>Módulos principales</h3>
            <a href="{{ url('/dashboard') }}"><svg class="i"><use href="#i-dashboard"/></svg> Dashboard</a>

            <a href="#" style="opacity:.5;pointer-events:none;"><svg class="i"><use href="#i-students"/></svg> Estudiantes</a>
            <a href="#"><svg class="i"><use href="#i-exams"/></svg> Exámenes</a>
            <a href="{{ route('usuarios.index') }}" class="active" aria-current="page"><svg class="i"><use href="#i-users"/></svg> Usuarios</a>
        </nav>

        <div class="side-foot">
            <b>Versión Sprint 1.0</b><br>
            Proyecto ISW · Gestión 2025
        </div>
    </aside>

    <div>
        <header class="topbar">
            <h2>SCIEM <span>– Sistema de Control de Exámenes Masivos</span></h2>

            <div class="user">
                <div class="who">
                    <b>{{ auth()->user()->nombre_completo ?? 'Carlos Choque' }}</b>
                    <span class="role">{{ auth()->user()?->roles->first()->nombre ?? 'Administrador' }}</span>
                </div>
                <form method="POST" action="{{ url('/logout') }}">
                    @csrf
                    <button class="logout" type="submit">
                        <svg class="i"><use href="#i-logout"/></svg> Cerrar sesión
                    </button>
                </form>
            </div>
        </header>

        <main>
            <nav class="crumb" aria-label="Ruta">
                <a href="{{ url('/dashboard') }}">Inicio</a> › <span>Usuarios</span>
            </nav>

            <div class="head">
                <div>
                    <h1>Administración de usuarios</h1>
                    <p>Registrar y administrar las cuentas de acceso al sistema.</p>
                </div>
                <a class="btn" href="{{ route('usuarios.create') }}">
                    <svg class="i"><use href="#i-user-plus"/></svg> Nuevo usuario
                </a>
            </div>

            @if (session('status'))
                <div class="flash" role="status">{{ session('status') }}</div>
            @endif

            <form class="filters" method="GET" action="{{ route('usuarios.index') }}">
                <label class="search">
                    <svg class="i"><use href="#i-search"/></svg>
                    <input type="search" name="buscar" value="{{ request('buscar') }}"
                           placeholder="Buscar usuario por nombre o usuario…" aria-label="Buscar usuario">
                </label>

                <select name="rol" aria-label="Rol" onchange="this.form.submit()">
                    <option value="">Rol (todos)</option>
                    @foreach ($roles as $rol)
                        <option value="{{ $rol->id_rol }}" @selected((string) request('rol') === (string) $rol->id_rol)>{{ $rol->nombre }}</option>
                    @endforeach
                </select>

                <select name="estado" aria-label="Estado" onchange="this.form.submit()">
                    <option value="">Estado (todos)</option>
                    <option value="ACTIVO"   @selected(request('estado') === 'ACTIVO')>Activo</option>
                    <option value="INACTIVO" @selected(request('estado') === 'INACTIVO')>Inactivo</option>
                </select>
            </form>

            <section class="stats">
                <div class="stat">
                    <div><small>Usuarios totales</small><strong>{{ number_format($stats['total']) }}</strong></div>
                    <span class="ic"><svg class="i"><use href="#i-users"/></svg></span>
                </div>
                <div class="stat ok">
                    <div><small>Activos</small><strong>{{ number_format($stats['activos']) }}</strong></div>
                    <span class="ic"><svg class="i"><use href="#i-check"/></svg></span>
                </div>
                <div class="stat off">
                    <div><small>Inactivos</small><strong>{{ number_format($stats['inactivos']) }}</strong></div>
                    <span class="ic"><svg class="i"><use href="#i-info"/></svg></span>
                </div>
            </section>

            <section class="card">
                <div class="scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Nombre completo</th>
                                <th>Roles</th>
                                <th>Estado</th>
                                <th style="text-align:right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse ($usuarios as $u)
                            <tr>
                                <td class="strong">{{ $u->usuario }}</td>
                                <td class="strong">{{ $u->nombre_completo }}</td>
                                <td>
                                    @forelse ($u->roles as $rol)
                                        <span class="chip">{{ $rol->nombre }}</span>
                                    @empty
                                        <span class="mail">Sin rol asignado</span>
                                    @endforelse
                                </td>
                                <td>
                                    <span class="badge {{ strtolower($u->estado) }}">{{ ucfirst(strtolower($u->estado)) }}</span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a class="icon-btn" href="{{ route('usuarios.edit', $u) }}" title="Editar" aria-label="Editar">
                                            <svg class="i"><use href="#i-edit"/></svg></a>
                                        <form method="POST" action="{{ route('usuarios.destroy', $u) }}"
                                              onsubmit="return confirm('¿Eliminar a este usuario? Esta acción no se puede deshacer.')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="icon-btn danger" type="submit" title="Eliminar" aria-label="Eliminar">
                                                <svg class="i"><use href="#i-trash"/></svg></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="empty">
                                    No hay usuarios con esos filtros.
                                    <a href="{{ route('usuarios.index') }}">Limpiar filtros</a>
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="foot">
                    <p>
                        Mostrando {{ $usuarios->firstItem() ?? 0 }}–{{ $usuarios->lastItem() ?? 0 }}
                        de {{ number_format($usuarios->total()) }} registros
                    </p>

                    @if ($usuarios->hasPages())
                        @php $actual = $usuarios->currentPage(); $ultima = $usuarios->lastPage(); @endphp
                        <nav class="pager" aria-label="Paginación">
                            @if ($usuarios->onFirstPage())
                                <span class="disabled">‹</span>
                            @else
                                <a href="{{ $usuarios->previousPageUrl() }}" aria-label="Anterior">‹</a>
                            @endif

                            @foreach (range(max(1, $actual - 2), min($ultima, $actual + 2)) as $p)
                                @if ($p === $actual)
                                    <span class="current" aria-current="page">{{ $p }}</span>
                                @else
                                    <a href="{{ $usuarios->url($p) }}">{{ $p }}</a>
                                @endif
                            @endforeach

                            @if ($usuarios->hasMorePages())
                                <a href="{{ $usuarios->nextPageUrl() }}" aria-label="Siguiente">›</a>
                            @else
                                <span class="disabled">›</span>
                            @endif
                        </nav>
                    @endif
                </div>
            </section>
        </main>
    </div>
</div>

</body>
</html>