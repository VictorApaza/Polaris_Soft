<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Docente extends Model
{
    protected $fillable = [
        'nombre',
        'ci',
    ];

    public function grupos()
    {
        return $this->hasMany(Grupo::class);
    }

    public function usuario()
    {
        return $this->hasOne(
            User::class,
            'docente_id',
            'id'
        );
    }
}