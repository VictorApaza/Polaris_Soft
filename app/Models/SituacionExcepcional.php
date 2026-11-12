<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * RQ21: situación excepcional registrada durante un examen (problema de identificación, cambio de ambiente,
 * estudiante no habilitado, otra) para que el docente o el administrador la revise.
 *
 * - La fecha y hora la fija SIEMPRE el servidor al crear el registro (zona America/La_Paz) y el estado nace
 *   PENDIENTE: `fecha_hora` y `estado` no son asignables en masa, así que cualquier valor enviado desde el
 *   navegador se descarta.
 * - Lo único que cambia después de registrada es el estado: PENDIENTE → REVISADA (marcarRevisada()).
 */
class SituacionExcepcional extends Model
{
    protected $table = 'situacion_excepcional';

    /** No hay created_at/updated_at: la marca de tiempo oficial es fecha_hora. */
    public $timestamps = false;

    public const PROBLEMA_IDENTIFICACION = 'PROBLEMA_IDENTIFICACION';
    public const CAMBIO_AMBIENTE = 'CAMBIO_AMBIENTE';
    public const ESTUDIANTE_NO_HABILITADO = 'ESTUDIANTE_NO_HABILITADO';
    public const OTRA = 'OTRA';

    /** Categoría => texto para mostrar. */
    public const CATEGORIAS = [
        self::PROBLEMA_IDENTIFICACION => 'Problema de identificación',
        self::CAMBIO_AMBIENTE => 'Cambio de ambiente',
        self::ESTUDIANTE_NO_HABILITADO => 'Estudiante no habilitado',
        self::OTRA => 'Otra situación',
    ];

    /** Categorías que no tienen sentido sin un estudiante concreto. */
    public const REQUIEREN_ESTUDIANTE = [self::CAMBIO_AMBIENTE, self::ESTUDIANTE_NO_HABILITADO];

    public const PENDIENTE = 'PENDIENTE';
    public const REVISADA = 'REVISADA';

    /** fecha_hora y estado NO están aquí a propósito: los pone el servidor. */
    protected $fillable = [
        'examen_id', 'estudiante_id', 'categoria', 'detalle',
        'ambiente_origen_id', 'ambiente_destino_id', 'registrado_por',
    ];

    protected $casts = [
        'fecha_hora' => 'datetime',
        'revisada_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (SituacionExcepcional $situacion) {
            $situacion->fecha_hora = now()->startOfSecond();
            $situacion->estado = self::PENDIENTE;
        });
    }

    public function examen()
    {
        return $this->belongsTo(Examen::class, 'examen_id');
    }

    public function estudiante()
    {
        return $this->belongsTo(Estudiante::class, 'estudiante_id', 'id_estudiante');
    }

    public function ambienteOrigen()
    {
        return $this->belongsTo(Ambiente::class, 'ambiente_origen_id');
    }

    public function ambienteDestino()
    {
        return $this->belongsTo(Ambiente::class, 'ambiente_destino_id');
    }

    public function registrador()
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function revisor()
    {
        return $this->belongsTo(User::class, 'revisada_por');
    }

    public function getCategoriaEtiquetaAttribute(): string
    {
        return self::CATEGORIAS[$this->categoria] ?? (string) $this->categoria;
    }

    /** "23/10/2026 17:58:12" (dd/mm/aaaa HH:mm:ss) en hora de Bolivia, igual que `ingreso` y `expulsion`. */
    public function getFechaHoraLocalAttribute(): string
    {
        return $this->fecha_hora->copy()->timezone(TransparenciaIngreso::zonaHoraria())->format('d/m/Y H:i:s');
    }

    public function getRevisadaAtLocalAttribute(): ?string
    {
        return $this->revisada_at?->copy()->timezone(TransparenciaIngreso::zonaHoraria())->format('d/m/Y H:i:s');
    }

    /**
     * Marca la situación como REVISADA (con quién y cuándo, hora del servidor).
     * Devuelve true si este llamado la cambió, o false si ya estaba revisada (no se pisa quién la revisó primero).
     * La condición `estado = PENDIENTE` va en el propio UPDATE, así dos revisores a la vez no se pisan.
     */
    public function marcarRevisada(User $usuario): bool
    {
        $filas = static::whereKey($this->getKey())
            ->where('estado', self::PENDIENTE)
            ->update([
                'estado' => self::REVISADA,
                'revisada_por' => $usuario->getKey(),
                'revisada_at' => now()->startOfSecond(),
            ]);

        $this->refresh();

        return $filas === 1;
    }
}
