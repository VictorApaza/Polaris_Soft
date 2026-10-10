<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MotivoInhabilitacion extends Model
{
    protected $table = 'motivo_inhabilitacion';

    protected $fillable = ['descripcion'];
}
