<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Docente extends Model
{
    protected $fillable = ['nombre', 'apellido', 'grado', 'ci', 'ci_complemento', 'user_id'];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Ficha de docente que corresponde a una cuenta de usuario con rol docente.
     * Primero por el vínculo (user_id); si no lo hay, por coincidencia de nombre y apellido
     * (y en ese caso deja guardado el vínculo para la próxima vez).
     */
    public static function paraUsuario(User $usuario): ?self
    {
        if ($usuario->rol !== 'docente') {
            return null;
        }

        $vinculado = static::where('user_id', $usuario->id)->first();
        if ($vinculado) {
            return $vinculado;
        }

        $nombre = mb_strtolower(trim((string) $usuario->nombre));
        $apellido = mb_strtolower(trim((string) $usuario->apellido));
        if ($nombre === '' || $apellido === '') {
            return null;
        }

        $docente = static::whereNull('user_id')
            ->whereRaw('LOWER(nombre) = ?', [$nombre])
            ->whereRaw('LOWER(apellido) = ?', [$apellido])
            ->first();

        if ($docente) {
            $docente->forceFill(['user_id' => $usuario->id])->save();
        }

        return $docente;
    }

    /** "7123456-1A" (con complemento) o "7123456". */
    public function getCarnetCompletoAttribute(): string
    {
        return $this->ci_complemento ? "{$this->ci}-{$this->ci_complemento}" : (string) $this->ci;
    }

    public function grupos()
    {
        return $this->hasMany(Grupo::class);
    }

    /** "Juan" + "Pérez" -> "Ing. Juan Pérez" (si tiene grado) */
    public function getNombreCompletoAttribute(): string
    {
        $base = trim($this->nombre.' '.$this->apellido);

        return $this->grado ? "{$this->grado} {$base}" : $base;
    }
}
