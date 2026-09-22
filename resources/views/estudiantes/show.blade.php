<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detalle de estudiante · SCIEM</title>
    <style>
        :root { --navy-900:#0f1d4d; --blue-700:#1e3a8a; --bg:#f3f5fb; --line:#e2e8f0; --text:#1e293b; --muted:#64748b; }
        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family: Inter, system-ui, sans-serif; background:var(--bg); color:var(--text); font-size:14px; }
        a { color: var(--blue-700); text-decoration:none; }
        main { max-width: 640px; margin: 40px auto; padding: 0 16px; }
        .crumb { font-size: 12px; color: var(--muted); margin-bottom: 16px; }
        h1 { font-size: 22px; color: var(--navy-900); margin-bottom: 20px; }
        .card { background:#fff; border:1px solid var(--line); border-radius:12px; padding:24px; }
        dl { display:grid; grid-template-columns: 180px 1fr; row-gap:14px; }
        dt { font-weight:600; color:var(--muted); }
        .btn { display:inline-flex; background:var(--blue-700); color:#fff; font-weight:600;
               padding:10px 18px; border-radius:8px; border:0; text-decoration:none; margin-top:20px; }
    </style>
</head>
<body>
<main>
    <nav class="crumb"><a href="{{ route('estudiantes.index') }}">Estudiantes</a> › <span>Detalle</span></nav>
    <h1>{{ $estudiante->apellidos }}, {{ $estudiante->nombres }}</h1>

    <div class="card">
        <dl>
            <dt>Código universitario</dt><dd>{{ $estudiante->codigo_universitario }}</dd>
            <dt>CI / Documento</dt><dd>{{ $estudiante->documento_identidad }}</dd>
            <dt>Carrera</dt><dd>{{ $estudiante->carrera ?? '—' }}</dd>
            <dt>Correo institucional</dt><dd>{{ $estudiante->correo_institucional }}</dd>
            <dt>Estado</dt><dd>{{ $estudiante->estado }}</dd>
        </dl>
        <a class="btn" href="{{ route('estudiantes.edit', $estudiante) }}">Editar</a>
    </div>
</main>
</body>
</html>
