@extends('layouts.estudiante')
@section('title', 'Mi panel')

@section('content')
    @php
        $zona = config('sciem.zona_horaria');
        $limiteHoras = config('sciem.foto_limite_horas');
        $abreHoras = config('sciem.portal_abre_horas');
    @endphp

    <h1>Hola, {{ $estudiante->nombres }}</h1>
    <p class="lead">Código {{ $estudiante->codigo_universitario }} · {{ $estudiante->carrera }}</p>

    @if (session('status')) <div class="flash" role="status">{{ session('status') }}</div> @endif
    @if (session('error')) <div class="flash err" role="alert">{{ session('error') }}</div> @endif
    @if ($errors->any()) <div class="flash err" role="alert">{{ $errors->first() }}</div> @endif

    {{-- ================= MI FOTO ================= --}}
    <section class="p-card">
        <h2>Mi foto actual</h2>
        <div class="mi-foto">
            <div class="img">
                @if ($estudiante->tiene_foto)
                    <img class="foto lg" src="{{ route('estudiante.foto.ver') }}?v={{ $estudiante->foto_subida_at ? strtotime((string) $estudiante->foto_subida_at) : 0 }}" alt="Mi foto">
                @else
                    <div class="foto-vacia lg">Aún no subiste tu foto</div>
                @endif
            </div>
            <div class="info">
                <div><span class="badge {{ strtolower($estudiante->foto_estado ?: 'sin_foto') }}">{{ $estudiante->foto_estado_etiqueta }}</span></div>

                @if ($estudiante->foto_estado === 'OBSERVADA')
                    <div class="aviso err"><b>Tu foto fue observada:</b> {{ $estudiante->foto_observacion }}<br>Sube otra foto dentro del plazo para no quedar inhabilitado.</div>
                @elseif ($estudiante->foto_estado === 'PENDIENTE')
                    <div class="aviso warn">Tu foto está pendiente de revisión por un docente.</div>
                @elseif ($estudiante->foto_estado === 'APROBADA')
                    <div class="aviso ok">Tu foto fue aprobada.</div>
                @endif

                @if ($puedeSubirFoto)
                    <div class="aviso info">
                        Puedes subir o cambiar tu foto hasta el
                        <b>{{ $examenFoto->limiteFoto()->format('d/m/Y H:i') }}</b>
                        ({{ $limiteHoras }} horas antes de «{{ $examenFoto->nombre }}»).
                    </div>
                    <form method="POST" action="{{ route('estudiante.foto.subir') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="field">
                            <label for="foto">Elige una foto de tu rostro (JPG o PNG, máximo {{ config('sciem.foto_max_kb') / 1024 }} MB)</label>
                            <input id="foto" type="file" name="foto" accept="image/jpeg,image/png" required>
                        </div>
                        <button class="btn" type="submit" style="margin-top:10px">Subir foto</button>
                    </form>
                    <small class="sub">Debe ser reciente, de frente, con buena luz y sin lentes oscuros ni gorra, para que el personal pueda reconocerte.</small>
                @else
                    <div class="aviso warn">
                        Ahora no puedes subir ni cambiar tu foto. Solo se puede desde {{ $abreHoras }} horas hasta {{ $limiteHoras }} horas antes del examen.
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- ================= MIS EXÁMENES ================= --}}
    <h2 style="margin-top:28px;font-size:18px;color:var(--navy-900)">Mis exámenes</h2>

    @forelse ($tarjetas as $t)
        @php $x = $t['examen']; @endphp
        <article class="exam-card {{ $t['abierto'] ? '' : 'cerrado' }}">
            <div class="tit">
                <h3>{{ $x->nombre }}</h3>
                @if ($t['ingreso'])
                    <span class="badge habilitado">Ingreso registrado</span>
                @else
                    <span class="badge {{ $t['estado'] }}">{{ ['habilitado' => 'Habilitado', 'inhabilitado' => 'Inhabilitado', 'sin_evaluar' => 'Sin evaluar'][$t['estado']] }}</span>
                @endif
            </div>

            @if ($t['abierto'])
                <div class="meta">
                    <div class="aula"><small>Aula / ambiente</small><b>{{ $x->ambiente ?: 'Por confirmar' }}</b></div>
                    <div><small>Fecha</small><b>{{ $x->inicio->translatedFormat('d/m/Y') }}</b></div>
                    <div><small>Hora</small><b>{{ $x->hora }}</b></div>
                    <div><small>Duración</small><b>{{ $x->duracion_min ?: '—' }} min</b></div>
                </div>

                @if ($t['estado'] === 'inhabilitado')
                    <div class="aviso err"><b>Estás inhabilitado para este examen.</b>
                        {{ $t['motivo'] ? 'Motivo: '.$t['motivo'].'.' : '' }}
                        {{ $t['observacion'] ? $t['observacion'] : '' }}
                        Consulta con tu docente.</div>
                @elseif ($t['estado'] === 'habilitado')
                    <div class="aviso ok">Estás habilitado. El día del examen lleva tu carnet y muestra tu código QR.</div>
                    <p style="margin-top:12px"><a class="btn" href="{{ route('estudiante.qr') }}"><svg class="i sm"><use href="#i-id"/></svg> Ver mi código QR</a></p>
                @else
                    <div class="aviso info">Todavía no te habilitaron para este examen. Consulta con tu docente.</div>
                @endif
            @else
                <div class="meta">
                    <div><small>Fecha del examen</small><b>{{ $x->inicio->format('d/m/Y') }}</b></div>
                </div>
                <div class="aviso info">
                    El detalle de tu aula y tu QR se mostrará desde el
                    <b>{{ $x->portalAbreEn()->format('d/m/Y H:i') }}</b> ({{ $abreHoras }} horas antes del examen).
                </div>
            @endif
        </article>
    @empty
        <div class="p-card"><p class="muted">No tienes exámenes próximos. Si crees que es un error, consulta con la administración.</p></div>
    @endforelse
@endsection
