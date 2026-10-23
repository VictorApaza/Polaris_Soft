<?php

namespace App\Services;

use App\Models\Asignacion;
use App\Models\Estudiante;
use App\Models\Examen;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reglas del portal del estudiante:
 *  - se abre 72 h antes del examen (config sciem.portal_abre_horas): ahí ve su aula y su QR;
 *  - puede subir o corregir su foto desde que se abre hasta 48 h antes (config sciem.foto_limite_horas).
 */
class PortalEstudianteService
{
    /** Exámenes próximos (o en curso) de un estudiante, en orden. */
    public function examenesDe(Estudiante $estudiante): Collection
    {
        $asignaturaIds = DB::table('estudiante_asignatura')
            ->where('id_estudiante', $estudiante->getKey())
            ->pluck('id_asignatura')->all();

        $materiaIds = Asignacion::where('estudiante_id', $estudiante->getKey())
            ->pluck('materia_id')->all();

        return Examen::with(['asignatura', 'materia'])
            ->where('estado', '!=', 'finalizado')
            ->where(function ($q) use ($asignaturaIds, $materiaIds, $estudiante) {
                $q->where(function ($w) use ($asignaturaIds, $estudiante) {
                    $w->whereIn('asignatura_id', $asignaturaIds)
                        ->where(fn ($c) => $c->whereNull('carrera')->orWhere('carrera', $estudiante->carrera));
                })->orWhere(function ($w) use ($materiaIds) {
                    $w->whereNull('asignatura_id')->whereIn('materia_id', $materiaIds);
                });
            })
            ->orderBy('fecha')->orderBy('hora_inicio')
            ->get()
            ->filter(fn (Examen $x) => $x->fin->isFuture())
            ->values();
    }

    public function portalAbierto(Examen $examen): bool
    {
        return now()->gte($examen->portalAbreEn());
    }

    public function puedeSubirFoto(Examen $examen): bool
    {
        return now()->gte($examen->portalAbreEn()) && now()->lt($examen->limiteFoto());
    }

    /** El primer examen que permite subir foto ahora mismo (null si ninguno). */
    public function examenQuePermiteFoto(Collection $examenes): ?Examen
    {
        return $examenes->first(fn (Examen $x) => $this->puedeSubirFoto($x));
    }
}
