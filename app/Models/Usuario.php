<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class Usuario extends Authenticatable
{
    protected $table = 'usuario';
    protected $primaryKey = 'id_usuario';
    public $timestamps = false;

    protected $fillable = ['usuario', 'password_hash', 'nombres', 'apellidos', 'estado'];
    protected $hidden   = ['password_hash'];

    public function getAuthPassword()      { return $this->password_hash; }
    public function getRememberTokenName() { return ''; }

    public function getNombreCompletoAttribute(): string
    {
        return "{$this->apellidos}, {$this->nombres}";
    }

    public function roles()
    {
        return $this->belongsToMany(Rol::class, 'usuario_rol', 'id_usuario', 'id_rol');
    }

    public function scopeBuscar($q, ?string $texto)
    {
        return $q->when($texto, fn ($q) => $q->where(function ($w) use ($texto) {
            $w->where('usuario', 'like', "%{$texto}%")
              ->orWhere('nombres', 'like', "%{$texto}%")
              ->orWhere('apellidos', 'like', "%{$texto}%");
        }));
    }
}