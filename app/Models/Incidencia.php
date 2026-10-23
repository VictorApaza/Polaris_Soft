<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Hecho irregular durante un examen: intento de ingreso repetido, suplantación, foto que no coincide… */
class Incidencia extends Model
{
    protected $table = 'incidencia';

    public $timestamps = false;

    public const TIPOS = [
        'INTENTO_DUPLICADO' => 'Intento de ingreso repetido',
        'SUPLANTACION_SOSPECHADA' => 'Posible suplantación',
        'FOTO_NO_COINCIDE' => 'La foto no coincide',
        'TRAMPA' => 'Trampa durante el examen',
        'OTRO' => 'Otra incidencia',
    ];

    public const METODOS = [
        'CODIGO_UNIVERSITARIO' => 'Código universitario',
        'DOCUMENTO_IDENTIDAD' => 'Cédula de identidad',
        'CODIGO_QR' => 'Código QR',
    ];

    protected $fillable = [
        'examen_id', 'estudiante_id', 'tipo', 'metodo', 'detalle', 'controlador_id',
        'ingreso_original_at', 'fecha_hora', 'estado', 'revisada_por', 'revisada_at',
    ];

    protected $casts = [
        'ingreso_original_at' => 'datetime',
        'fecha_hora' => 'datetime',
        'revisada_at' => 'datetime',
    ];

    public function examen()
    {
        return $this->belongsTo(Examen::class);
    }

    public function estudiante()
    {
        return $this->belongsTo(Estudiante::class, 'estudiante_id', 'id_estudiante');
    }

    public function controlador()
    {
        return $this->belongsTo(User::class, 'controlador_id');
    }

    public function getTipoEtiquetaAttribute(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }

    public function getMetodoEtiquetaAttribute(): string
    {
        return self::METODOS[$this->metodo] ?? ($this->metodo ?: '—');
    }
}
