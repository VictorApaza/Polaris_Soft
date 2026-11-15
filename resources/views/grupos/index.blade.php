@extends('layouts.app')
@section('title', 'Grupos')

@section('content')
    <nav class="crumb" aria-label="Ruta"><a href="{{ url('/dashboard') }}">Inicio</a> › <b>Grupos</b></nav>

    <div class="head">
        <div>
            <h1>Gestión de grupos</h1>
            <p>Cada grupo pertenece a una materia y puede tener un docente a cargo.</p>
        </div>
        <button type="button" class="btn" id="btn-nuevo"><svg class="i"><use href="#i-plus"/></svg> Nuevo grupo</button>
    </div>

    @include('partials.flash')

    <section class="card" style="margin-top:20px">
        <div class="scroll">
            <table>
                <thead><tr><th>Grupo</th><th>Materia</th><th>Docente a cargo</th><th class="t-right">Acciones</th></tr></thead>
                <tbody>
                @forelse ($grupos as $g)
                    @php $url = route('grupos.update', $g); @endphp
                    <tr>
                        <td class="strong">{{ $g->nombre }}</td>
                        <td>{{ $g->materia->nombre ?? '—' }}<span class="sub">{{ $g->materia?->sigla }}</span></td>
                        <td>{{ $g->docente->nombre_completo ?? 'Sin docente' }}</td>
                        <td>
                            <div class="actions">
                                <button type="button" class="icon-btn" title="Editar" aria-label="Editar"
                                        data-edit="{{ json_encode($g->only(['nombre', 'materia_id', 'docente_id'])) }}" data-url="{{ $url }}" data-id="{{ $g->id }}">
                                    <svg class="i"><use href="#i-edit"/></svg></button>
                                <form method="POST" action="{{ $url }}" onsubmit="return confirm('¿Eliminar este grupo? También se eliminarán sus asignaciones.')">
                                    @csrf @method('DELETE')
                                    <button class="icon-btn danger" type="submit" title="Eliminar" aria-label="Eliminar"><svg class="i"><use href="#i-trash"/></svg></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty">Aún no hay grupos registrados.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="foot"><span>{{ $grupos->count() }} grupos registrados</span></div>
    </section>

    <dialog id="modal-grupo">
        <form method="POST" action="{{ route('grupos.store') }}">
            @csrf
            <input type="hidden" name="_method" value="PUT" disabled>
            <input type="hidden" name="_form" value="grupo">
            <input type="hidden" name="_editing" value="{{ old('_editing') }}">
            <div class="m-head">
                <span class="ic"><svg class="i"><use href="#i-dashboard"/></svg></span>
                <div><b data-title>Registrar grupo</b><small>Materia y docente a cargo</small></div>
                <button type="button" class="icon-btn x" aria-label="Cerrar" onclick="this.closest('dialog').close()"><svg class="i"><use href="#i-x"/></svg></button>
            </div>
            <div class="m-body">
                <div class="field">
                    <label for="g-nombre">Nombre del grupo <span class="req">*</span></label>
                    <input id="g-nombre" name="nombre" value="{{ old('nombre') }}" placeholder="Grupo A" required>
                    @error('nombre')<p class="err">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="g-materia">Materia <span class="req">*</span></label>
                    <select id="g-materia" name="materia_id" required>
                        <option value="">Seleccione la materia…</option>
                        @foreach ($materias as $m)
                            <option value="{{ $m->id }}" @selected((string) old('materia_id') === (string) $m->id)>{{ $m->nombre }}{{ $m->sigla ? ' ('.$m->sigla.')' : '' }}</option>
                        @endforeach
                    </select>
                    @error('materia_id')<p class="err">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="g-docente">Docente <i>Opcional</i></label>
                    <select id="g-docente" name="docente_id">
                        <option value="">Sin docente</option>
                        @foreach ($docentes as $d)
                            <option value="{{ $d->id }}" @selected((string) old('docente_id') === (string) $d->id)>{{ $d->nombre_completo }}</option>
                        @endforeach
                    </select>
                    @error('docente_id')<p class="err">{{ $message }}</p>@enderror
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
    const MODAL = 'modal-grupo';
    document.getElementById('btn-nuevo').addEventListener('click', () =>
        abrirForm(MODAL, { url: '{{ route('grupos.store') }}', method: 'POST', title: 'Registrar grupo' }));
    document.querySelectorAll('[data-edit]').forEach(b => b.addEventListener('click', () =>
        abrirForm(MODAL, { url: b.dataset.url, method: 'PUT', editing: b.dataset.id, values: JSON.parse(b.dataset.edit), title: 'Editar grupo' })));
    @if ($errors->any() && old('_form') === 'grupo')
        abrirForm(MODAL, { keep: true,
            url: '{{ old('_editing') ? url('grupos/'.old('_editing')) : route('grupos.store') }}',
            method: '{{ old('_editing') ? 'PUT' : 'POST' }}',
            title: '{{ old('_editing') ? 'Editar grupo' : 'Registrar grupo' }}' });
    @endif
</script>
@endpush
