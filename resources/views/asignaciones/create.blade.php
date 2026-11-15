@extends('layouts.app')
@section('title', 'Asignar materias')

@section('content')
    <nav class="crumb" aria-label="Ruta">
        <a href="{{ url('/dashboard') }}">Inicio</a> › <a href="{{ route('estudiantes.index') }}">Estudiantes</a> › <b>Asignaciones</b>
    </nav>

    <div class="head">
        <div>
            <h1>Asignar materia, grupo y docente</h1>
            <p>Define en qué materias, grupos y con qué docente cursa el estudiante.</p>
        </div>
        <a class="btn ghost" href="{{ route('estudiantes.index') }}">‹ Volver a estudiantes</a>
    </div>

    @include('partials.flash')

    {{-- Datos del estudiante --}}
    <section class="banner">
        <span class="ic"><svg class="i"><use href="#i-students"/></svg></span>
        <div>
            <b>{{ $estudiante->nombre_completo }}</b>
            <small>{{ $estudiante->codigo_universitario }} · CI {{ $estudiante->documento_identidad }} · {{ $estudiante->carrera }}</small>
        </div>
        <span class="badge {{ strtolower($estudiante->estado) }}" style="margin-left:auto">{{ ucfirst(strtolower($estudiante->estado)) }}</span>
    </section>

    <div style="display:grid;grid-template-columns:minmax(300px,380px) 1fr;gap:20px;margin-top:20px;align-items:start" class="asig-grid">
        {{-- Formulario --}}
        <form class="card" method="POST" action="{{ route('asignaciones.store', $estudiante) }}" style="padding:20px;display:grid;gap:14px">
            @csrf
            <b style="color:var(--navy-900)">Nueva asignación</b>

            <div class="field">
                <label for="a-materia">Materia <span class="req">*</span></label>
                <select id="a-materia" name="materia_id" required>
                    <option value="">Seleccione la materia…</option>
                    @foreach ($materias as $m)
                        <option value="{{ $m->id }}" @selected((string) old('materia_id') === (string) $m->id)>{{ $m->nombre }}{{ $m->sigla ? ' ('.$m->sigla.')' : '' }}</option>
                    @endforeach
                </select>
                @error('materia_id')<p class="err">{{ $message }}</p>@enderror
            </div>

            <div class="field">
                <label for="a-grupo">Grupo <span class="req">*</span></label>
                <select id="a-grupo" name="grupo_id" required>
                    <option value="">Primero elija la materia…</option>
                    @foreach ($grupos as $g)
                        <option value="{{ $g->id }}" data-materia="{{ $g->materia_id }}" data-docente="{{ $g->docente_id }}"
                                @selected((string) old('grupo_id') === (string) $g->id)>{{ $g->nombre }}</option>
                    @endforeach
                </select>
                @error('grupo_id')<p class="err">{{ $message }}</p>@enderror
            </div>

            <div class="field">
                <label for="a-docente">Docente <i>Opcional</i></label>
                <select id="a-docente" name="docente_id">
                    <option value="">Sin docente</option>
                    @foreach ($docentes as $d)
                        <option value="{{ $d->id }}" @selected((string) old('docente_id') === (string) $d->id)>{{ $d->nombre_completo }}</option>
                    @endforeach
                </select>
                <p class="muted" style="font-size:12px;margin-top:4px">Se completa solo con el docente del grupo; puedes cambiarlo.</p>
                @error('docente_id')<p class="err">{{ $message }}</p>@enderror
            </div>

            <button type="submit" class="btn block"><svg class="i sm"><use href="#i-check"/></svg> Guardar asignación</button>
        </form>

        {{-- Asignaciones actuales --}}
        <section class="card">
            <div class="scroll">
                <table style="min-width:520px">
                    <thead><tr><th>Materia</th><th>Grupo</th><th>Docente</th><th class="t-right">Acción</th></tr></thead>
                    <tbody>
                    @forelse ($asignaciones as $a)
                        <tr>
                            <td class="strong">{{ $a->materia->nombre ?? '—' }}<span class="sub">{{ $a->materia?->sigla }}</span></td>
                            <td>{{ $a->grupo->nombre ?? '—' }}</td>
                            <td>{{ $a->docente->nombre_completo ?? 'Sin docente' }}</td>
                            <td>
                                <div class="actions">
                                    <form method="POST" action="{{ route('asignaciones.destroy', $a) }}" onsubmit="return confirm('¿Quitar esta asignación?')">
                                        @csrf @method('DELETE')
                                        <button class="icon-btn danger" type="submit" title="Quitar" aria-label="Quitar"><svg class="i"><use href="#i-trash"/></svg></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty">Este estudiante aún no tiene materias asignadas.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="foot"><span>{{ $asignaciones->count() }} materias asignadas</span></div>
        </section>
    </div>
@endsection

@push('scripts')
<script>
    const selMateria = document.getElementById('a-materia');
    const selGrupo = document.getElementById('a-grupo');
    const selDocente = document.getElementById('a-docente');

    // Muestra solo los grupos de la materia elegida
    function filtrarGrupos(limpiar) {
        const materia = selMateria.value;
        selGrupo.options[0].textContent = materia ? 'Seleccione el grupo…' : 'Primero elija la materia…';
        [...selGrupo.options].forEach((o, i) => {
            if (i === 0) return;
            const visible = o.dataset.materia === materia;
            o.hidden = !visible; o.disabled = !visible;
        });
        if (limpiar || selGrupo.selectedOptions[0]?.disabled) selGrupo.value = '';
    }
    selMateria.addEventListener('change', () => { filtrarGrupos(true); selDocente.value = ''; });

    // Al elegir el grupo, sugiere el docente que lo dicta
    selGrupo.addEventListener('change', () => {
        const d = selGrupo.selectedOptions[0]?.dataset.docente;
        if (d) selDocente.value = d;
    });
    filtrarGrupos(false);
</script>
<style>@media (max-width:1000px){.asig-grid{grid-template-columns:1fr !important}}</style>
@endpush
