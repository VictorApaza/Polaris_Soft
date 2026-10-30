@extends('layouts.app')
@section('title', 'Reporte del examen')

@section('content')
    @php
        $zona = config('sciem.zona_horaria');
        $fh = fn ($c) => $c ? $c->copy()->timezone($zona)->format('d/m/Y H:i:s') : '—';
        $abiertas = $incidencias->where('estado', 'ABIERTA')->count();
        $maxMotivo = max(1, (int) $porMotivo->max());
    @endphp

    <nav class="crumb" aria-label="Ruta">
        <a href="{{ url('/dashboard') }}">Inicio</a> › <a href="{{ route('reportes.index') }}">Reportes</a> › <b>{{ $examen->nombre }}</b>
    </nav>

    <div class="head">
        <div>
            <h1>Reporte: {{ $examen->nombre }}</h1>
            <p>{{ $examen->carrera }} · {{ $examen->inicio->format('d/m/Y') }} {{ $examen->hora }} · Aula {{ $examen->ambiente }}</p>
        </div>
        <div class="no-print" style="display:flex;gap:8px;flex-wrap:wrap">
            <a class="btn ghost" href="{{ route('habilitaciones.index', $examen) }}">Habilitados</a>
            <a class="btn ghost" href="{{ route('examenes.fotos', $examen) }}">Fotos</a>
            <button class="btn" type="button" onclick="window.print()">Imprimir</button>
        </div>
    </div>

    @include('partials.flash')
    @if ($errors->any())
        <div class="flash err" role="alert" style="display:block">
            <ul style="margin-left:18px">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="stats cinco">
        <div class="stat"><div><small>Habilitados</small><strong>{{ $habilitados }}</strong></div><span class="ic"><svg class="i"><use href="#i-users"/></svg></span></div>
        <div class="stat ok"><div><small>Ingresaron</small><strong>{{ $ingresos->pluck('estudiante_id')->unique()->count() }}</strong></div><span class="ic"><svg class="i"><use href="#i-check"/></svg></span></div>
        <div class="stat"><div><small>No ingresaron</small><strong>{{ $noIngresaron->count() }}</strong></div><span class="ic"><svg class="i"><use href="#i-calendar"/></svg></span></div>
        <div class="stat off"><div><small>Inhabilitados</small><strong>{{ $inhabilitados->count() }}</strong></div><span class="ic"><svg class="i"><use href="#i-lock"/></svg></span></div>
        <div class="stat"><div><small>Incidencias abiertas</small><strong style="{{ $abiertas ? 'color:var(--danger)' : '' }}">{{ $abiertas }}</strong></div><span class="ic" style="{{ $abiertas ? 'background:var(--danger-bg);color:var(--danger)' : '' }}"><svg class="i"><use href="#i-alert"/></svg></span></div>
    </section>

    {{-- ================= REGISTRAR UNA INCIDENCIA (el docente durante el examen) ================= --}}
    <section class="seccion no-print">
        <h2>Registrar una incidencia o falta durante el examen</h2>
        <form class="card" method="POST" action="{{ route('incidencias.store') }}" enctype="multipart/form-data" style="padding:18px;display:grid;gap:14px">
            @csrf
            <input type="hidden" name="examen_id" value="{{ $examen->id }}">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px">
                <div class="field">
                    <label for="inc-est">Estudiante</label>
                    <select id="inc-est" name="estudiante_id">
                        <option value="">Sin estudiante en particular</option>
                        @foreach ($estudiantesExamen as $est)
                            <option value="{{ $est->id_estudiante }}" @selected((string) old('estudiante_id') === (string) $est->id_estudiante)>{{ $est->apellidos }}, {{ $est->nombres }} · {{ $est->codigo_universitario }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="inc-tipo">Tipo de falta <span class="req">*</span></label>
                    <select id="inc-tipo" name="tipo" required>
                        <option value="TRAMPA" @selected(old('tipo') === 'TRAMPA')>Copiando / trampa durante el examen</option>
                        <option value="OTRO" @selected(old('tipo') === 'OTRO')>Otra falta (la explico abajo)</option>
                    </select>
                </div>
            </div>
            <div class="field">
                <label for="inc-detalle">Argumento: qué pasó y por qué es una falta <span class="req">*</span></label>
                <textarea id="inc-detalle" name="detalle" rows="3" maxlength="1000" required placeholder="Ej.: Estaba mirando el examen de su compañero de al lado y le pasó un papel.">{{ old('detalle') }}</textarea>
            </div>
            <div class="field">
                <label for="inc-prueba">Prueba: foto de lo que hizo <i>opcional, JPG o PNG, máx. 4 MB</i></label>
                <input id="inc-prueba" type="file" name="evidencia" accept="image/*" capture="environment">
            </div>
            <div><button class="btn" type="submit"><svg class="i sm"><use href="#i-alert"/></svg> Registrar incidencia</button></div>
        </form>
    </section>

    {{-- ================= INCIDENCIAS ================= --}}
    <section class="seccion">
        <h2>Incidencias y posibles trampas <span class="n">{{ $incidencias->count() }}</span></h2>
        <div class="card">
            <div class="scroll">
                <table>
                    <thead>
                        <tr><th>Hora del intento</th><th>Estudiante</th><th>Tipo</th><th>Método</th><th>Ingresó antes a las</th><th>Detalle</th><th>Registró</th><th>Estado</th><th class="no-print" style="text-align:right">Acciones</th></tr>
                    </thead>
                    <tbody>
                    @forelse ($incidencias as $i)
                        <tr class="{{ $i->estado === 'ABIERTA' ? 'alerta-fila' : '' }}">
                            <td class="num strong">{{ $fh($i->fecha_hora) }}</td>
                            <td>
                                @if ($i->estudiante)
                                    <div style="display:flex;gap:10px;align-items:center">
                                        @if ($i->estudiante->tiene_foto)<img class="foto sm" src="{{ $i->estudiante->foto_url }}" alt="">@else<div class="foto-vacia sm">—</div>@endif
                                        <div><b>{{ $i->estudiante->apellidos }}, {{ $i->estudiante->nombres }}</b><span class="sub">{{ $i->estudiante->codigo_universitario }}</span></div>
                                    </div>
                                @else — @endif
                            </td>
                            <td>{{ $i->tipo_etiqueta }}</td>
                            <td>{{ $i->metodo_etiqueta }}</td>
                            <td class="num">{{ $fh($i->ingreso_original_at) }}</td>
                            <td style="max-width:300px">
                                {{ $i->detalle ?: '—' }}
                                @if ($i->suplantador_nombre || $i->suplantador_foto_url)
                                    <div style="margin-top:6px;padding:8px;border-radius:8px;background:var(--danger-bg)">
                                        <b>Suplantador:</b> {{ $i->suplantador_nombre ?: '—' }}@if ($i->suplantador_ci) · CI {{ $i->suplantador_ci }}@endif
                                        @if ($i->suplantador_foto_url)
                                            <a href="{{ $i->suplantador_foto_url }}" target="_blank" rel="noopener"><img src="{{ $i->suplantador_foto_url }}" alt="Foto del suplantador" style="display:block;margin-top:6px;max-width:110px;border-radius:8px;border:1px solid var(--line)"></a>
                                        @endif
                                    </div>
                                @endif
                                @if ($i->evidencia_url)
                                    <a href="{{ $i->evidencia_url }}" target="_blank" rel="noopener"><img src="{{ $i->evidencia_url }}" alt="Prueba" style="display:block;margin-top:6px;max-width:110px;border-radius:8px;border:1px solid var(--line)"></a>
                                    <small class="sub">Foto de prueba</small>
                                @endif
                            </td>
                            <td>{{ $i->controlador->name ?? '—' }}</td>
                            <td><span class="badge {{ strtolower($i->estado) }}">{{ $i->estado === 'ABIERTA' ? 'Abierta' : 'Revisada' }}</span></td>
                            <td class="no-print">
                                <div class="actions" style="justify-content:flex-end;gap:6px;flex-wrap:wrap">
                                    @if ($i->estudiante)
                                        <form method="POST" action="{{ route('habilitaciones.inhabilitar', [$examen, $i->estudiante->id_estudiante]) }}"
                                              onsubmit="return confirm('¿Inhabilitar a este estudiante por trampa o suplantación?')">
                                            @csrf
                                            <input type="hidden" name="motivo_inhabilitacion_id" value="{{ $motivoTrampaId }}">
                                            <input type="hidden" name="observacion" value="Incidencia #{{ $i->id }}: {{ $i->tipo_etiqueta }}">
                                            <button class="link-btn danger" type="submit">Inhabilitar por trampa</button>
                                        </form>
                                    @endif
                                    @if ($i->estado === 'ABIERTA')
                                        <form method="POST" action="{{ route('incidencias.revisar', $i) }}">
                                            @csrf
                                            <button class="link-btn ok" type="submit">Marcar revisada</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="empty">No se registraron incidencias en este examen.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    {{-- ================= INHABILITADOS Y POR QUÉ ================= --}}
    <section class="seccion">
        <h2>Inhabilitados y por qué <span class="n">{{ $inhabilitados->count() }}</span></h2>
        <div class="card" style="padding:16px 18px">
            @forelse ($porMotivo as $motivo => $cantidad)
                <div class="barra-motivo">
                    <span class="t">{{ $motivo }}</span>
                    <span class="b"><i style="width:{{ round($cantidad / $maxMotivo * 100) }}%"></i></span>
                    <span class="v">{{ $cantidad }}</span>
                </div>
            @empty
                <p class="muted">Nadie fue inhabilitado en este examen.</p>
            @endforelse
        </div>

        @if ($inhabilitados->isNotEmpty())
            <div class="card" style="margin-top:12px">
                <div class="scroll">
                    <table>
                        <thead><tr><th>Estudiante</th><th>Código</th><th>Motivo</th><th>Observación</th></tr></thead>
                        <tbody>
                        @foreach ($inhabilitados as $h)
                            <tr>
                                <td class="strong">{{ $h->estudiante ? $h->estudiante->apellidos.', '.$h->estudiante->nombres : '—' }}</td>
                                <td class="num">{{ $h->estudiante->codigo_universitario ?? '—' }}</td>
                                <td>{{ $h->motivo->descripcion ?? '—' }}</td>
                                <td style="max-width:320px">{{ $h->observacion ?: '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </section>

    {{-- ================= INGRESARON ================= --}}
    <section class="seccion">
        <h2>Estudiantes que ingresaron <span class="n">{{ $ingresos->count() }}</span></h2>
        <div class="card">
            <div class="scroll">
                <table>
                    <thead><tr><th>Hora de ingreso</th><th>Estudiante</th><th>Método</th><th>Aula</th><th>Controlador</th></tr></thead>
                    <tbody>
                    @forelse ($ingresos as $r)
                        <tr>
                            <td class="num strong">{{ $fh($r->fecha_hora_ingreso) }}</td>
                            <td>{{ $r->estudiante_nombre }}<span class="sub">{{ $r->estudiante_codigo }}</span></td>
                            <td>{{ \App\Models\TransparenciaIngreso::METODOS[$r->metodo_verificacion] ?? $r->metodo_verificacion }}</td>
                            <td>{{ $r->ambiente_nombre }}</td>
                            <td>{{ $r->controlador_nombre }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty">Todavía nadie registró ingreso.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    {{-- ================= NO INGRESARON ================= --}}
    <section class="seccion">
        <h2>Habilitados que no ingresaron <span class="n">{{ $noIngresaron->count() }}</span></h2>
        <div class="card">
            <div class="scroll">
                <table>
                    <thead><tr><th>Estudiante</th><th>Código</th><th>Cédula</th><th>Carrera</th><th>Foto</th></tr></thead>
                    <tbody>
                    @forelse ($noIngresaron as $h)
                        @php $e = $h->estudiante; @endphp
                        <tr>
                            <td class="strong">{{ $e ? $e->apellidos.', '.$e->nombres : '—' }}</td>
                            <td class="num">{{ $e->codigo_universitario ?? '—' }}</td>
                            <td class="num">{{ $e->carnet_completo ?? '—' }}</td>
                            <td>{{ $e->carrera ?? '—' }}</td>
                            <td>{{ $e->foto_estado_etiqueta ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty">Todos los habilitados ya ingresaron (o aún no hay habilitados).</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <p class="sub" style="margin-top:18px">
        Fotos de los {{ $totalCandidatos }} estudiantes del examen:
        {{ $fotos['APROBADA'] ?? 0 }} aprobadas · {{ $fotos['PENDIENTE'] ?? 0 }} pendientes ·
        {{ $fotos['OBSERVADA'] ?? 0 }} observadas · {{ $fotos['SIN_FOTO'] ?? 0 }} sin foto.
    </p>
@endsection
