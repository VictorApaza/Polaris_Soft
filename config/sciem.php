<?php

return [
    /*
    | Zona horaria en la que se escriben las fechas y horas de los exámenes (la app guarda sus marcas en la zona de config/app.php: America/La_Paz).
    */
    'zona_horaria' => env('SCIEM_ZONA_HORARIA', 'America/La_Paz'),

    /*
    | Portal del estudiante: cuántas horas ANTES del examen se abre (ve su aula, su QR y puede subir su foto).
    */
    'portal_abre_horas' => (int) env('SCIEM_PORTAL_ABRE_HORAS', 72),

    /*
    | Fecha límite para subir o corregir la foto: cuántas horas ANTES del examen se cierra la subida.
    | Pasada esa hora, el docente puede inhabilitar a quien no tenga una foto válida.
    */
    'foto_limite_horas' => (int) env('SCIEM_FOTO_LIMITE_HORAS', 48),

    /* Peso máximo de la foto en KB. */
    'foto_max_kb' => (int) env('SCIEM_FOTO_MAX_KB', 2048),

    /*
    | Revisión automática de la foto al subirla (ver App\Services\ValidadorFotoRostro).
    | Si rechaza fotos buenas, baja los mínimos; si deja pasar fotos malas, súbelos. SCIEM_FOTO_VALIDAR=false la apaga.
    */
    'foto_validar' => filter_var(env('SCIEM_FOTO_VALIDAR', true), FILTER_VALIDATE_BOOLEAN),
    'foto_nitidez_min' => (float) env('SCIEM_FOTO_NITIDEZ_MIN', 40),
    'foto_brillo_min' => (float) env('SCIEM_FOTO_BRILLO_MIN', 55),
    'foto_brillo_max' => (float) env('SCIEM_FOTO_BRILLO_MAX', 215),
    'foto_contraste_min' => (float) env('SCIEM_FOTO_CONTRASTE_MIN', 25),
    'foto_piel_min' => (float) env('SCIEM_FOTO_PIEL_MIN', 0.08),
    'foto_lentes_ratio' => (float) env('SCIEM_FOTO_LENTES_RATIO', 0.5),
];
