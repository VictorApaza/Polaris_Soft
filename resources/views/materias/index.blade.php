@extends('layouts.app')
@section('title', 'Materias')

@section('content')
    <nav class="crumb" aria-label="Ruta"><a href="{{ url('/dashboard') }}">Inicio</a> › <b>Materias</b></nav>

    <div class="head">
        <div>
            <h1>Gestión de materias</h1>
            <p>Registrar las materias que se evalúan y a las que se asignan los estudiantes.</p>
        </div>
        <button type="button" class="btn" id="btn-nuevo"><svg class="i"><use href="#i-plus"/></svg> Nueva materia</button>
    </div>

    @include('partials.flash')

    <section class="card" style="margin-top:20px">
        <div class="scroll">
            <table>
                <thead><tr><th>Sigla</th><th>Nombre de la asignatura</th><th>Carrera</th><th>Grupos</th><th style="text-align:right">Acciones</th></tr></thead>
                <tbody>
                @forelse ($materias as $m)
                    @php $url = route('materias.update', $m); @endphp
                    <tr>
                        <td class="strong num">{{ $m->sigla ?: '—' }}</td>
                        <td class="strong">{{ $m->nombre }}</td>
                        <td>{{ $m->carrera ?: 'Sin carrera asignada' }}</td>
                        <td class="num">{{ $m->grupos_count }}</td>
                        <td>
                            <div class="actions">
                                <button type="button" class="icon-btn" title="Editar" aria-label="Editar"
                                        data-edit="{{ json_encode($m->only(['nombre', 'sigla', 'carrera'])) }}" data-url="{{ $url }}" data-id="{{ $m->id }}">
                                    <svg class="i"><use href="#i-edit"/></svg></button>
                                <form method="POST" action="{{ $url }}" onsubmit="return confirm('¿Eliminar esta materia? También se eliminarán sus grupos y asignaciones.')">
                                    @csrf @method('DELETE')
                                    <button class="icon-btn danger" type="submit" title="Eliminar" aria-label="Eliminar"><svg class="i"><use href="#i-trash"/></svg></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">Aún no hay asignaturas registradas.</td></tr>
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
@endsection

@push('scripts')
<script>
    const MODAL = 'modal-materia';
    document.getElementById('btn-nuevo').addEventListener('click', () =>
        abrirForm(MODAL, { url: '{{ route('materias.store') }}', method: 'POST', title: 'Registrar materia' }));
    document.querySelectorAll('[data-edit]').forEach(b => b.addEventListener('click', () =>
        abrirForm(MODAL, { url: b.dataset.url, method: 'PUT', editing: b.dataset.id, values: JSON.parse(b.dataset.edit), title: 'Editar materia' })));
    @if ($errors->any() && old('_form') === 'materia')
        abrirForm(MODAL, { keep: true,
            url: '{{ old('_editing') ? url('materias/'.old('_editing')) : route('materias.store') }}',
            method: '{{ old('_editing') ? 'PUT' : 'POST' }}',
            title: '{{ old('_editing') ? 'Editar materia' : 'Registrar materia' }}' });
    @endif
</script>
@endpush
