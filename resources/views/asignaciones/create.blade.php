<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Asignación académica · SCIEM</title>
    <style>
        :root {
            --navy: #0f1d4d;
            --blue: #1e3a8a;
            --background: #f3f5fb;
            --line: #e2e8f0;
            --text: #1e293b;
            --muted: #64748b;
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--background); color: var(--text); font: 14px Inter, system-ui, sans-serif; }
        main { max-width: 720px; margin: 40px auto; padding: 0 16px; }
        a { color: var(--blue); }
        .breadcrumb { margin-bottom: 18px; color: var(--muted); font-size: 12px; }
        h1 { margin: 0 0 8px; color: var(--navy); font-size: 24px; }
        .subtitle { margin: 0 0 24px; color: var(--muted); }
        .card { padding: 24px; border: 1px solid var(--line); border-radius: 12px; background: #fff; }
        .field { margin-bottom: 20px; }
        label { display: block; margin-bottom: 7px; font-weight: 600; }
        select { width: 100%; padding: 11px 12px; border: 1px solid var(--line); border-radius: 8px;
                 background: #fff; color: var(--text); font: inherit; }
        select:disabled { background: #f8fafc; color: var(--muted); }
        .help { margin: 6px 0 0; color: var(--muted); font-size: 12px; }
        .actions { display: flex; align-items: center; justify-content: flex-end; gap: 12px; flex-wrap: wrap;
                   padding-top: 8px; }
        .button { padding: 10px 16px; border: 0; border-radius: 8px; background: var(--blue); color: #fff;
                  font: inherit; font-weight: 600; }
        .button:disabled { cursor: not-allowed; opacity: .55; }
        .aviso { display: none; margin-bottom: 18px; padding: 10px 12px; border-radius: 8px; font-size: 13px; }
        .aviso.visible { display: block; }
        .aviso.info { background: #e6ebf7; color: var(--blue); }
        .aviso.ok { background: #dcfce7; color: #15803d; }
        .aviso.error { background: #fee2e2; color: #b91c1c; }
        :focus-visible { outline: 2px solid var(--blue); outline-offset: 2px; }
        @media (max-width: 600px) {
            main { margin: 24px auto; }
            .card { padding: 18px; }
            .actions { justify-content: stretch; }
            .actions a, .actions button { width: 100%; text-align: center; }
        }
    </style>
</head>
<body>
<main>
    <nav class="breadcrumb" aria-label="Ruta">
        <a href="{{ url('/') }}">Estudiantes</a> › Asignación académica
    </nav>
    <h1>Asignar materia, grupo y docente</h1>
    <p class="subtitle">{{ $estudiante->nombre_completo }} · {{ $estudiante->codigo_universitario }}</p>

    <section class="card" aria-label="Formulario de asignación">
        <div id="asignacion-aviso" class="aviso" role="status" aria-live="polite"></div>

        <form id="asignacion-form" method="post" novalidate
              data-api-url="{{ url('/api') }}" data-estudiante-id="{{ $estudiante->id_estudiante }}">
            @csrf
            <div class="field">
                <label for="materia">Materia</label>
                <select id="materia" name="materia_id" required disabled aria-describedby="materia-help">
                    <option value="">Selecciona una materia</option>
                </select>
                <p class="help" id="materia-help">Cargando materias…</p>
            </div>

            <div class="field">
                <label for="grupo">Grupo</label>
                <select id="grupo" name="grupo_id" required disabled aria-describedby="grupo-help">
                    <option value="">Selecciona un grupo</option>
                </select>
                <p class="help" id="grupo-help">Elige primero una materia.</p>
            </div>

            <div class="field">
                <label for="docente">Docente</label>
                <select id="docente" name="docente_id" required disabled aria-describedby="docente-help">
                    <option value="">Selecciona un docente</option>
                </select>
                <p class="help" id="docente-help">Elige primero un grupo.</p>
            </div>

            <div class="actions">
                <a href="{{ url('/') }}">Cancelar</a>
                <button class="button" type="submit" disabled>Guardar asignación</button>
            </div>
        </form>
    </section>
</main>

<script src="{{ asset('js/asignacion-api.js') }}"></script>
<script src="{{ asset('js/asignacion.js') }}"></script>
</body>
</html>
