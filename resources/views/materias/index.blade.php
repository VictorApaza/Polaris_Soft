@extends('layouts.app')
@section('title', 'Materias')

@section('content')
    <nav class="crumb" aria-label="Ruta"><a href="{{ url('/dashboard') }}">Inicio</a> › <b>Materias</b></nav>

    <div class="head">
        <div>
            <h1>Gestión de materias</h1>
            <p>Registrar las materias y, desplegando cada una, administrar sus grupos y docentes.</p>
        </div>
        <button type="button" class="btn" id="btn-nuevo"><svg class="i"><use href="#i-plus"/></svg> Nueva materia</button>
    </div>

    @include('partials.flash')

    <section class="card" style="margin-top:20px">
        <div class="scroll">
            <table>
                <thead><tr><th>Sigla</th><th>Nombre de la asignatura</th><th>Facultad</th><th>Carrera</th><th>Grupos</th><th style="text-align:right">Acciones</th></tr></thead>
                <tbody>
                @forelse ($materias as $m)
                    @php $url = route('materias.update', $m); @endphp
                    <tr>
                        <td class="strong num">{{ $m->sigla ?: '—' }}</td>
                        <td class="strong">{{ $m->nombre }}</td>
                        <td>{{ $m->facultad ?: 'Sin facultad asignada' }}</td>
                        <td>{{ $m->carrera ?: 'Sin carrera asignada' }}</td>
                        <td>
                            <button type="button" class="btn-desplegar" data-grupos="{{ $m->id }}" aria-expanded="false" aria-controls="grupos-{{ $m->id }}">
                                {{ $m->grupos_count }} {{ $m->grupos_count === 1 ? 'grupo' : 'grupos' }}
                                <svg class="i sm chev"><use href="#i-arrow"/></svg>
                            </button>
                        </td>
                        <td>
                            <div class="actions">
                                <button type="button" class="icon-btn" title="Editar" aria-label="Editar"
                                        data-edit="{{ json_encode($m->only(['nombre', 'sigla', 'facultad', 'carrera'])) }}" data-url="{{ $url }}" data-id="{{ $m->id }}">
                                    <svg class="i"><use href="#i-edit"/></svg></button>
                                <form method="POST" action="{{ $url }}" onsubmit="return confirm('¿Eliminar esta materia? También se eliminarán sus grupos y asignaciones.')">
                                    @csrf @method('DELETE')
                                    <button class="icon-btn danger" type="submit" title="Eliminar" aria-label="Eliminar"><svg class="i"><use href="#i-trash"/></svg></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <tr class="fila-grupos" id="grupos-{{ $m->id }}" hidden>
                        <td colspan="6">
                            <div class="grupos-box">
                                @forelse ($m->grupos as $g)
                                    @php $urlG = route('grupos.update', $g); @endphp
                                    <div class="g-fila">
                                        <b>{{ $g->nombre }}</b>
                                        <span class="sub">Docente: {{ $g->docente?->nombre_completo ?? 'Sin docente' }}</span>
                                        <div class="actions">
                                            <button type="button" class="icon-btn" title="Editar grupo" aria-label="Editar grupo"
                                                    data-edit-grupo="{{ json_encode(['nombre' => $g->nombre, 'materia_id' => $g->materia_id, 'docente_id' => $g->docente_id]) }}"
                                                    data-url="{{ $urlG }}" data-id="{{ $g->id }}">
                                                <svg class="i"><use href="#i-edit"/></svg></button>
                                            <form method="POST" action="{{ $urlG }}" onsubmit="return confirm('¿Eliminar este grupo? Los estudiantes asignados a él perderán el grupo.')">
                                                @csrf @method('DELETE')
                                                <button class="icon-btn danger" type="submit" title="Eliminar grupo" aria-label="Eliminar grupo"><svg class="i"><use href="#i-trash"/></svg></button>
                                            </form>
                                        </div>
                                    </div>
                                @empty
                                    <span class="muted">Esta materia todavía no tiene grupos.</span>
                                @endforelse
                                <div>
                                    <button type="button" class="btn ghost" data-nuevo-grupo="{{ $m->id }}" style="padding:7px 12px">
                                        <svg class="i sm"><use href="#i-plus"/></svg> Agregar grupo a {{ $m->nombre }}</button>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">Aún no hay asignaturas registradas.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="foot"><span>{{ $materias->count() }} materias registradas</span></div>
    </section>

    <dialog id="modal-materia">
        <form method="POST" action="{{ route('materias.store') }}">
            @csrf
            <input type="hidden" name="_method" value="PUT" disabled>
            <input type="hidden" name="_form" value="materia">
            <input type="hidden" name="_editing" value="{{ old('_editing') }}">
            <div class="m-head">
                <span class="ic"><svg class="i"><use href="#i-exams"/></svg></span>
                <div><b data-title>Registrar materia</b><small>Datos de la asignatura</small></div>
                <button type="button" class="icon-btn x" aria-label="Cerrar" onclick="this.closest('dialog').close()"><svg class="i"><use href="#i-x"/></svg></button>
            </div>
            <div class="m-body">
                <div class="field">
                    <label for="m-nombre">Nombre <span class="req">*</span></label>
                    <input id="m-nombre" name="nombre" value="{{ old('nombre') }}" placeholder="Cálculo Multivariable" pattern="[\p{L}\s]+" oninput="soloLetras(this)" required>
                    @error('nombre')<p class="err">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="m-sigla">Sigla <span class="req">*</span></label>
                    <input id="m-sigla" name="sigla" value="{{ old('sigla') }}" placeholder="MAT-201" required>
                    @error('sigla')<p class="err">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="m-facultad">Facultad <span class="req">*</span></label>
                    <select id="m-facultad" name="facultad" required>
                        <option value="">Seleccione la facultad…</option>
                        @foreach ($facultades as $facultad => $lista)
                            <option value="{{ $facultad }}" @selected(old('facultad') === $facultad)>{{ $facultad }}</option>
                        @endforeach
                    </select>
                    @error('facultad')<p class="err">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="m-carrera">Carrera <span class="req">*</span></label>
                    <select id="m-carrera" name="carrera" required>
                        <option value="">Seleccione la carrera…</option>
                        @foreach ($carreras as $carrera)
                            <option value="{{ $carrera }}" @selected(old('carrera') === $carrera)>{{ $carrera }}</option>
                        @endforeach
                    </select>
                    @error('carrera')<p class="err">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="m-foot">
                <button type="button" class="btn ghost" onclick="this.closest('dialog').close()">Cancelar</button>
                <button type="submit" class="btn" data-submit><svg class="i sm"><use href="#i-check"/></svg> Guardar materia</button>
            </div>
        </form>
    </dialog>
    <dialog id="modal-grupo">
        <form method="POST" action="{{ route('grupos.store') }}">
            @csrf
            <input type="hidden" name="_method" value="PUT" disabled>
            <input type="hidden" name="_form" value="grupo">
            <input type="hidden" name="_editing" value="{{ old('_form') === 'grupo' ? old('_editing') : '' }}">
            <input type="hidden" name="materia_id" value="{{ old('materia_id') }}">
            <div class="m-head">
                <span class="ic"><svg class="i"><use href="#i-dashboard"/></svg></span>
                <div><b data-title>Registrar grupo</b><small>Nombre y docente a cargo</small></div>
                <button type="button" class="icon-btn x" aria-label="Cerrar" onclick="this.closest('dialog').close()"><svg class="i"><use href="#i-x"/></svg></button>
            </div>
            <div class="m-body">
                <div class="field">
                    <label for="g-nombre">Nombre del grupo <span class="req">*</span></label>
                    <input id="g-nombre" name="nombre" value="{{ old('_form') === 'grupo' ? old('nombre') : '' }}" placeholder="Grupo A" required>
                    @if (old('_form') === 'grupo') @error('nombre')<p class="err">{{ $message }}</p>@enderror @endif
                </div>
                <div class="field">
                    <label for="g-docente">Docente <i>Opcional</i></label>
                    <select id="g-docente" name="docente_id">
                        <option value="">Sin docente</option>
                        @foreach ($docentes as $d)
                            <option value="{{ $d->id }}" @selected(old('_form') === 'grupo' && (string) old('docente_id') === (string) $d->id)>{{ $d->nombre_completo }}</option>
                        @endforeach
                    </select>
                    @if (old('_form') === 'grupo') @error('docente_id')<p class="err">{{ $message }}</p>@enderror @endif
                </div>
            </div>
            <div class="m-foot">
                <button type="button" class="btn ghost" onclick="this.closest('dialog').close()">Cancelar</button>
                <button type="submit" class="btn" data-submit><svg class="i sm"><use href="#i-check"/></svg> Guardar grupo</button>
            </div>
        </form>
    </dialog>
@endsection

@push('scripts')
<script>
    const MODAL = 'modal-materia';
    const MODAL_GRUPO = 'modal-grupo';

    // ---- Grupos desplegables por materia (se recuerda cuáles estaban abiertas) ----
    const CLAVE_ABIERTAS = 'sciem_materias_abiertas';
    let abiertas = [];
    try { abiertas = JSON.parse(sessionStorage.getItem(CLAVE_ABIERTAS) || '[]'); } catch (e) { abiertas = []; }
    function pintarAbiertas() {
        document.querySelectorAll('[data-grupos]').forEach(b => {
            const abierta = abiertas.includes(b.dataset.grupos);
            b.setAttribute('aria-expanded', String(abierta));
            document.getElementById('grupos-' + b.dataset.grupos).hidden = !abierta;
        });
    }
    document.querySelectorAll('[data-grupos]').forEach(b => b.addEventListener('click', () => {
        const id = b.dataset.grupos;
        abiertas = abiertas.includes(id) ? abiertas.filter(x => x !== id) : [...abiertas, id];
        sessionStorage.setItem(CLAVE_ABIERTAS, JSON.stringify(abiertas));
        pintarAbiertas();
    }));
    pintarAbiertas();

    document.querySelectorAll('[data-nuevo-grupo]').forEach(b => b.addEventListener('click', () =>
        abrirForm(MODAL_GRUPO, { url: '{{ route('grupos.store') }}', method: 'POST', title: 'Registrar grupo', values: { materia_id: b.dataset.nuevoGrupo } })));
    document.querySelectorAll('[data-edit-grupo]').forEach(b => b.addEventListener('click', () =>
        abrirForm(MODAL_GRUPO, { url: b.dataset.url, method: 'PUT', editing: b.dataset.id, values: JSON.parse(b.dataset.editGrupo), title: 'Editar grupo' })));
    @if ($errors->any() && old('_form') === 'grupo')
        abrirForm(MODAL_GRUPO, { keep: true,
            url: '{{ old('_editing') ? url('grupos/'.old('_editing')) : route('grupos.store') }}',
            method: '{{ old('_editing') ? 'PUT' : 'POST' }}',
            title: '{{ old('_editing') ? 'Editar grupo' : 'Registrar grupo' }}' });
    @endif

    const FACULTADES = @json($facultades);
    const selFacultad = document.getElementById('m-facultad');
    const selCarrera = document.getElementById('m-carrera');
    const TODAS_LAS_CARRERAS = [...selCarrera.options].filter(o => o.value).map(o => o.value);

    /** Muestra solo las carreras de la facultad elegida (si no hay facultad, ninguna). */
    function filtrarCarreras() {
        const permitidas = FACULTADES[selFacultad.value] || [];
        const actual = selCarrera.value;
        selCarrera.innerHTML = '<option value="">Seleccione la carrera…</option>';
        TODAS_LAS_CARRERAS.filter(c => permitidas.includes(c)).forEach(c => {
            const o = new Option(c, c);
            o.selected = (c === actual);
            selCarrera.add(o);
        });
    }
    selFacultad.addEventListener('change', () => { selCarrera.value = ''; filtrarCarreras(); });
    document.getElementById('btn-nuevo').addEventListener('click', () =>
        { abrirForm(MODAL, { url: '{{ route('materias.store') }}', method: 'POST', title: 'Registrar materia' }); filtrarCarreras(); });
    document.querySelectorAll('[data-edit]').forEach(b => b.addEventListener('click', () =>
        { const v = JSON.parse(b.dataset.edit); abrirForm(MODAL, { url: b.dataset.url, method: 'PUT', editing: b.dataset.id, values: { nombre: v.nombre, sigla: v.sigla, facultad: v.facultad }, title: 'Editar materia' }); filtrarCarreras(); selCarrera.value = v.carrera || ''; }));
    @if ($errors->any() && old('_form') === 'materia')
        abrirForm(MODAL, { keep: true,
            url: '{{ old('_editing') ? url('materias/'.old('_editing')) : route('materias.store') }}',
            method: '{{ old('_editing') ? 'PUT' : 'POST' }}',
            title: '{{ old('_editing') ? 'Editar materia' : 'Registrar materia' }}' });
        filtrarCarreras();
    @endif
</script>
@endpush
