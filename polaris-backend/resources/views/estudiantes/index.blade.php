<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Estudiantes · SCIEM</title>

    <style>
        :root {
            --navy-900: #0f1d4d;
            --navy-800: #16286a;
            --blue-700: #1e3a8a;
            --blue-100: #e6ebf7;
            --bg: #f3f5fb;
            --line: #e2e8f0;
            --text: #1e293b;
            --muted: #64748b;
            --ok: #15803d;   --ok-bg: #dcfce7;
            --warn: #b45309; --warn-bg: #fef3c7;
            --off: #475569;  --off-bg: #e5e7eb;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Inter, system-ui, sans-serif; background: var(--bg); color: var(--text); font-size: 14px; }
        a { color: inherit; text-decoration: none; }
        svg.i { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 1.5; stroke-linecap: round; stroke-linejoin: round; }

        /* ---------- Estructura ---------- */
        .app { display: grid; grid-template-columns: 224px 1fr; min-height: 100vh; }

        /* ---------- Sidebar ---------- */
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

        /* ---------- Topbar ---------- */
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

        /* ---------- Contenido ---------- */
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

        /* Filtros */
        .filters { display: grid; grid-template-columns: 1fr auto auto; gap: 12px; margin-top: 24px; }
        .search { position: relative; }
        .search svg { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; width: 16px; height: 16px; }
        .search input, .filters select { width: 100%; padding: 10px 12px; border: 1px solid var(--line);
                 border-radius: 8px; background: #fff; font: inherit; color: var(--text); }
        .search input { padding-left: 36px; }

        /* Tarjetas resumen */
        .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 16px; }
        .stat { display: flex; justify-content: space-between; align-items: flex-start; background: #fff;
                border: 1px solid var(--line); border-radius: 12px; padding: 16px; }
        .stat small { font-size: 12px; font-weight: 600; color: var(--muted); }
        .stat strong { display: block; font-size: 30px; margin-top: 4px; font-variant-numeric: tabular-nums; }
        .stat .ic { width: 36px; height: 36px; border-radius: 8px; display: grid; place-items: center; background: var(--blue-100); color: var(--blue-700); }
        .stat.ok small, .stat.ok strong { color: var(--ok); }
        .stat.ok .ic { background: var(--ok-bg); color: var(--ok); }
        .stat.off .ic { background: var(--off-bg); color: var(--off); }

        /* Tabla */
        .card { margin-top: 16px; background: #fff; border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
        .scroll { overflow-x: auto; }
        table { width: 100%; min-width: 860px; border-collapse: collapse; text-align: left; }
        th { font-size: 12px; font-weight: 600; color: var(--muted); padding: 12px 16px; border-bottom: 1px solid var(--line); }
        td { padding: 14px 16px; border-bottom: 1px solid #f1f5f9; }
        tbody tr:hover { background: #f8faff; }
        td.strong { font-weight: 600; color: #0f172a; }
        td.num { font-variant-numeric: tabular-nums; }
        td.mail { color: var(--muted); }
        .empty { text-align: center; color: var(--muted); padding: 48px 16px; }
        .empty a { color: var(--blue-700); font-weight: 500; }

        .badge { display: inline-flex; align-items: center; gap: 6px; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 500; }
        .badge::before { content: ""; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
        .badge.activo    { background: var(--ok-bg);   color: var(--ok); }
        .badge.observado { background: var(--warn-bg); color: var(--warn); }
        .badge.inactivo  { background: var(--off-bg);  color: var(--off); }

        .actions { display: flex; justify-content: flex-end; gap: 4px; }
        .actions form { display: inline; }
        .icon-btn { width: 32px; height: 32px; display: grid; place-items: center; border-radius: 6px;
                    color: var(--muted); background: none; border: 0; cursor: pointer; }
        .icon-btn:hover { background: #f1f5f9; color: var(--blue-700); }
        .icon-btn.danger:hover { background: #fef2f2; color: #dc2626; }

        /* Pie de tabla */
        .foot { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;
                padding: 12px 16px; border-top: 1px solid var(--line); font-size: 12px; color: var(--muted); }
        .pager { display: flex; gap: 4px; }
        .pager a, .pager span { min-width: 28px; height: 28px; display: grid; place-items: center; border-radius: 6px; font-size: 12px; }
        .pager a:hover { background: #f1f5f9; }
        .pager .current { background: var(--blue-700); color: #fff; font-weight: 600; }
        .pager .disabled { color: #cbd5e1; }

        /* Responsive */
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

{{-- Iconos (sprite SVG, se reutilizan con <use>) --}}
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
    <symbol id="i-search" viewBox="0 0 24 24"><path d="m21 21-5.2-5.2m0 0A7.5 7.5 0 1 0 5.2 5.2a7.5 7.5 0 0 0 10.6 10.6Z"/></symbol>
    <symbol id="i-eye" viewBox="0 0 24 24"><path d="M2 12.3a1 1 0 0 1 0-.6C3.4 7.5 7.4 4.5 12 4.5s8.6 3 10 7.2a1 1 0 0 1 0 .6c-1.4 4.2-5.4 7.2-10 7.2S3.4 16.5 2 12.3Z"/><circle cx="12" cy="12" r="3"/></symbol>
    <symbol id="i-edit" viewBox="0 0 24 24"><path d="m16.9 4.5 1.7-1.7a1.9 1.9 0 0 1 2.6 2.6L10.6 16a4.5 4.5 0 0 1-1.9 1.1L6 18l.8-2.7a4.5 4.5 0 0 1 1.1-1.9l9-8.9ZM18 14v4.8A2.2 2.2 0 0 1 15.8 21H5.2A2.2 2.2 0 0 1 3 18.8V8.2A2.2 2.2 0 0 1 5.2 6H10"/></symbol>
    <symbol id="i-trash" viewBox="0 0 24 24"><path d="m14.7 9-.3 9m-4.8 0L9.3 9m9.9-3.2c.3 0 .7.1 1 .2m-1-.2-1.1 13.9a2.2 2.2 0 0 1-2.2 2H8.1a2.2 2.2 0 0 1-2.2-2L4.8 5.8m14.4 0a48 48 0 0 0-3.5-.4m-12 .6 1-.2m0 0a48 48 0 0 1 3.5-.4m7.5 0v-.9c0-1.2-.9-2.200-2.100-2.200a52 52 0 0 0-3.300 0c-1.200 0-2.100 1-2.100 2.200v.9m7.500 0a48.700 48.700 0 0 0-7.500 0"/></symbol>
    <symbol id="i-user-plus" viewBox="0 0 24 24"><path d="M18 7.5v6m3-3h-6m-2.300-1.100a3.400 3.400 0 1 1-6.700 0 3.400 3.400 0 0 1 6.700 0ZM3 19.200v-.1a6.400 6.400 0 0 1 12.800 0v.1A12.300 12.300 0 0 1 9.400 21 12.300 12.300 0 0 1 3 19.200Z"/></symbol>
    <symbol id="i-dashboard" viewBox="0 0 24 24"><rect x="3.750" y="3.750" width="6.750" height="6.750" rx="2"/><rect x="13.500" y="3.750" width="6.750" height="6.750" rx="2"/><rect x="3.750" y="13.500" width="6.750" height="6.750" rx="2"/><rect x="13.500" y="13.500" width="6.750" height="6.750" rx="2"/></symbol>
    <symbol id="i-students" viewBox="0 0 24 24"><path d="M12 3.500 1.600 9.300 12 13.500l10.400-4.200L12 3.500ZM4.300 10.100a60 60 0 0 0-.5 6.400A48.600 48.600 0 0 1 12 20.900a48.600 48.600 0 0 1 8.200-4.400 60 60 0 0 0-.5-6.400"/></symbol>
    <symbol id="i-exams" viewBox="0 0 24 24"><path d="M19.500 14.250v-2.600a3.400 3.400 0 0 0-3.400-3.400h-1.500a1.100 1.100 0 0 1-1.100-1.100v-1.500a3.400 3.400 0 0 0-3.400-3.400H8.250m0 12.750h7.500m-7.500 3H12M10.500 2.250H5.600c-.6 0-1.100.5-1.100 1.100v17.300c0 .6.5 1.100 1.100 1.100h12.800c.6 0 1.100-.5 1.100-1.100V11.250a9 9 0 0 0-9-9Z"/></symbol>
    <symbol id="i-users" viewBox="0 0 24 24"><path d="M15 19.100a9.400 9.400 0 0 0 2.600.4 9.300 9.300 0 0 0 4.100-1 4.100 4.100 0 0 0-7.500-2.500M15 19.100v-.1c0-1.100-.3-2.200-.8-3.100M15 19.100v.1A12.300 12.300 0 0 1 8.600 21c-2.300 0-4.500-.6-6.400-1.800v-.1a6.400 6.400 0 0 1 12-3.100M12 6.400a3.400 3.400 0 1 1-6.700 0 3.400 3.400 0 0 1 6.700 0Zm8.200 2.200a2.600 2.600 0 1 1-5.200 0 2.600 2.600 0 0 1 5.200 0Z"/></symbol>
    <symbol id="i-logout" viewBox="0 0 24 24"><path d="M15.750 9V5.250A2.250 2.250 0 0 0 13.500 3h-6a2.250 2.250 0 0 0-2.250 2.250v13.500A2.250 2.250 0 0 0 7.500 21h6a2.250 2.250 0 0 0 2.250-2.250V15m3 0 3-3m0 0-3-3m3 3H9"/></symbol>
    <symbol id="i-check" viewBox="0 0 24 24"><path d="m9 12.750 2.250 2.250L15 9.750M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></symbol>
    <symbol id="i-info" viewBox="0 0 24 24"><path d="M12 11.250v5m0-8h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></symbol>
</svg>

<div class="app">

    {{-- ================= SIDEBAR ================= --}}
    <aside class="sidebar">
        <div class="brand">
            <b>SCIEM</b>
            <small>Control de Exámenes</small>
        </div>

        <nav class="nav" aria-label="Módulos principales">
            <h3>Módulos principales</h3>
            <a href="{{ url('/dashboard') }}"><svg class="i"><use href="#i-dashboard"/></svg> Dashboard</a>
            <a href="{{ route('estudiantes.index') }}" class="active" aria-current="page"><svg class="i"><use href="#i-students"/></svg> Estudiantes</a>
            <a href="#"><svg class="i"><use href="#i-exams"/></svg> Exámenes</a>
            <a href="#"><svg class="i"><use href="#i-users"/></svg> Usuarios</a>
        </nav>

        <div class="side-foot">
            <b>Versión Sprint 1.0</b><br>
            Proyecto ISW · Gestión 2025
        </div>
    </aside>

    <div>
        {{-- ================= TOPBAR ================= --}}
        <header class="topbar">
            <h2>SCIEM <span>– Sistema de Control de Exámenes Masivos</span></h2>

            <div class="user">
                <div class="who">
                    <b>{{ auth()->user()->name ?? 'Carlos Choque' }}</b>
                    <span class="role">{{ auth()->user()->rol ?? 'Administrador' }}</span>
                </div>
                <form method="POST" action="{{ url('/logout') }}">
                    @csrf
                    <button class="logout" type="submit">
                        <svg class="i"><use href="#i-logout"/></svg> Cerrar sesión
                    </button>
                </form>
            </div>
        </header>

        {{-- ================= CONTENIDO ================= --}}
        <main>
            <nav class="crumb" aria-label="Ruta">
                <a href="{{ url('/dashboard') }}">Inicio</a> › <span>Estudiantes</span>
            </nav>

            <div class="head">
                <div>
                    <h1>Gestión de estudiantes</h1>
                    <p>Registrar y administrar estudiantes que participarán en los exámenes.</p>
                </div>
                <a class="btn" href="{{ route('estudiantes.create') }}">
                    <svg class="i"><use href="#i-user-plus"/></svg> Nuevo estudiante
                </a>
            </div>

            @if (session('status'))
                <div class="flash" role="status">{{ session('status') }}</div>
            @endif

            {{-- Filtros --}}
            <form class="filters" method="GET" action="{{ route('estudiantes.index') }}">
                <label class="search">
                    <svg class="i"><use href="#i-search"/></svg>
                    <input type="search" name="buscar" value="{{ request('buscar') }}"
                           placeholder="Buscar estudiante por código, DNI o apellidos…" aria-label="Buscar estudiante">
                </label>

                <select name="carrera" aria-label="Carrera" onchange="this.form.submit()">
                    <option value="">Carrera (todas las carreras)</option>
                    @foreach ($carreras as $carrera)
                        <option value="{{ $carrera }}" @selected(request('carrera') === $carrera)>{{ $carrera }}</option>
                    @endforeach
                </select>

                <select name="estado" aria-label="Estado" onchange="this.form.submit()">
                    <option value="">Estado (todos)</option>
                    <option value="activo"    @selected(request('estado') === 'activo')>Activo</option>
                    <option value="observado" @selected(request('estado') === 'observado')>Observado</option>
                    <option value="inactivo"  @selected(request('estado') === 'inactivo')>Inactivo</option>
                </select>
            </form>

            {{-- Resumen --}}
            <section class="stats">
                <div class="stat">
                    <div><small>Matrícula total</small><strong>{{ number_format($stats['total']) }}</strong></div>
                    <span class="ic"><svg class="i"><use href="#i-users"/></svg></span>
                </div>
                <div class="stat ok">
                    <div><small>Habilitados / activos</small><strong>{{ number_format($stats['habilitados']) }}</strong></div>
                    <span class="ic"><svg class="i"><use href="#i-check"/></svg></span>
                </div>
                <div class="stat off">
                    <div><small>Inactivos / observados</small><strong>{{ number_format($stats['inactivos']) }}</strong></div>
                    <span class="ic"><svg class="i"><use href="#i-info"/></svg></span>
                </div>
            </section>

            {{-- Tabla --}}
            <section class="card">
                <div class="scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Código universitario</th>
                                <th>Nombre completo</th>
                                <th>CI / DNI</th>
                                <th>Carrera</th>
                                <th>Correo institucional</th>
                                <th>Estado</th>
                                <th style="text-align:right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse ($estudiantes as $e)
                            <tr>
                                <td class="strong num">{{ $e->codigo }}</td>
                                <td class="strong">{{ $e->nombre_completo }}</td>
                                <td class="num">{{ $e->ci }}</td>
                                <td>{{ $e->carrera }}</td>
                                <td class="mail">{{ $e->correo }}</td>
                                <td><span class="badge {{ $e->estado }}">{{ ucfirst($e->estado) }}</span></td>
                                <td>
                                    <div class="actions">
                                        <a class="icon-btn" href="{{ route('estudiantes.show', $e) }}" title="Ver" aria-label="Ver">
                                            <svg class="i"><use href="#i-eye"/></svg></a>
                                        <a class="icon-btn" href="{{ route('estudiantes.edit', $e) }}" title="Editar" aria-label="Editar">
                                            <svg class="i"><use href="#i-edit"/></svg></a>
                                        <form method="POST" action="{{ route('estudiantes.destroy', $e) }}"
                                              onsubmit="return confirm('¿Eliminar a este estudiante? Esta acción no se puede deshacer.')">
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
                                <td colspan="7" class="empty">
                                    No hay estudiantes con esos filtros.
                                    <a href="{{ route('estudiantes.index') }}">Limpiar filtros</a>
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="foot">
                    <p>
                        Mostrando {{ $estudiantes->firstItem() ?? 0 }}–{{ $estudiantes->lastItem() ?? 0 }}
                        de {{ number_format($estudiantes->total()) }} registros
                    </p>

                    @if ($estudiantes->hasPages())
                        @php
                            $actual = $estudiantes->currentPage();
                            $ultima = $estudiantes->lastPage();
                        @endphp
                        <nav class="pager" aria-label="Paginación">
                            @if ($estudiantes->onFirstPage())
                                <span class="disabled">‹</span>
                            @else
                                <a href="{{ $estudiantes->previousPageUrl() }}" aria-label="Anterior">‹</a>
                            @endif

                            @foreach (range(max(1, $actual - 2), min($ultima, $actual + 2)) as $p)
                                @if ($p === $actual)
                                    <span class="current" aria-current="page">{{ $p }}</span>
                                @else
                                    <a href="{{ $estudiantes->url($p) }}">{{ $p }}</a>
                                @endif
                            @endforeach

                            @if ($estudiantes->hasMorePages())
                                <a href="{{ $estudiantes->nextPageUrl() }}" aria-label="Siguiente">›</a>
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