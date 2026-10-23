<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Código QR · {{ $estudiante->codigo_universitario }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; background:#f3f4f6; margin:0; padding:24px; color:#1f2937; }
        .card { max-width:360px; margin:auto; background:#fff; border:1px solid #e5e7eb; border-radius:16px; padding:28px; text-align:center; }
        h1 { margin:0 0 4px; font-size:20px; color:#1e3a8a; }
        .sub { color:#6b7280; font-size:13px; margin:0 0 20px; }
        #qr { display:inline-block; padding:12px; background:#fff; border:1px solid #e5e7eb; border-radius:12px; }
        .dato { margin-top:18px; font-size:14px; line-height:1.6; }
        .btns { margin-top:20px; display:flex; gap:8px; justify-content:center; }
        .btn { background:#1e3a8a; color:#fff; border:0; border-radius:8px; padding:10px 18px; font-weight:bold; cursor:pointer; text-decoration:none; font-size:14px; }
        .btn.ghost { background:#e5e7eb; color:#1f2937; }
        @media print { body { background:#fff; } .btns { display:none; } .card { border:0; } }
    </style>
</head>
<body>
    <div class="card">
        <h1>SCIEM · Código QR</h1>
        <p class="sub">Muéstralo al personal de control para ingresar al examen</p>

        <div id="qr"></div>

        <div class="dato">
            <b>{{ $estudiante->apellidos }}, {{ $estudiante->nombres }}</b><br>
            Código: {{ $estudiante->codigo_universitario }}<br>
            {{ $estudiante->carrera }}
        </div>

        <div class="btns">
            <button class="btn" type="button" onclick="window.print()">Imprimir</button>
            @isset($volverPanel)
                <a class="btn ghost" href="{{ $volverPanel }}">Volver a mi panel</a>
            @else
                @unless ($publico)
                    <a class="btn ghost" href="{{ route('estudiantes.index') }}">Volver</a>
                @endunless
            @endisset
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        new QRCode(document.getElementById('qr'), {
            text: @json($token),
            width: 260,
            height: 260,
            correctLevel: QRCode.CorrectLevel.M,
        });
    </script>
</body>
</html>
