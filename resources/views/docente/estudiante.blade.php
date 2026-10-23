@extends('layouts.app')
@section('title', 'Detalle del estudiante')

@section('content')
    @php
        $zona = config('sciem.zona_horaria');
        $fh = fn ($c) => $c ? $c->copy()->timezone($zona)->format('d/m/Y H:i:s') : '—';
    @endphp

    <nav class="crumb" aria-label="Ruta">
        <a href="{{ url('/dashboard') }}">Inicio</a> › <a href="{{ route('buscador.index') }}">Buscar estudiantes</a> › <b>{{ $estudiante->nombre_completo }}</b>
    </nav>

    <div class="head">
        <div><h1>{{ $estudiante->nombres }} {{ $estudiante->apellidos }}</h1><p>{{ $estudiante->carrera }}</p></div>
        <a class="btn ghost" href="{{ route('buscador.index') }}">‹ Volver a la búsqueda</a>
    </div>

    <section class="card" style="margin-top:20px;padding:20px">
        <div class="mi-foto">
            <div class="img" style="width:140px">
                @if ($estudiante->tiene_foto)
                    <a href="{{ $estudiante->foto_url }}" target="_blank" rel="noopener"><img class="foto lg" src="{{ $estudiante->foto_url }}" alt="Foto"></a>
                @else
                    <div class="foto-vacia lg">Sin foto</div>
                @endif
            </div>
            <div class="info" style="display:grid;gap:6px">
                <div><small class="sub">Código universitario</small><b>{{ $estudiante->codigo_universitario }}</b></div>
                <div><small class="sub">Cédula de identidad</small><b>{{ $estudiante->carnet_completo }}</b></div>
                <div><small class="sub">Correo institucional</small><b>{{ $estudiante->correo_institucional ?: '—' }}</b></div>
                <div>
                    <span class="badge {{ strtolower($estudiante->estado) }}">{{ ucfirst(strtolower($estudiante->estado)) }}</span>
                    <span class="badge {{ strtolower($estudiante->foto_estado ?: 'sin_foto') }}">Foto: {{ $estudiante->foto_estado_etiqueta }}</span>
                </div>
                @if ($estudiante->foto_estado === 'OBSERVADA')
                    <div class="nota-foto"><b>Observación de la foto:</b> {{ $estudiante->foto_observacion }}</div>
                @endif
            </div>
        </div>
    </section>

    <section class="seccion">
        <h2>Exámenes <span class="n">{{ $habilitaciones->count() }}</span></h2>
        <div class="card">
            <div class="scroll">
                <table>
                    <thead><tr><th>Examen</th><th>Fecha</th><th>Habilitación</th><th>Motivo</th><th>Ingresó</th></tr></thead>
                    <tbody>
                    @forelse ($habilitaciones as $h)
                        @php $x = $h->examen; @endphp
                        <tr>
                            <td class="strong">{{ $x ? $x->nombre : '—' }}</td>
                            <td class="num">{{ $x ? $x->fecha->format('d/m/Y').' '.$x->hora : '—' }}</td>
                            <td><span class="badge {{ $h->estado }}">{{ ucfirst($h->estado) }}</span></td>
                            <td>{{ $h->motivo->descripcion ?? '—' }}{{ $h->observacion ? ' · '.$h->observacion : '' }}</td>
                            <td class="num">{{ $x ? $fh($ingresos->get($x->id)) : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty">Todavía no tiene habilitaciones registradas.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="seccion">
        <h2>Incidencias <span class="n">{{ $incidencias->count() }}</span></h2>
        <div class="card">
            <div class="scroll">
                <table>
                    <thead><tr><th>Hora</th><th>Examen</th><th>Tipo</th><th>Método</th><th>Detalle</th><th>Estado</th></tr></thead>
                    <tbody>
                    @forelse ($incidencias as $i)
                        <tr>
                            <td class="num strong">{{ $fh($i->fecha_hora) }}</td>
                            <td>{{ $i->examen ? $i->examen->nombre : '—' }}</td>
                            <td>{{ $i->tipo_etiqueta }}</td>
                            <td>{{ $i->metodo_etiqueta }}</td>
                            <td style="max-width:280px">{{ $i->detalle ?: '—' }}</td>
                            <td><span class="badge {{ strtolower($i->estado) }}">{{ $i->estado === 'ABIERTA' ? 'Abierta' : 'Revisada' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty">Sin incidencias.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection
