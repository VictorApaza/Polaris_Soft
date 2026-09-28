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
        'codigo_universitario', 'documento_identidad', 'nombres', 'apellidos',
        'carrera', 'correo_institucional', 'estado',
    ];

    /** "Romero Silva, Gabriel Fernando" */
    public function getNombreCompletoAttribute(): string
    {
        return "{$this->apellidos}, {$this->nombres}";
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