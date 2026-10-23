@extends('layouts.app')
@section('title', 'Fotos de estudiantes')

@section('content')
    @php
        $rutaVolver = auth()->user()->rol === 'docente' ? route('docente.examenes') : route('examenes.index');
        $etiquetas = ['SIN_FOTO' => 'Sin foto', 'PENDIENTE' => 'Pendientes', 'OBSERVADA' => 'Observadas', 'APROBADA' => 'Aprobadas'];
    @endphp

    <nav class="crumb" aria-label="Ruta">
        <a href="{{ url('/dashboard') }}">Inicio</a> › <a href="{{ $rutaVolver }}">Exámenes</a> › <b>Fotos</b>
    </nav>

    <div class="head">
        <div>
            <h1>Fotos de los estudiantes</h1>
            <p>{{ $examen->nombre }} · {{ $examen->carrera }} · {{ $examen->inicio->format('d/m/Y') }} {{ $examen->hora }} · {{ $examen->ambiente }}</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a class="btn ghost" href="{{ route('habilitaciones.index', $examen) }}">Habilitados</a>
            <a class="btn ghost" href="{{ route('reportes.show', $examen) }}">Reporte del examen</a>
        </div>
    </div>

    @include('partials.flash')

    <div class="aviso {{ $plazoVencido ? 'warn' : 'info' }}" style="margin-top:16px">
        Plazo para subir fotos: hasta el <b>{{ $limite->format('d/m/Y H:i') }}</b>
        ({{ config('sciem.foto_limite_horas') }} h antes del examen).
        @if ($plazoVencido)
            <b>El plazo ya venció:</b> puedes inhabilitar a quienes no tienen una foto válida.
        @else
            Hasta entonces los estudiantes pueden subir o corregir su foto.
        @endif
    </div>

    {{-- Filtros --}}
    <div class="chips">
        <a class="chip {{ $filtro === '' ? 'activo' : '' }}" href="{{ route('examenes.fotos', $examen) }}">Todos <b>{{ $totalCandidatos }}</b></a>
        @foreach ($etiquetas as $clave => $texto)
            <a class="chip {{ $filtro === $clave ? 'activo' : '' }}" href="{{ route('examenes.fotos', [$examen, 'f' => $clave]) }}">{{ $texto }} <b>{{ $conteos[$clave] ?? 0 }}</b></a>
        @endforeach
    </div>

    <form class="toolbar" method="GET" action="{{ route('examenes.fotos', $examen) }}" style="margin-top:14px;align-items:center;gap:10px;flex-wrap:wrap">
        @if ($filtro !== '') <input type="hidden" name="f" value="{{ $filtro }}"> @endif
        <label class="search" style="flex:1;min-width:240px">
            <svg class="i"><use href="#i-search"/></svg>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Buscar por nombre, código o cédula…" aria-label="Buscar estudiante">
        </label>
        <button class="btn ghost" type="submit">Buscar</button>
    </form>

    <form method="POST" action="{{ route('examenes.fotos.inhabilitar', $examen) }}" class="no-print" style="margin-top:12px;text-align:right"
          onsubmit="return confirm('¿Inhabilitar para este examen a todos los habilitados que no tienen una foto válida (sin foto u observada)?')">
        @csrf
        <button type="submit" class="btn" style="background:var(--danger)" @disabled(! $plazoVencido) title="{{ $plazoVencido ? '' : 'Disponible cuando venza el plazo' }}">
            Inhabilitar a quienes no subieron una foto válida
        </button>
    </form>

    {{-- Tarjetas --}}
    <div class="fotos-grid">
        @forelse ($estudiantes as $e)
            @php $h = $habilitaciones[$e->id_estudiante] ?? null; @endphp
            <article class="foto-card">
                @if ($e->tiene_foto)
                    <a href="{{ $e->foto_url }}" target="_blank" rel="noopener" title="Ver en grande">
                        <img class="foto lg" src="{{ $e->foto_url }}" alt="Foto de {{ $e->nombre_completo }}" loading="lazy">
                    </a>
                @else
                    <div class="foto-vacia lg">Sin foto</div>
                @endif

                <div>
                    <b>{{ $e->apellidos }}, {{ $e->nombres }}</b>
                    <span class="sub">{{ $e->codigo_universitario }} · CI {{ $e->carnet_completo }}</span>
                </div>

                <div style="display:flex;gap:6px;flex-wrap:wrap">
                    <span class="badge {{ strtolower($e->foto_estado ?: 'sin_foto') }}">{{ $e->foto_estado_etiqueta }}</span>
                    <span class="badge {{ $h ?? 'sin_evaluar' }}">{{ ['habilitado' => 'Habilitado', 'inhabilitado' => 'Inhabilitado'][$h] ?? 'Sin evaluar' }}</span>
                </div>

                @if ($e->foto_estado === 'OBSERVADA')
                    <div class="nota-foto"><b>Observación:</b> {{ $e->foto_observacion }}</div>
                @endif

                @if ($e->tiene_foto)
                    <div class="acciones">
                        @if ($e->foto_estado !== 'APROBADA')
                            <form method="POST" action="{{ route('fotos.aprobar', $e->id_estudiante) }}">
                                @csrf
                                <button class="btn" type="submit" style="padding:7px 12px;background:var(--ok)">Aprobar</button>
                            </form>
                        @endif
                        <button class="btn ghost" type="button" style="padding:7px 12px"
                                data-observar="{{ $e->id_estudiante }}" data-nombre="{{ $e->nombre_completo }}">Observar</button>
                    </div>
                @endif
            </article>
        @empty
            <p class="empty" style="grid-column:1/-1">No hay estudiantes para este filtro.</p>
        @endforelse
    </div>

    <div class="foot" style="margin-top:16px;border:1px solid var(--line);border-radius:10px">
        <span>{{ $estudiantes->total() }} estudiante(s)</span><span>{{ $estudiantes->links() }}</span>
    </div>

    {{-- Modal: observar foto --}}
    <dialog id="modal-observar">
        <form method="POST" id="form-observar">
            @csrf
            <div class="m-head">
                <span class="ic" style="background:var(--danger-bg);color:var(--danger)"><svg class="i"><use href="#i-alert"/></svg></span>
                <div><b>Observar foto</b><small id="obs-nombre"></small></div>
                <button type="button" class="icon-btn x" aria-label="Cerrar" onclick="this.closest('dialog').close()"><svg class="i"><use href="#i-x"/></svg></button>
            </div>
            <div class="m-body">
                <div class="field">
                    <label for="obs-texto">¿Qué está mal con la foto? <span class="req">*</span></label>
                    <textarea id="obs-texto" name="observacion" rows="3" maxlength="300" required
                              placeholder="Ej.: está borrosa, no se ve el rostro, tiene lentes oscuros…"></textarea>
                </div>
                <small class="sub">El estudiante verá este mensaje y podrá subir otra foto hasta el límite. Si no lo hace, podrá ser inhabilitado.</small>
            </div>
            <div class="m-foot">
                <button type="button" class="btn ghost" onclick="this.closest('dialog').close()">Cancelar</button>
                <button type="submit" class="btn" style="background:var(--danger)">Marcar como observada</button>
            </div>
        </form>
    </dialog>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const PLANTILLA = @json(route('fotos.observar', '__ID__'));
    const modal = document.getElementById('modal-observar');
    const form = document.getElementById('form-observar');

    document.querySelectorAll('[data-observar]').forEach(b => b.addEventListener('click', () => {
        form.action = PLANTILLA.replace('__ID__', b.dataset.observar);
        document.getElementById('obs-nombre').textContent = b.dataset.nombre;
        document.getElementById('obs-texto').value = '';
        modal.showModal();
        // Foto inicial del formulario: así no se cierra solo (ni pierde lo escrito) al hacer clic afuera.
        setTimeout(() => { modal._valoresIniciales = valoresFormulario(form); }, 0);
    }));
});
</script>
@endpush
