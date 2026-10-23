@extends('layouts.app')
@section('title', 'Reportes')

@section('content')
    <nav class="crumb" aria-label="Ruta"><a href="{{ url('/dashboard') }}">Inicio</a> › <b>Reportes</b></nav>

    <div class="head">
        <div>
            <h1>Reportes de exámenes</h1>
            <p>Cuántos estudiantes entraron, cuántos no, cuántos fueron inhabilitados y si hubo incidencias.</p>
        </div>
    </div>

    @include('partials.flash')

    <section class="card" style="margin-top:20px">
        <div class="scroll">
            <table>
                <thead>
                    <tr>
                        <th>Examen</th><th>Fecha y hora</th><th>Aula</th>
                        <th style="text-align:right">Habilitados</th>
                        <th style="text-align:right">Ingresaron</th>
                        <th style="text-align:right">No ingresaron</th>
                        <th style="text-align:right">Inhabilitados</th>
                        <th style="text-align:right">Incidencias</th>
                        <th style="text-align:right">Reporte</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($filas as $f)
                    @php $x = $f['examen']; @endphp
                    <tr>
                        <td class="strong">{{ $x->nombre }}<span class="sub">{{ $x->carrera }}</span></td>
                        <td class="num">{{ $x->fecha->format('d/m/Y') }} {{ $x->hora }}</td>
                        <td>{{ $x->ambiente }}</td>
                        <td class="num" style="text-align:right">{{ $f['habilitados'] }}</td>
                        <td class="num" style="text-align:right;color:var(--ok);font-weight:700">{{ $f['ingresaron'] }}</td>
                        <td class="num" style="text-align:right">{{ $f['no_ingresaron'] }}</td>
                        <td class="num" style="text-align:right">{{ $f['inhabilitados'] }}</td>
                        <td class="num" style="text-align:right">
                            @if ($f['incidencias'] > 0)
                                <span class="badge inhabilitado">{{ $f['incidencias'] }} abierta(s)</span>
                            @else
                                0
                            @endif
                        </td>
                        <td style="text-align:right"><a class="link-btn ok" href="{{ route('reportes.show', $x) }}">Ver reporte</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="empty">Todavía no hay exámenes registrados.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="foot"><span>{{ $examenes->total() }} examen(es)</span><span>{{ $examenes->links() }}</span></div>
    </section>
@endsection
