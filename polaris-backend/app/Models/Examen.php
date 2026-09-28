<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Materia;

class Examen extends Model
{
    use HasFactory;

    protected $table = 'examenes';

    protected $primaryKey = 'id_examen';

    protected $fillable = [
        'materia_id',
        'fecha',
        'hora',
        'duracion',
        'ambiente',
        'normas_generales',
        'normas_particulares',
    ];

    protected $casts = [
        'fecha' => 'date',
        'duracion' => 'integer',
    ];

    public function materia()
    {
        return $this->belongsTo(Materia::class, 'materia_id');
    }

    
}