<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Estudiante extends Model
{
    protected $table = 'estudiante';
    protected $primaryKey = 'id_estudiante';
    public $timestamps = false;

    protected $fillable = [
        'facultad', 'codigo_universitario', 'documento_identidad', 'ci_complemento',
        'nombres', 'apellidos', 'carrera', 'correo_institucional', 'estado',
    ];

    public const FOTO_ESTADOS = [
        'SIN_FOTO' => 'Sin foto',
        'PENDIENTE' => 'Pendiente de revisión',
        'APROBADA' => 'Aprobada',
        'OBSERVADA' => 'Observada',
    ];

    public function getTieneFotoAttribute(): bool
    {
        return filled($this->foto_path);
    }

    /** ¿La foto sirve para el examen? (subida y no observada por el docente) */
    public function getFotoValidaAttribute(): bool
    {
        return $this->tiene_foto && in_array($this->foto_estado, ['PENDIENTE', 'APROBADA'], true);
    }

    public function getFotoEstadoEtiquetaAttribute(): string
    {
        return self::FOTO_ESTADOS[$this->foto_estado ?: 'SIN_FOTO'] ?? 'Sin foto';
    }

    /** Dirección de la foto para el personal (administrador, docente, control). null si no tiene. */
    public function getFotoUrlAttribute(): ?string
    {
        if (! $this->tiene_foto) {
            return null;
        }

        return route('estudiantes.foto', $this->getKey()).'?v='.($this->foto_subida_at ? strtotime((string) $this->foto_subida_at) : 0);
    }

    /** "Romero Silva, Gabriel Fernando" */
    public function getNombreCompletoAttribute(): string
    {
        return "{$this->apellidos}, {$this->nombres}";
    }

    /** "7123456-A1" (con complemento) o "7123456" (sin él). */
    public function getCarnetCompletoAttribute(): string
    {
        return $this->ci_complemento
            ? "{$this->documento_identidad}-{$this->ci_complemento}"
            : $this->documento_identidad;
    }

    public function scopeBuscar(Builder $q, ?string $texto): Builder
    {
        return $q->when($texto, fn ($q) => $q->where(function ($w) use ($texto) {
            $w->where('codigo_universitario', 'like', "%{$texto}%")
              ->orWhere('documento_identidad', 'like', "%{$texto}%")
              ->orWhere('nombres', 'like', "%{$texto}%")
              ->orWhere('apellidos', 'like', "%{$texto}%");
        }));
    }
}
