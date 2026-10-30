@extends('layouts.app')
@section('title', 'Materias')

@section('content')
    <nav class="crumb" aria-label="Ruta"><a href="{{ url('/dashboard') }}">Inicio</a> › <b>Materias</b></nav>

    <div class="head">
        <div>
            <h1>Mis materias</h1>
            <p>Solo aparecen las materias y grupos que dictas.</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <a class="btn ghost" href="{{ route('estudiantes.plantilla') }}">⬇ Plantilla CSV de estudiantes</a>
            <a class="btn" href="{{ route('estudiantes.importar.form') }}"><svg class="i"><use href="#i-plus"/></svg> Importar estudiantes</a>
        </div>
    </div>

    @include('partials.flash')

    @if (! $docente)
        <div class="banner" style="margin-top:20px">
            <span class="ic"><svg class="i"><use href="#i-info"/></svg></span>
            <div><b>Tu cuenta todavía no está vinculada a una ficha de docente</b>
                <small>Pídele al administrador que registre tu ficha en Usuarios › Docentes con tu mismo nombre y apellido (o que la vincule a tu cuenta).</small></div>
        </div>
    @elseif ($materias->isEmpty())
        <div class="banner" style="margin-top:20px">
            <span class="ic"><svg class="i"><use href="#i-info"/></svg></span>
            <div><b>Aún no tienes grupos asignados</b>
                <small>Cuando el administrador te asigne como docente de un grupo, la materia aparecerá aquí.</small></div>
        </div>
    @else
        <section class="card" style="margin-top:20px">
            <div class="scroll">
                <table>
                    <thead><tr><th>Sigla</th><th>Materia</th><th>Carrera</th><th>Mis grupos</th></tr></thead>
                    <tbody>
                    @foreach ($materias as $fila)
                        @php $m = $fila['materia']; @endphp
                        <tr>
                            <td class="strong num">{{ $m->sigla ?: '—' }}</td>
                            <td class="strong">{{ $m->nombre }}</td>
                            <td>{{ $m->carrera ?: '—' }}<span class="sub">{{ $m->facultad }}</span></td>
                            <td>
                                @foreach ($fila['grupos'] as $g)
                                    <span class="chip" style="margin:2px 4px 2px 0">{{ $g->nombre }} · <b>{{ $estudiantesPorGrupo[$g->id] ?? 0 }} estudiantes</b></span>
                                @endforeach
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="foot"><span>{{ $materias->count() }} materia(s) a tu cargo</span></div>
        </section>
    @endif
@endsection
