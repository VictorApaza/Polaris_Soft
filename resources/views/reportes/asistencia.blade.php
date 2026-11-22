@extends('layouts.app')
@section('title', 'Reporte de asistencia')

@section('content')
    @include('reportes._encabezado', [
        'titulo' => 'Reporte de asistencia',
        'descripcion' => 'Los estudiantes que ingresaron a este examen, con la hora, el método y quién registró el ingreso.',
        'ruta' => 'reportes.asistencia',
    ])

    <section class="stats cinco">
        <div class="stat"><div><small>Habilitados</small><strong>{{ $resumen['habilitados'] }}</strong></div><span class="ic"><svg class="i"><use href="#i-users"/></svg></span></div>
        <div class="stat ok"><div><small>Ingresaron</small><strong>{{ $resumen['ingresaron'] }}</strong></div><span class="ic"><svg class="i"><use href="#i-check"/></svg></span></div>
        <div class="stat"><div><small>Ausentes</small><strong>{{ $resumen['ausentes'] }}</strong></div><span class="ic"><svg class="i"><use href="#i-calendar"/></svg></span></div>
        <div class="stat off"><div><small>Expulsados</small><strong>{{ $resumen['expulsados'] }}</strong></div><span class="ic"><svg class="i"><use href="#i-lock"/></svg></span></div>
        <div class="stat"><div><small>% de asistencia</small><strong>{{ number_format($resumen['porcentaje'], 1, ',', '') }} %</strong></div><span class="ic"><svg class="i"><use href="#i-dashboard"/></svg></span></div>
    </section>

    <div class="aviso {{ $resumen['cuadra'] ? 'ok' : 'warn' }}" role="status">
        @if ($resumen['cuadra'])
            Cuadra con los registros: {{ $resumen['presentes_habilitados'] }} habilitado(s) que ingresaron + {{ $resumen['ausentes'] }} ausente(s) = {{ $resumen['habilitados'] }} habilitado(s).
        @else
            Los números no cuadran: revisa los datos de habilitación e ingreso de este examen.
        @endif
        @if ($resumen['ingresaron'] > $resumen['presentes_habilitados'])
            Además, {{ $resumen['ingresaron'] - $resumen['presentes_habilitados'] }} estudiante(s) ingresaron y luego fueron inhabilitados.
        @endif
    </div>

    <section class="card" style="margin-top:18px">
        <div class="scroll">
            <table>
                <thead>
                    <tr>
                        <th class="t-right">N°</th><th>Hora de ingreso</th><th>Estudiante</th><th>Cédula</th><th>Carrera</th>
                        <th>Método</th><th>Aula</th><th>Controlador</th><th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($filas as $f)
                    <tr>
                        <td class="num t-right">{{ $f['n'] }}</td>
                        <td class="num strong">{{ $f['fecha_hora'] }}</td>
                        <td class="strong">{{ $f['nombre'] }}<span class="sub">{{ $f['codigo'] }}</span></td>
                        <td class="num">{{ $f['ci'] }}</td>
                        <td>{{ $f['carrera'] }}</td>
                        <td>{{ $f['metodo'] }}</td>
                        <td>{{ $f['ambiente'] }}</td>
                        <td>{{ $f['controlador'] }}</td>
                        <td>
                            @if ($f['estado'] === 'Expulsado')
                                <span class="badge inhabilitado">Expulsado</span>
                                <span class="sub" style="max-width:260px;white-space:normal">{{ $f['detalle'] }}</span>
                            @else
                                <span class="badge activo">Presente</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="empty">Todavía nadie registró ingreso en este examen.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @include('reportes._paginador', ['unidad' => 'ingreso(s)'])
    </section>
@endsection
