<?php

namespace Tests\Feature;

use App\Models\Asignatura;
use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Un examen que ya terminó no se puede verificar ni aparece en "Verificar ingreso". */
class Sprint4ExamenPasadoTest extends TestCase
{
    use DatabaseTransactions;

    private function examen(string $nombre, string $fecha): Examen
    {
        $asignatura = Asignatura::create(['codigo' => 'XQ'.random_int(100, 999), 'nombre' => $nombre]);

        return Examen::create([
            'asignatura_id' => $asignatura->id_asignatura, 'carrera' => 'Carrera XQ',
            'fecha' => $fecha, 'hora_inicio' => '08:00', 'duracion_min' => 120,
            'ambiente' => 'Aula XQ', 'capacidad' => 50, 'estado' => 'programado',
        ]);
    }

    public function test_un_examen_pasado_no_se_lista_ni_se_puede_verificar(): void
    {
        $control = User::create([
            'name' => 'Control XQ', 'nombre' => 'Control', 'apellido' => 'XQ',
            'email' => 'control.xq@test.local', 'password' => bcrypt('x'), 'rol' => 'control', 'estado' => 'activo',
        ]);
        $pasado = $this->examen('Fisica Pasada XQ', now()->subDays(3)->toDateString());
        $futuro = $this->examen('Quimica Futura XQ', now()->addDay()->toDateString());

        $this->actingAs($control)->get('/verificacion')
            ->assertOk()->assertDontSee('Fisica Pasada XQ')->assertSee('Quimica Futura XQ');

        $this->actingAs($control)->postJson('/verificacion/verificar', [
            'id_examen' => $pasado->id, 'metodo_verificacion' => 'CODIGO_UNIV', 'codigo_universitario' => '930000001',
        ])->assertStatus(422)->assertJsonPath('tipo', 'examen_pasado');

        $estudiante = Estudiante::create([
            'codigo_universitario' => '930000001', 'documento_identidad' => '9300001', 'nombres' => 'Nombre',
            'apellidos' => 'Apellido', 'carrera' => 'Carrera XQ', 'estado' => 'ACTIVO',
        ]);

        $this->actingAs($control)->postJson('/verificacion/ingreso', [
            'id_examen' => $pasado->id, 'id_estudiante' => $estudiante->id_estudiante,
        ])->assertStatus(422)->assertJsonPath('tipo', 'examen_pasado');
    }
}
