
@extends('layouts.app')
@section('title', 'Control de ingreso')

@section('content')
<nav class="crumb">
    <a href="{{ url('/dashboard') }}">Inicio</a> ›
    <b>Control de ingreso</b>
</nav>

<div class="head">
    <div>
        <h1>Control de ingreso</h1>
        <p>Verificación de estudiantes para los exámenes.</p>
    </div>
</div>

<section class="card" style="padding:24px;margin-top:16px">
    <h2>Buscar estudiante</h2>
    <p>Selecciona el método de identificación.</p>

    <form id="form-ingreso">
        @csrf

        <div class="field">
            <label for="modo">Método de búsqueda</label>
            <select id="modo" name="modo">
                <option value="codigo">Código universitario</option>
                <option value="ci">Cédula de identidad (C.I.)</option>
            </select>
        </div>

        <div class="field" id="grupo-codigo" style="margin-top:16px">
            <label for="codigo_universitario">
                Código universitario
            </label>
            <input
                id="codigo_universitario"
                name="codigo_universitario"
                type="text"
                inputmode="numeric"
                maxlength="9"
                placeholder="202400034"
            >
        </div>

        <div id="grupo-ci" hidden>
            <div class="field" style="margin-top:16px">
                <label for="ci">Cédula de identidad</label>
                <input
                    id="ci"
                    name="ci"
                    type="text"
                    inputmode="numeric"
                    maxlength="10"
                    placeholder="7123456"
                >
            </div>

            <div class="field" style="margin-top:16px">
                <label for="ci_complemento">Complemento (opcional)</label>
                <input
                    id="ci_complemento"
                    name="ci_complemento"
                    type="text"
                    maxlength="2"
                    placeholder="A1"
                >
            </div>
        </div>

        <button type="submit" class="btn" id="btn-buscar"
                style="margin-top:20px">
            Buscar estudiante
        </button>
    </form>

    <div id="mensaje" role="status" aria-live="polite"
         style="margin-top:18px"></div>

    <section id="resultado" class="card"
             style="padding:18px;margin-top:16px" hidden>
        <h3>Estudiante encontrado</h3>
        <p><b>Nombre:</b> <span id="r-nombre"></span></p>
        <p><b>Código universitario:</b> <span id="r-codigo"></span></p>
        <p><b>C.I.:</b> <span id="r-ci"></span></p>
        <p><b>Facultad:</b> <span id="r-facultad"></span></p>
        <p><b>Carrera:</b> <span id="r-carrera"></span></p>
        <p><b>Estado:</b> <span id="r-estado"></span></p>
    </section>
</section>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('form-ingreso');
    const modo = document.getElementById('modo');
    const grupoCodigo = document.getElementById('grupo-codigo');
    const grupoCi = document.getElementById('grupo-ci');
    const codigo = document.getElementById('codigo_universitario');
    const ci = document.getElementById('ci');
    const complemento = document.getElementById('ci_complemento');
    const mensaje = document.getElementById('mensaje');
    const resultado = document.getElementById('resultado');
    const boton = document.getElementById('btn-buscar');

    function cambiarModo() {
        const porCi = modo.value === 'ci';

        grupoCodigo.hidden = porCi;
        grupoCi.hidden = !porCi;

        codigo.disabled = porCi;
        codigo.required = !porCi;

        ci.disabled = !porCi;
        ci.required = porCi;
        complemento.disabled = !porCi;

        mensaje.textContent = '';
        resultado.hidden = true;
    }

    modo.addEventListener('change', cambiarModo);
    cambiarModo();

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (boton.disabled) return;

        mensaje.textContent = '';
        resultado.hidden = true;
        boton.disabled = true;
        boton.textContent = 'Buscando...';

        const datos = new FormData(form);

        try {
            const respuesta = await fetch(
                @json(route('ingreso.verificar')),
                {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin',
                    body: datos
                }
            );

            const data = await respuesta.json();

            if (!respuesta.ok) {
                if (respuesta.status === 422 && data.errors) {
                    mensaje.textContent =
                        Object.values(data.errors).flat().join(' ');
                } else {
                    mensaje.textContent = data.mensaje ||
                        'No se encontró al estudiante o ocurrió un error.';
                }
                return;
            }

            if (!data.encontrado) {
                mensaje.textContent = data.mensaje ||
                    'No se encontró al estudiante.';
                return;
            }

            const e = data.estudiante;

            document.getElementById('r-nombre').textContent =
                `${e.nombres || ''} ${e.apellidos || ''}`.trim();

            document.getElementById('r-codigo').textContent =
                e.codigo_universitario || '—';

            document.getElementById('r-ci').textContent =
                `${e.documento_identidad || '—'}${
                    e.ci_complemento ? ' ' + e.ci_complemento : ''
                }`;

            document.getElementById('r-facultad').textContent =
                e.facultad || '—';

            document.getElementById('r-carrera').textContent =
                e.carrera || '—';

            document.getElementById('r-estado').textContent =
                e.estado || '—';

            resultado.hidden = false;
            mensaje.textContent = data.mensaje ||
                'Estudiante encontrado correctamente.';
        } catch (error) {
            mensaje.textContent =
                'No se pudo conectar con el servidor. Intenta nuevamente.';
        } finally {
            boton.disabled = false;
            boton.textContent = 'Buscar estudiante';
        }
    });
});
</script>
@endpush