<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'rol_id',
        'estudiante_id',
        'docente_id',
        'activo',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'activo' => 'boolean',
    ];
}

    public function rol()
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }

    public function estudiante()
    {
        return $this->belongsTo(
            Estudiante::class,
            'estudiante_id',
            'id_estudiante'
        );
    }

    public function docente()
    {
        return $this->belongsTo(
            Docente::class,
            'docente_id',
            'id'
        );
    }
}