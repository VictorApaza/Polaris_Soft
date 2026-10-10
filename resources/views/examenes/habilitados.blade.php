@extends('layouts.app')
@section('title', 'Estudiantes habilitados')

@section('content')
    <nav class="crumb" aria-label="Ruta">
        <a href="{{ url('/dashboard') }}">Inicio</a> › <a href="{{ route('examenes.index') }}">Exámenes</a> › <b>Habilitados</b>
    </nav>

    <div class="head">
        <div>
            <h1>Estudiantes habilitados</h1>
            <p>{{ $examen->asignatura->nombre ?? $examen->materia->nombre ?? '—' }}{{ $examen->carrera ? ' · '.$examen->carrera : '' }} · {{ $examen->fecha->format('d/m/Y') }} {{ $examen->hora }} · {{ $examen->ambiente }}</p>
        </div>
        <a class="btn ghost" href="{{ route('examenes.index') }}">‹ Volver a exámenes</a>
    </div>

    @if (session('status')) <div class="flash" role="status">{{ session('status') }}</div> @endif
    @if ($errors->any()) <div class="flash err" role="alert">{{ $errors->first() }}</div> @endif

    {{-- Resumen + filtro por grupo --}}
    <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;margin-top:16px">
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <span class="badge activo">{{ $resumen['habilitado'] }} habilitado(s)</span>
            <span class="badge observado">{{ $resumen['inhabilitado'] }} inhabilitado(s)</span>
            <span class="badge inactivo">{{ $resumen['sin_evaluar'] }} sin evaluar</span>
        </div>
        @if ($usaGrupos)
        <form method="GET" action="{{ route('habilitaciones.index', $examen) }}" class="field" style="margin:0;min-width:240px">
            <label for="filtro-grupo">Grupo</label>
            <select id="filtro-grupo" name="grupo_id" onchange="this.form.submit()">
                <option value="">Todos los grupos de la materia</option>
                @foreach ($grupos as $g)
                    <option value="{{ $g->id }}" @selected($grupoId === $g->id)>{{ $g->nombre }}</option>
                @endforeach
            </select>
        </form>
        @endif
    </div>

    {{-- Tabla con casillas (formulario principal: habilitar seleccionados / habilitar a todos) --}}
    <form method="POST" action="{{ route('habilitaciones.masiva', $examen) }}">
        @csrf
        <input type="hidden" name="grupo_id" value="{{ $grupoId }}">
        <section class="card" style="margin-top:12px">
            <div class="scroll">
                <table>
                    <thead>
                        <tr>
                            <th style="width:36px"><input type="checkbox" id="check-todos" aria-label="Seleccionar todos"></th>
                            <th>Código</th><th>Estudiante</th><th>Grupo</th><th>Estado</th><th>Motivo / Observación</th>
                            <th style="text-align:right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($estudiantes as $est)
                        @php $h = $habilitaciones->get($est->id_estudiante); @endphp
                        <tr>
                            <td><input type="checkbox" name="estudiante_ids[]" value="{{ $est->id_estudiante }}" class="chk-estudiante"></td>
                            <td class="num">{{ $est->codigo_universitario }}</td>
                            <td class="strong">{{ $est->nombre_completo }}</td>
                            <td>{{ $gruposPorEstudiante[$est->id_estudiante] ?? '—' }}</td>
                            <td>
                                @if (! $h)
                                    <span class="badge inactivo">Sin evaluar</span>
                                @elseif ($h->estado === 'habilitado')
                                    <span class="badge activo">Habilitado</span>
                                @else
                                    <span class="badge observado">Inhabilitado</span>
                                @endif
                            </td>
                            <td class="muted">{{ $h?->motivo?->descripcion }}{{ $h?->observacion ? ' — '.$h->observacion : '' }}</td>
                            <td>
                                <div class="actions">
                                    <button type="button" class="link-btn ok" onclick="habilitarUno({{ $est->id_estudiante }})">Habilitar</button>
                                    <button type="button" class="link-btn danger"
                                            onclick="abrirInhabilitar({{ $est->id_estudiante }}, @js($est->nombre_completo))">Inhabilitar</button>
                                    @if ($h)
                                        <button type="button" class="link-btn muted" onclick="quitarUno({{ $est->id_estudiante }})">Quitar</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty">@if ($usaGrupos)No hay estudiantes asignados a esta materia{{ $grupoId ? ' en ese grupo' : '' }}. Ve a Estudiantes y usa "Asignar materia, grupo y docente".@else No hay estudiantes inscritos en esta asignatura para la carrera del examen.@endif</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="foot">
                <span>{{ $estudiantes->count() }} estudiante(s) mostrados</span>
                <span style="display:flex;gap:8px;flex-wrap:wrap">
                    <button type="submit" name="todos" value="1" class="btn ghost">Habilitar a todos los mostrados</button>
                    <button type="submit" class="btn"><svg class="i sm"><use href="#i-check"/></svg> Habilitar seleccionados</button>
                </span>
            </div>
        </section>
    </form>

    {{-- Formularios ocultos (fuera del formulario principal: en HTML no se pueden anidar formularios) --}}
    <form id="form-habilitar-uno" method="POST" action="{{ route('habilitaciones.masiva', $examen) }}" style="display:none">
        @csrf
        <input type="hidden" name="estudiante_ids[]" id="input-habilitar-uno">
    </form>
    <form id="form-quitar" method="POST" style="display:none">
        @csrf @method('DELETE')
    </form>

    {{-- ================= MODAL: inhabilitar ================= --}}
    <dialog id="modal-inhabilitar">
        <form method="POST" id="form-inhabilitar">
            @csrf
            <div class="m-head">
                <span class="ic" style="background:var(--danger-bg);color:var(--danger)"><svg class="i"><use href="#i-alert"/></svg></span>
                <div><b>Inhabilitar estudiante</b><small id="inhabilitar-nombre"></small></div>
                <button type="button" class="icon-btn x" aria-label="Cerrar" onclick="this.closest('dialog').close()"><svg class="i"><use href="#i-x"/></svg></button>
            </div>
            <div class="m-body">
                <div class="field">
                    <label for="mot-select">Causa <span class="req">*</span></label>
                    <select id="mot-select" name="motivo_inhabilitacion_id" required>
                        <option value="">Seleccione la causa…</option>
                        @foreach ($motivos as $m)
                            <option value="{{ $m->id }}">{{ $m->descripcion }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="mot-obs">Observación <i id="mot-obs-nota">opcional</i></label>
                    <textarea id="mot-obs" name="observacion" rows="3" maxlength="500" placeholder="Detalle adicional…"></textarea>
                </div>
            </div>
            <div class="m-foot">
                <button type="button" class="btn ghost" onclick="this.closest('dialog').close()">Cancelar</button>
                <button type="submit" class="btn" style="background:var(--danger)">Inhabilitar</button>
            </div>
        </form>
    </dialog>
@endsection

@push('scripts')
<script>
    const BASE = "{{ url('examenes/'.$examen->id.'/habilitados') }}";

    document.getElementById('check-todos').addEventListener('change', function () {
        document.querySelectorAll('.chk-estudiante').forEach(c => c.checked = this.checked);
    });

    function habilitarUno(id) {
        document.getElementById('input-habilitar-uno').value = id;
        document.getElementById('form-habilitar-uno').submit();
    }

    function quitarUno(id) {
        const f = document.getElementById('form-quitar');
        f.action = BASE + '/' + id;
        f.submit();
    }

    function abrirInhabilitar(id, nombre) {
        document.getElementById('inhabilitar-nombre').textContent = nombre;
        document.getElementById('form-inhabilitar').action = BASE + '/' + id + '/inhabilitar';
        document.getElementById('mot-select').value = '';
        document.getElementById('mot-obs').value = '';
        actualizarObligatoriedad();
        document.getElementById('modal-inhabilitar').showModal();
    }

    // Si la causa elegida es "Otro", la observación pasa a ser obligatoria.
    function actualizarObligatoriedad() {
        const sel = document.getElementById('mot-select');
        const esOtro = sel.selectedOptions[0]?.textContent.trim() === 'Otro';
        document.getElementById('mot-obs').required = esOtro;
        document.getElementById('mot-obs-nota').textContent = esOtro ? 'obligatoria para "Otro"' : 'opcional';
    }
    document.getElementById('mot-select').addEventListener('change', actualizarObligatoriedad);
</script>
@endpush
