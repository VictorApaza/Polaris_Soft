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
                    <form id="form-foto" method="POST" action="{{ route('estudiante.foto.subir') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="field">
                            <label for="foto">Elige una foto de tu rostro (JPG o PNG, máximo {{ config('sciem.foto_max_kb') / 1024 }} MB)</label>
                            <input id="foto" type="file" name="foto" accept="image/jpeg,image/png" required>
                        </div>
                        <div id="foto-aviso" class="aviso" style="display:none;margin-top:10px" role="alert"></div>
                        <img id="foto-previa" alt="Vista previa" style="display:none;margin-top:10px;max-width:180px;border-radius:10px;border:1px solid var(--line)">
                        <button class="btn" type="submit" style="margin-top:10px">Subir foto</button>
                    </form>
                    <small class="sub">Debe ser reciente, de frente, nítida, con buena luz y sin lentes ni gorra, para que el personal pueda reconocerte. El sistema revisa tu foto al subirla y te avisa si no es válida.</small>
                @else
                    <div class="aviso warn">
                        Ahora no puedes subir ni cambiar tu foto. Solo se puede desde {{ $abreHoras }} horas hasta {{ $limiteHoras }} horas antes del examen.
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- ================= CAMBIAR CONTRASEÑA ================= --}}
    <section class="p-card" style="margin-top:20px">
        <h2>Cambiar mi contraseña</h2>
        <p class="sub" style="margin-bottom:10px">Si nunca la cambiaste, tu contraseña actual es tu cédula de identidad. La nueva debe tener al menos 8 caracteres.</p>
        <form method="POST" action="{{ route('estudiante.password') }}" style="display:grid;gap:10px;max-width:420px">
            @csrf
            <div class="field">
                <label for="pw-actual">Contraseña actual</label>
                <input id="pw-actual" type="password" name="password_actual" autocomplete="current-password" required>
                @error('password_actual')<p class="err">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="pw-nueva">Contraseña nueva</label>
                <input id="pw-nueva" type="password" name="password" minlength="8" autocomplete="new-password" required>
            </div>
            <div class="field">
                <label for="pw-conf">Repite la contraseña nueva</label>
                <input id="pw-conf" type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required>
            </div>
            <div><button class="btn" type="submit">Cambiar contraseña</button></div>
        </form>
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

@push('scripts')
<script>
(function () {
    const input = document.getElementById('foto');
    if (!input) return;
    const form = document.getElementById('form-foto');
    const aviso = document.getElementById('foto-aviso');
    const previa = document.getElementById('foto-previa');
    const boton = form.querySelector('button[type=submit]');
    const MAX = {{ (int) config('sciem.foto_max_kb') }} * 1024;
    const NITIDEZ_MIN = {{ (float) config('sciem.foto_nitidez_min') }};
    const BRILLO_MIN = {{ (float) config('sciem.foto_brillo_min') }};
    const BRILLO_MAX = {{ (float) config('sciem.foto_brillo_max') }};

    function mostrar(texto, ok) {
        aviso.style.display = 'block';
        aviso.className = 'aviso ' + (ok ? 'ok' : 'err');
        aviso.textContent = texto;
        boton.disabled = !ok;
    }

    input.addEventListener('change', () => {
        const f = input.files[0];
        aviso.style.display = 'none'; previa.style.display = 'none'; boton.disabled = false;
        if (!f) return;
        if (!/^image\/(jpeg|png)$/.test(f.type)) { mostrar('La foto no es válida: debe ser JPG o PNG.', false); return; }
        if (f.size > MAX) { mostrar('La foto pesa demasiado (máximo ' + (MAX / 1048576) + ' MB).', false); return; }

        const url = URL.createObjectURL(f);
        const img = new Image();
        img.onload = async () => {
            previa.src = url; previa.style.display = 'block';
            if (img.width < 200 || img.height < 200) { mostrar('La foto es muy pequeña: mínimo 200 x 200 píxeles.', false); return; }

            const w = Math.min(320, img.width), h = Math.round(img.height * w / img.width);
            const c = document.createElement('canvas'); c.width = w; c.height = h;
            const g = c.getContext('2d', { willReadFrequently: true });
            g.drawImage(img, 0, 0, w, h);
            const d = g.getImageData(0, 0, w, h).data;
            const gris = new Float32Array(w * h);
            let suma = 0;
            for (let i = 0; i < w * h; i++) { gris[i] = 0.299 * d[i * 4] + 0.587 * d[i * 4 + 1] + 0.114 * d[i * 4 + 2]; suma += gris[i]; }
            const brillo = suma / (w * h);
            let s1 = 0, s2 = 0, n = 0;
            for (let y = 1; y < h - 1; y++) for (let x = 1; x < w - 1; x++) {
                const i = y * w + x;
                const lap = 4 * gris[i] - gris[i - 1] - gris[i + 1] - gris[i - w] - gris[i + w];
                s1 += lap; s2 += lap * lap; n++;
            }
            const nitidez = s2 / n - (s1 / n) ** 2;

            if (nitidez < NITIDEZ_MIN) { mostrar('La foto no es válida: está borrosa. Tómala de nuevo con la cámara quieta y enfocada.', false); return; }
            if (brillo < BRILLO_MIN) { mostrar('La foto no es válida: está muy oscura. Tómala con buena luz.', false); return; }
            if (brillo > BRILLO_MAX) { mostrar('La foto no es válida: tiene demasiada luz. Evita el contraluz y el flash directo.', false); return; }

            // Si el navegador sabe detectar rostros (Chrome/Edge con la API activada), se exige exactamente uno.
            if ('FaceDetector' in window) {
                try {
                    const caras = await new window.FaceDetector({ fastMode: true, maxDetectedFaces: 3 }).detect(c);
                    if (caras.length === 0) { mostrar('La foto no es válida: no se ve tu rostro. Debe ser de frente y descubierto.', false); return; }
                    if (caras.length > 1) { mostrar('La foto no es válida: debe salir una sola persona.', false); return; }
                } catch (e) { /* sin detector: decide el servidor */ }
            }
            mostrar('La foto se ve bien. Al subirla se hará una última revisión automática.', true);
        };
        img.onerror = () => mostrar('La foto no es válida: no se pudo leer la imagen.', false);
        img.src = url;
    });
})();
</script>
@endpush
