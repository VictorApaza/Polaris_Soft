<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Examen extends Model
{
    protected $table = 'examenes';

    protected $fillable = [
        'materia_id', 'asignatura_id', 'carrera', 'fecha', 'hora_inicio', 'duracion_min', 'ambiente',
        'capacidad', 'estado', 'normas_admision', 'normas_salida',
    ];

    protected $casts = ['fecha' => 'date'];

    public const ESTADOS = ['programado' => 'Programado', 'abierto' => 'Abierto', 'finalizado' => 'Finalizado'];

    public function materia()
    {
        return $this->belongsTo(Materia::class);
    }

    public function asignatura()
    {
        return $this->belongsTo(Asignatura::class, 'asignatura_id', 'id_asignatura');
    }

    /** Momento exacto de inicio (fecha + hora, en la zona horaria de los exámenes). */
    public function getInicioAttribute(): \Carbon\Carbon
    {
        return \Carbon\Carbon::parse($this->fecha->format('Y-m-d').' '.$this->hora_inicio, config('sciem.zona_horaria'));
    }

    public function getFinAttribute(): \Carbon\Carbon
    {
        return $this->inicio->copy()->addMinutes((int) ($this->duracion_min ?: 120));
    }

    /** Desde cuándo pueden entrar los estudiantes al portal para ver su aula y subir su foto. */
    public function portalAbreEn(): \Carbon\Carbon
    {
        return $this->inicio->copy()->subHours(config('sciem.portal_abre_horas'));
    }

    /** Hasta cuándo pueden subir o corregir su foto. */
    public function limiteFoto(): \Carbon\Carbon
    {
        return $this->inicio->copy()->subHours(config('sciem.foto_limite_horas'));
    }

    /** Nombre legible del examen: la asignatura (o materia). */
    public function getNombreAttribute(): string
    {
        return $this->asignatura->nombre ?? $this->materia->nombre ?? 'Examen';
    }

    /** Hora en formato HH:MM (la columna time devuelve HH:MM:SS). */
    public function getHoraAttribute(): string
    {
        return substr((string) $this->hora_inicio, 0, 5);
    }

    /** Estudiantes asignados a la materia del examen (RQ asignaciones). */
    public function getAsignadosAttribute(): int
    {
        if ($this->asignatura_id) {
            return \Illuminate\Support\Facades\DB::table('estudiante_asignatura')
                ->where('id_asignatura', $this->asignatura_id)
                ->count();
        }

        return $this->materia?->asignaciones_count ?? $this->materia?->asignaciones()->count() ?? 0;
    }

    public function scopeBuscar(Builder $q, ?string $texto): Builder
    {
        return $q->when($texto, fn ($q) => $q->where(function ($w) use ($texto) {
            $w->where('carrera', 'like', "%{$texto}%")
              ->orWhereHas('asignatura', fn ($a) => $a
                  ->where('nombre', 'like', "%{$texto}%")
                  ->orWhere('codigo', 'like', "%{$texto}%"))
              ->orWhereHas('materia', fn ($m) => $m
                  ->where('nombre', 'like', "%{$texto}%")
                  ->orWhere('sigla', 'like', "%{$texto}%"));
        }));
    }
}
