@extends('layouts.app')
@section('title', 'Docentes')

@section('content')
    <nav class="crumb" aria-label="Ruta"><a href="{{ url('/dashboard') }}">Inicio</a> › <b>Docentes</b></nav>

    <div class="head">
        <div>
            <h1>Gestión de docentes</h1>
            <p>Registrar a los docentes que pueden ser asignados a grupos y estudiantes.</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <button type="button" class="btn ghost" id="btn-importar"><svg class="i"><use href="#i-plus"/></svg> Importar Excel / CSV</button>
            <button type="button" class="btn" id="btn-nuevo"><svg class="i"><use href="#i-user-plus"/></svg> Nuevo docente</button>
        </div>
    </div>

    @include('partials.flash')

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
    @error('archivo')<div class="flash err" role="alert">{{ $message }}</div>@enderror

    <section class="card" style="margin-top:20px">
        <div class="scroll">
            <table>
                <thead><tr><th>Nombre completo</th><th>Grado</th><th>CI</th><th>Grupos a cargo</th><th style="text-align:right">Acciones</th></tr></thead>
                <tbody>
                @forelse ($docentes as $d)
                    @php
                        $payload = $d->only(['nombre', 'apellido', 'grado', 'ci', 'ci_complemento', 'user_id']);
                        $url = route('docentes.update', $d);
                    @endphp
                    <tr>
                        <td class="strong">{{ $d->nombre_completo }}</td>
                        <td>{{ $d->grado }}</td>
                        <td class="num">{{ $d->carnet_completo }}</td>
                        <td class="num">{{ $d->grupos_count }}</td>
                        <td>
                            <div class="actions">
                                <button type="button" class="icon-btn" title="Editar" aria-label="Editar"
                                        data-edit="{{ json_encode($payload) }}" data-url="{{ $url }}" data-id="{{ $d->id }}">
                                    <svg class="i"><use href="#i-edit"/></svg></button>
                                <form method="POST" action="{{ $url }}" onsubmit="return confirm('¿Eliminar a este docente? Sus grupos quedarán sin docente.')">
                                    @csrf @method('DELETE')
                                    <button class="icon-btn danger" type="submit" title="Eliminar" aria-label="Eliminar"><svg class="i"><use href="#i-trash"/></svg></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">Aún no hay docentes registrados.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="foot"><span>{{ $docentes->count() }} docentes registrados</span></div>
    </section>

    <dialog id="modal-docente">
        <form method="POST" action="{{ route('docentes.store') }}">
            @csrf
            <input type="hidden" name="_method" value="PUT" disabled>
            <input type="hidden" name="_form" value="docente">
            <input type="hidden" name="_editing" value="{{ old('_editing') }}">
            <div class="m-head">
                <span class="ic"><svg class="i"><use href="#i-user-plus"/></svg></span>
                <div><b data-title>Registrar docente</b><small>Datos del docente</small></div>
                <button type="button" class="icon-btn x" aria-label="Cerrar" onclick="this.closest('dialog').close()"><svg class="i"><use href="#i-x"/></svg></button>
            </div>
            <div class="m-body">
                <div class="row2">
                    <div class="field">
                        <label for="d-nombre">Nombre <span class="req">*</span></label>
                        <input id="d-nombre" name="nombre" value="{{ old('nombre') }}" placeholder="María" pattern="[\p{L}\s]+" oninput="soloLetras(this)" required>
                        @error('nombre')<p class="err">{{ $message }}</p>@enderror
                    </div>
                    <div class="field">
                        <label for="d-apellido">Apellido <span class="req">*</span></label>
                        <input id="d-apellido" name="apellido" value="{{ old('apellido') }}" placeholder="Ramos" pattern="[\p{L}\s]+" oninput="soloLetras(this)" required>
                        @error('apellido')<p class="err">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="field">
                    <label for="d-grado">Grado académico <span class="req">*</span></label>
                    <select id="d-grado" name="grado" data-default="Licenciado(a)" required>
                        <option value="">Seleccione el grado…</option>
                        @foreach ($grados as $g)
                            <option value="{{ $g }}" @selected(old('grado') === $g)>{{ $g }}</option>
                        @endforeach
                    </select>
                    @error('grado')<p class="err">{{ $message }}</p>@enderror
                </div>
                <div class="row2">
                    <div class="field">
                        <label for="d-ci">CI <span class="req">*</span> <i>Solo números, 5 a 10 dígitos</i></label>
                        <input id="d-ci" name="ci" value="{{ old('ci') }}" placeholder="4521367" inputmode="numeric" pattern="[0-9]{5,10}" minlength="5" maxlength="10" oninput="soloNumeros(this)" required>
                        @error('ci')<p class="err">{{ $message }}</p>@enderror
                    </div>
                    <div class="field">
                        <label for="d-complemento">Complemento <i>opcional, máx. 2</i></label>
                        <input id="d-complemento" name="ci_complemento" value="{{ old('ci_complemento') }}" placeholder="1A" maxlength="2" pattern="[A-Za-z0-9]{1,2}" oninput="this.value=this.value.replace(/[^A-Za-z0-9]/g,'').toUpperCase()">
                        @error('ci_complemento')<p class="err">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="field">
                    <label for="d-user">Cuenta de usuario <i>opcional · para que vea solo sus materias</i></label>
                    <select id="d-user" name="user_id">
                        <option value="">Sin vincular (se busca por nombre y apellido)</option>
                        @foreach ($usuariosDocentes as $u)
                            <option value="{{ $u->id }}" @selected((string) old('user_id') === (string) $u->id)>{{ $u->name }} · {{ $u->email }}</option>
                        @endforeach
                    </select>
                    @error('user_id')<p class="err">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="m-foot">
                <button type="button" class="btn ghost" onclick="this.closest('dialog').close()">Cancelar</button>
                <button type="submit" class="btn" data-submit><svg class="i sm"><use href="#i-check"/></svg> Guardar docente</button>
            </div>
        </form>
    </dialog>

    <dialog id="modal-importar">
        <form method="POST" action="{{ route('docentes.importar') }}" enctype="multipart/form-data">
            @csrf
            <div class="m-head">
                <span class="ic"><svg class="i"><use href="#i-plus"/></svg></span>
                <div><b>Importar docentes</b><small>Archivo CSV o Excel (.xlsx)</small></div>
                <button type="button" class="icon-btn x" aria-label="Cerrar" onclick="this.closest('dialog').close()"><svg class="i"><use href="#i-x"/></svg></button>
            </div>
            <div class="m-body">
                <p class="muted">La primera fila debe ser el encabezado, con estas columnas:</p>
                <p><code>nombres · apellidos · grado · ci · ci_complemento · correo</code></p>
                <ul class="muted" style="margin:8px 0 0 18px;line-height:1.7">
                    <li><b>grado:</b> Licenciado(a), Ingeniero(a), Doctor(a), Magíster, Técnico o Bachiller (también se acepta Lic., Ing., Dr.).</li>
                    <li><b>ci:</b> solo números (5 a 10 dígitos). <b>ci_complemento:</b> opcional, hasta 2 caracteres.</li>
                    <li><b>correo:</b> opcional; si ya existe la cuenta del docente, se vincula para que vea solo sus materias.</li>
                    <li>Si una sola fila tiene error no se importa ninguna; el sistema te dice la fila y la columna.</li>
                </ul>
                <p style="margin-top:10px"><a href="{{ route('docentes.plantilla') }}">⬇ Descargar plantilla CSV</a></p>
                <div class="field" style="margin-top:12px">
                    <label for="d-archivo">Archivo <span class="req">*</span></label>
                    <input id="d-archivo" type="file" name="archivo" accept=".csv,.txt,.xlsx" required>
                </div>
            </div>
            <div class="m-foot">
                <button type="button" class="btn ghost" onclick="this.closest('dialog').close()">Cancelar</button>
                <button type="submit" class="btn" data-submit><svg class="i sm"><use href="#i-check"/></svg> Validar e importar</button>
            </div>
        </form>
    </dialog>
@endsection

@push('scripts')
<script>
    const MODAL = 'modal-docente';
    document.getElementById('btn-nuevo').addEventListener('click', () =>
        abrirForm(MODAL, { url: '{{ route('docentes.store') }}', method: 'POST', title: 'Registrar docente' }));
    document.getElementById('btn-importar').addEventListener('click', () => document.getElementById('modal-importar').showModal());
    document.querySelectorAll('[data-edit]').forEach(b => b.addEventListener('click', () =>
        abrirForm(MODAL, { url: b.dataset.url, method: 'PUT', editing: b.dataset.id, values: JSON.parse(b.dataset.edit), title: 'Editar docente' })));
    @if ($errors->any() && old('_form') === 'docente')
        abrirForm(MODAL, { keep: true,
            url: '{{ old('_editing') ? url('docentes/'.old('_editing')) : route('docentes.store') }}',
            method: '{{ old('_editing') ? 'PUT' : 'POST' }}',
            title: '{{ old('_editing') ? 'Editar docente' : 'Registrar docente' }}' });
    @endif
</script>
@endpush
