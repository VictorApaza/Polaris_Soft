<?php

return [
    /*
    | Zona horaria en la que se escriben las fechas y horas de los exámenes (la app guarda sus marcas en UTC).
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
];
