@extends('layouts.app')
@section('title', 'Ambientes')

@section('content')
    <nav class="crumb" aria-label="Ruta"><a href="{{ url('/dashboard') }}">Inicio</a> › <b>Ambientes</b></nav>

    <div class="head">
        <div>
            <h1>Gestión de ambientes</h1>
            <p>Registra las aulas y su capacidad para usarlas al programar exámenes.</p>
        </div>
        <button type="button" class="btn" id="btn-nuevo"><svg class="i"><use href="#i-plus"/></svg> Nuevo ambiente</button>
    </div>

    @include('partials.flash')

    <section class="card" style="margin-top:20px">
        <div class="scroll">
            <table>
                <thead><tr><th>Ambiente / Aula</th><th>Capacidad</th><th style="text-align:right">Acciones</th></tr></thead>
                <tbody>
                @forelse ($ambientes as $ambiente)
                    @php $url = route('ambientes.update', $ambiente); @endphp
                    <tr>
                        <td class="strong">{{ $ambiente->nombre }}</td>
                        <td class="num">{{ $ambiente->capacidad }} personas</td>
                        <td>
                            <div class="actions">
                                <button type="button" class="icon-btn" title="Editar" aria-label="Editar"
                                        data-edit="{{ json_encode($ambiente->only(['nombre', 'capacidad'])) }}" data-url="{{ $url }}" data-id="{{ $ambiente->id }}">
                                    <svg class="i"><use href="#i-edit"/></svg></button>
                                <form method="POST" action="{{ route('ambientes.destroy', $ambiente) }}">
                                    @csrf @method('DELETE')
                                    <button class="icon-btn danger" type="button" title="Eliminar" aria-label="Eliminar"
                                            onclick="confirmarEliminar(this.closest('form'), '¿Eliminar este ambiente?')">
                                        <svg class="i"><use href="#i-trash"/></svg></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="empty">Aún no hay ambientes registrados.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="foot"><span>{{ $ambientes->count() }} ambientes registrados</span></div>
    </section>

    <dialog id="modal-ambiente">
        <form method="POST" action="{{ route('ambientes.store') }}">
            @csrf
            <input type="hidden" name="_method" value="PUT" disabled>
            <input type="hidden" name="_form" value="ambiente">
            <input type="hidden" name="_editing" value="{{ old('_editing') }}">
            <div class="m-head">
                <span class="ic"><svg class="i"><use href="#i-building"/></svg></span>
                <div><b data-title>Registrar ambiente</b><small>Aula y capacidad disponible</small></div>
                <button type="button" class="icon-btn x" aria-label="Cerrar" onclick="this.closest('dialog').close()"><svg class="i"><use href="#i-x"/></svg></button>
            </div>
            <div class="m-body">
                <div class="field">
                    <label for="a-nombre">Ambiente / Aula <span class="req">*</span></label>
                    <input id="a-nombre" name="nombre" value="{{ old('nombre') }}" placeholder="Edificio Central 102A" maxlength="100" required>
                    @error('nombre')<p class="err">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="a-capacidad">Capacidad <span class="req">*</span></label>
                    <input id="a-capacidad" type="number" name="capacidad" value="{{ old('capacidad') }}" min="1" max="5000" placeholder="120" required>
                    @error('capacidad')<p class="err">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="m-foot">
                <button type="button" class="btn ghost" onclick="this.closest('dialog').close()">Cancelar</button>
                <button type="submit" class="btn" data-submit><svg class="i sm"><use href="#i-check"/></svg> Guardar ambiente</button>
            </div>
        </form>
    </dialog>
@endsection

@push('scripts')
<script>
    const MODAL = 'modal-ambiente';
    document.getElementById('btn-nuevo').addEventListener('click', () =>
        abrirForm(MODAL, { url: '{{ route('ambientes.store') }}', method: 'POST', title: 'Registrar ambiente' }));
    document.querySelectorAll('[data-edit]').forEach(b => b.addEventListener('click', () =>
        abrirForm(MODAL, { url: b.dataset.url, method: 'PUT', editing: b.dataset.id, values: JSON.parse(b.dataset.edit), title: 'Editar ambiente' })));
    @if ($errors->any() && old('_form') === 'ambiente')
        abrirForm(MODAL, { keep: true,
            url: '{{ old('_editing') ? url('ambientes/'.old('_editing')) : route('ambientes.store') }}',
            method: '{{ old('_editing') ? 'PUT' : 'POST' }}',
            title: '{{ old('_editing') ? 'Editar ambiente' : 'Registrar ambiente' }}' });
    @endif
</script>
@endpush
