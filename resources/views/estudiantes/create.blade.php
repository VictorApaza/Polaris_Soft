<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nuevo estudiante · SCIEM</title>
    <style>
        :root { --navy-900:#0f1d4d; --blue-700:#1e3a8a; --blue-100:#e6ebf7; --bg:#f3f5fb;
                --line:#e2e8f0; --text:#1e293b; --muted:#64748b; --err:#dc2626; }
        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family: Inter, system-ui, sans-serif; background:var(--bg); color:var(--text); font-size:14px; }
        a { color: var(--blue-700); text-decoration:none; }
        main { max-width: 640px; margin: 40px auto; padding: 0 16px; }
        .crumb { font-size: 12px; color: var(--muted); margin-bottom: 16px; }
        h1 { font-size: 22px; color: var(--navy-900); margin-bottom: 20px; }
        .card { background:#fff; border:1px solid var(--line); border-radius:12px; padding:24px; }
        label { display:block; margin-bottom:16px; font-weight:600; font-size:13px; color:var(--text); }
        input, select { width:100%; margin-top:6px; padding:10px 12px; border:1px solid var(--line);
                 border-radius:8px; font:inherit; color:var(--text); }
        .error { display:block; color:var(--err); font-size:12px; font-weight:400; margin-top:4px; }
        .btn { display:inline-flex; background:var(--blue-700); color:#fff; font-weight:600;
               padding:10px 18px; border-radius:8px; border:0; cursor:pointer; margin-top:8px; }
        .btn:hover { background:var(--navy-900); }
    </style>
</head>
<body>
<main>
    <nav class="crumb"><a href="{{ route('estudiantes.index') }}">Estudiantes</a> › <span>Nuevo</span></nav>
    <h1>Registrar estudiante</h1>

    <div class="card">
        <form method="POST" action="{{ route('estudiantes.store') }}">
            @csrf

            <label>Código universitario
                <input name="codigo_universitario" value="{{ old('codigo_universitario') }}">
                @error('codigo_universitario') <span class="error">{{ $message }}</span> @enderror
            </label>

            <label>CI / Documento de identidad
                <input name="documento_identidad" value="{{ old('documento_identidad') }}">
                @error('documento_identidad') <span class="error">{{ $message }}</span> @enderror
            </label>

            <label>Nombres
                <input name="nombres" value="{{ old('nombres') }}">
                @error('nombres') <span class="error">{{ $message }}</span> @enderror
            </label>

            <label>Apellidos
                <input name="apellidos" value="{{ old('apellidos') }}">
                @error('apellidos') <span class="error">{{ $message }}</span> @enderror
            </label>

            <label>Carrera
                <input name="carrera" value="{{ old('carrera') }}">
                @error('carrera') <span class="error">{{ $message }}</span> @enderror
            </label>

            <label>Correo institucional
                <input type="email" name="correo_institucional" value="{{ old('correo_institucional') }}">
                @error('correo_institucional') <span class="error">{{ $message }}</span> @enderror
            </label>

            <label>Estado
                <select name="estado">
                    <option value="ACTIVO" @selected(old('estado') === 'ACTIVO')>Activo</option>
                    <option value="OBSERVADO" @selected(old('estado') === 'OBSERVADO')>Observado</option>
                    <option value="INACTIVO" @selected(old('estado') === 'INACTIVO')>Inactivo</option>
                </select>
                @error('estado') <span class="error">{{ $message }}</span> @enderror
            </label>

            <button class="btn" type="submit">Guardar estudiante</button>
        </form>
    </div>
</main>
</body>
</html>
