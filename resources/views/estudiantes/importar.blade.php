@extends('layouts.app')
@section('title', 'Importar estudiantes')

@section('content')
    <nav class="crumb" aria-label="Ruta">
        <a href="{{ url('/dashboard') }}">Inicio</a> ›
        @if ($esDocente)<a href="{{ route('docente.materias') }}">Materias</a>@else<a href="{{ route('estudiantes.index') }}">Estudiantes</a>@endif
        › <b>Importar</b>
    </nav>

    <div class="head">
        <div>
            <h1>Importar estudiantes desde CSV o Excel</h1>
            <p>Sube una lista de estudiantes y asígnalos de una sola vez a una materia, grupo y docente.</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <a class="btn ghost" href="{{ route('estudiantes.plantilla') }}">⬇ Descargar plantilla CSV</a>
            <a class="btn ghost" href="{{ $esDocente ? route('docente.materias') : route('estudiantes.index') }}">‹ Volver</a>
        </div>
    </div>

    @if (session('status')) <div class="flash" role="status">{{ session('status') }}</div> @endif

    @error('importacion')
        <div class="flash err" role="alert" style="display:block">
            <b>No se importó nada porque el archivo tiene errores. Corrígelos y vuelve a subirlo:</b>
            <ul style="margin:8px 0 0 18px">
                @foreach ($errors->get('importacion')[0] as $linea)
                    <li>{{ $linea }}</li>
                @endforeach
            </ul>
        </div>
    @enderror

    @if ($errors->any() && ! $errors->has('importacion'))
        <div class="flash err" role="alert">{{ $errors->first() }}</div>
    @endif

    <div style="display:grid;grid-template-columns:minmax(300px,420px) 1fr;gap:20px;margin-top:20px;align-items:start" class="import-grid">
        {{-- Formulario --}}
        <form class="card" method="POST" action="{{ route('estudiantes.importar') }}" enctype="multipart/form-data" style="padding:20px;display:grid;gap:14px">
            @csrf
            <b style="color:var(--navy-900)">Destino de la importación</b>

            @unless ($esDocente)
            <div class="field">
                <label for="i-facultad">Facultad <span class="req">*</span></label>
                <select id="i-facultad" name="facultad" required>
                    <option value="">Seleccione la facultad…</option>
                    @foreach (array_keys($facultades) as $f)
                        <option value="{{ $f }}" @selected(old('facultad') === $f)>{{ $f }}</option>
                    @endforeach
                </select>
                @error('facultad')<p class="err">{{ $message }}</p>@enderror
            </div>

            <div class="field">
                <label for="i-carrera">Carrera <span class="req">*</span></label>
                <select id="i-carrera" name="carrera" required>
                    <option value="">Primero elija la facultad…</option>
                </select>
                @error('carrera')<p class="err">{{ $message }}</p>@enderror
            </div>

            @endunless

            <div class="field">
                <label for="i-materia">Materia <span class="req">*</span></label>
                <select id="i-materia" name="materia_id" required>
                    <option value="">{{ $esDocente ? 'Seleccione la materia…' : 'Primero elija la carrera…' }}</option>
                </select>
                @error('materia_id')<p class="err">{{ $message }}</p>@enderror
            </div>

            <div class="field">
                <label for="i-grupo">Grupo <span class="req">*</span></label>
                <select id="i-grupo" name="grupo_id" required>
                    <option value="">Primero elija la materia…</option>
                </select>
                @error('grupo_id')<p class="err">{{ $message }}</p>@enderror
            </div>

            <div class="field">
                <label for="i-archivo">Archivo CSV o Excel (.xlsx) <span class="req">*</span></label>
                <input id="i-archivo" type="file" name="archivo" accept=".csv,.txt,.xlsx" required>
                @error('archivo')<p class="err">{{ $message }}</p>@enderror
            </div>

            <button type="submit" class="btn block"><svg class="i sm"><use href="#i-check"/></svg> Validar e importar</button>
        </form>

        {{-- Instrucciones --}}
        <section class="card" style="padding:20px;display:grid;gap:14px">
            <div>
                <b style="color:var(--navy-900)">Formato del archivo</b>
                <p class="muted" style="margin-top:4px">
                    La primera fila debe ser el encabezado, con exactamente estas columnas, separadas por coma o punto y coma (la plantilla ya viene lista):
                </p>
            </div>
            <div class="scroll">
                <table style="min-width:420px">
                    <thead><tr><th>codigo_universitario</th><th>ci</th><th>ci_complemento</th><th>nombres</th><th>apellidos</th></tr></thead>
                    <tbody>
                        <tr><td class="num">202400034</td><td class="num">7123456</td><td>A1</td><td>Gabriel Fernando</td><td>Romero Silva</td></tr>
                        <tr><td class="num">202400041</td><td class="num">8456123</td><td></td><td>Valeria</td><td>Gutiérrez Rojas</td></tr>
                    </tbody>
                </table>
            </div>
            <ul class="muted" style="margin-left:18px;line-height:1.8">
                <li><b>codigo_universitario:</b> exactamente 9 dígitos (empieza con el año).</li>
                <li><b>ci_complemento:</b> opcional, hasta 2 caracteres (letras y/o números); puede ir vacío.</li>
                <li><b>ci:</b> solo números, entre 5 y 10 dígitos.</li>
                <li><b>nombres</b> y <b>apellidos:</b> solo letras.</li>
                <li>El correo institucional se genera solo, a partir del código universitario.</li>
            </ul>
            <div class="banner" style="margin-top:0">
                <span class="ic"><svg class="i"><use href="#i-info"/></svg></span>
                <div><b>Validación todo o nada</b>
                    <small>Si una sola fila tiene un error, no se importa ningún estudiante. El sistema te indica exactamente en qué fila y columna está cada error para que los corrijas y vuelvas a intentar.</small></div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
<script>
    const FACULTADES = @json($facultades);
    const MATERIAS = @json($materias);  // [{id, nombre, sigla, carrera}, ...]
    const GRUPOS = @json($grupos);      // [{id, nombre, materia_id, docente: {nombre, apellido} | null}, ...]

    const ES_DOCENTE = @json($esDocente);
    const selFacultad = document.getElementById('i-facultad');
    const selCarrera = document.getElementById('i-carrera');
    const selMateria = document.getElementById('i-materia');
    const selGrupo = document.getElementById('i-grupo');

    function cargarCarreras() {
        const lista = FACULTADES[selFacultad.value] || [];
        selCarrera.innerHTML = '<option value="">' + (lista.length ? 'Seleccione la carrera…' : 'Primero elija la facultad…') + '</option>'
            + lista.map(c => `<option value="${c}">${c}</option>`).join('');
        cargarMaterias();
    }

    function cargarMaterias() {
        const lista = ES_DOCENTE ? MATERIAS : MATERIAS.filter(m => m.carrera === selCarrera.value);
        selMateria.innerHTML = '<option value="">' + (lista.length ? 'Seleccione la materia…' : 'No hay materias para esa carrera…') + '</option>'
            + lista.map(m => `<option value="${m.id}">${m.nombre}${m.sigla ? ' (' + m.sigla + ')' : ''}</option>`).join('');
        cargarGrupos();
    }

    function cargarGrupos() {
        const lista = GRUPOS.filter(g => String(g.materia_id) === selMateria.value);
        selGrupo.innerHTML = '<option value="">' + (lista.length ? 'Seleccione el grupo…' : 'No hay grupos para esa materia…') + '</option>'
            + lista.map(g => {
                const doc = g.docente ? ` — ${g.docente.nombre} ${g.docente.apellido ?? ''}`.trim() : ' — sin docente';
                return `<option value="${g.id}">${g.nombre}${doc}</option>`;
            }).join('');
    }

    if (!ES_DOCENTE) {
        selFacultad.addEventListener('change', cargarCarreras);
        selCarrera.addEventListener('change', cargarMaterias);
    }
    selMateria.addEventListener('change', cargarGrupos);

    // Si el formulario vuelve tras un error, se restauran las selecciones anteriores.
    const previos = { facultad: @json(old('facultad')), carrera: @json(old('carrera')), materia_id: @json(old('materia_id')), grupo_id: @json(old('grupo_id')) };
    if (ES_DOCENTE) {
        cargarMaterias();
        if (previos.materia_id) { selMateria.value = previos.materia_id; cargarGrupos(); }
        if (previos.grupo_id) { selGrupo.value = previos.grupo_id; }
    } else if (previos.facultad) {
        selFacultad.value = previos.facultad;
        cargarCarreras();
        if (previos.carrera) { selCarrera.value = previos.carrera; cargarMaterias(); }
        if (previos.materia_id) { selMateria.value = previos.materia_id; cargarGrupos(); }
        if (previos.grupo_id) { selGrupo.value = previos.grupo_id; }
    }
</script>
<style>@media (max-width:1000px){.import-grid{grid-template-columns:1fr !important}}</style>
@endpush
