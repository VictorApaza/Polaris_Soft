<?php

namespace Tests\Feature;

use App\Models\Asignatura;
use App\Models\Examen;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/** La gestión de exámenes es del docente (solo sus materias); el administrador ya no la tiene. */
class ExamenesDelDocenteTest extends TestCase
{
    use DatabaseTransactions;

    private function usuario(string $rol): User
    {
        return User::create([
            'name' => ucfirst($rol).' Ex', 'nombre' => ucfirst($rol), 'apellido' => 'Ex',
            'email' => "{$rol}.ex@test.local", 'password' => bcrypt('x'), 'rol' => $rol, 'estado' => 'activo',
        ]);
    }

    private function examenAjeno(): Examen
    {
        $asig = Asignatura::create(['codigo' => 'EXD-1', 'nombre' => 'Materia ajena']);

        return Examen::create([
            'asignatura_id' => $asig->id_asignatura, 'carrera' => 'Carrera EXD',
            'fecha' => now()->addDay()->toDateString(), 'hora_inicio' => '08:00', 'duracion_min' => 90,
            'ambiente' => 'Aula EXD', 'capacidad' => 30, 'estado' => 'programado',
        ]);
    }

    public function test_el_administrador_ya_no_gestiona_examenes_y_el_docente_si_entra(): void
    {
        $this->actingAs($this->usuario('administrador'))->get('/examenes')->assertForbidden();
        $this->actingAs($this->usuario('docente'))->get('/examenes')->assertOk()->assertSee('Módulo del docente');
    }

    public function test_el_docente_no_edita_ni_borra_examenes_de_materias_ajenas(): void
    {
        $examen = $this->examenAjeno();
        $docente = $this->usuario('docente');

        $this->actingAs($docente)->put("/examenes/{$examen->id}", ['ambiente' => 'X'])->assertForbidden();
        $this->actingAs($docente)->delete("/examenes/{$examen->id}")->assertForbidden();
        $this->assertNotNull(Examen::find($examen->id));
    }

    public function test_el_docente_no_programa_una_asignatura_que_no_dicta(): void
    {
        $examen = $this->examenAjeno();
        $r = $this->actingAs($this->usuario('docente'))->post('/examenes', [
            'asignatura_id' => $examen->asignatura_id, 'carrera' => 'Carrera EXD',
            'fecha' => now()->addDays(2)->toDateString(), 'hora_inicio' => '10:00', 'duracion_min' => 60,
            'ambiente' => 'Aula Z', 'capacidad' => 10,
        ]);
        $r->assertSessionHasErrors();
        $this->assertSame(1, Examen::where('asignatura_id', $examen->asignatura_id)->count());
    }

    public function test_los_errores_de_importacion_se_muestran_sin_romper_la_pantalla(): void
    {
        $errores = new ViewErrorBag();
        $errores->put('default', new MessageBag(['importacion' => ['Fila 2: el código ya está registrado.']]));

        $this->actingAs($this->usuario('docente'))->withSession(['errors' => $errores])
            ->get('/estudiantes-importar')->assertOk()->assertSee('Fila 2: el código ya está registrado.');
    }
}
