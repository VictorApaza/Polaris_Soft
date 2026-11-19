{{-- Próximos exámenes con las acciones directas de cada rol (menos clics). Variables: $proximos --}}
@php $rolActual = auth()->user()->rol; @endphp
<div class="section-title">
    <span><svg class="i sm"><use href="#i-exams"/></svg> Próximos exámenes</span>
    @if ($rolActual === 'administrador')<a href="{{ route('examenes.index') }}">Ver cronograma completo ›</a>@endif
</div>
<section class="card">
    <div class="scroll">
        <table>
            <thead>
                <tr><th>Examen</th><th>Fecha</th><th>Hora</th><th>Ambiente</th><th>Estado</th><th class="t-right">Acciones</th></tr>
            </thead>
            <tbody>
            @forelse ($proximos as $e)
                <tr>
                    <td class="strong">{{ $e->nombre }}<span class="sub">{{ $e->carrera }}</span></td>
                    <td class="num">{{ $e->fecha->format('d/m/Y') }}</td>
                    <td class="num">{{ $e->hora }}</td>
                    <td><span class="place"><svg class="i sm"><use href="#i-building"/></svg>{{ $e->ambiente }}</span></td>
                    <td><span class="badge {{ $e->estado }}">{{ \App\Models\Examen::ESTADOS[$e->estado] ?? $e->estado }}</span></td>
                    <td>
                        <div class="fila-acciones">
                            @if ($rolActual === 'docente')
                                <a class="btn-mini" href="{{ route('habilitaciones.index', $e) }}">Habilitar</a>
                                <a class="btn-mini" href="{{ route('en-vivo.examen', $e) }}">En vivo</a>
                                <a class="btn-mini" href="{{ route('reportes.show', $e) }}">Reporte</a>
                            @elseif ($rolActual === 'control')
                                <a class="btn-mini" href="{{ route('verificacion.index', ['examen' => $e->id]) }}">Verificar ingreso</a>
                                <a class="btn-mini" href="{{ route('en-vivo.examen', $e) }}">En vivo</a>
                            @else
                                <a class="btn-mini" href="{{ route('habilitaciones.index', $e) }}">Habilitados</a>
                                <a class="btn-mini" href="{{ route('en-vivo.examen', $e) }}">En vivo</a>
                                <a class="btn-mini" href="{{ route('reportes.show', $e) }}">Reporte</a>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">
                    No hay exámenes próximos.
                    @if ($rolActual === 'administrador')<a href="{{ route('examenes.index') }}">Registrar un examen</a>@endif
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="foot"><span>Mostrando los {{ $proximos->count() }} exámenes más próximos</span></div>
</section>
