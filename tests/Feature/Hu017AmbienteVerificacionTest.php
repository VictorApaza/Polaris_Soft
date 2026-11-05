<?php

namespace Tests\Feature;

use App\Models\Ambiente;
use App\Models\Asignatura;
use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\Habilitacion;
use App\Models\Ingreso;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class Hu017AmbienteVerificacionTest extends TestCase
{
    use DatabaseTransactions;

    private User $control;
    private Ambiente $ambienteAsignado;
    private Ambiente $ambienteControl;
    private Examen $examen;
    private Estudiante $estudiante;

    protected function setUp(): void
    {
        parent::setUp();

        $this->control = User::create([
            'name' => 'Control HU017', 'nombre' => 'Control', 'apellido' => 'HU017',
            'email' => 'control.hu017@test.local', 'password' => bcrypt('x'), 'rol' => 'control', 'estado' => 'activo',
        ]);
        $this->ambienteAsignado = Ambiente::create([
            'nombre' => 'Aula HU017-Asignada', 'ubicacion' => 'Planta baja, Bloque Central', 'capacidad' => 40,
        ]);
        $this->ambienteControl = Ambiente::create([
            'nombre' => 'Aula HU017-Control', 'ubicacion' => 'Segundo piso, Bloque Norte', 'capacidad' => 40,
        ]);

        $asignatura = Asignatura::create(['codigo' => 'HU017', 'nombre' => 'Prueba HU017']);
        $this->examen = Examen::create([
            'asignatura_id' => $asignatura->id_asignatura,
            'carrera' => 'Carrera HU017',
            'fecha' => now()->addDay()->toDateString(),
            'hora_inicio' => '08:00',
            'duracion_min' => 120,
            'ambiente' => $this->ambienteAsignado->nombre,
            'capacidad' => 40,
            'estado' => 'programado',
        ]);
        $this->estudiante = Estudiante::create([
            'codigo_universitario' => '917000001',
            'documento_identidad' => '9170001',
            'nombres' => 'Estudiante',
            'apellidos' => 'HU017',
            'carrera' => 'Carrera HU017',
            'estado' => 'ACTIVO',
        ]);
        $this->estudiante->forceFill([
            'foto_path' => 'fotos_estudiantes/hu017.jpg',
            'foto_estado' => 'APROBADA',
        ])->save();
        Habilitacion::habilitar($this->examen, [$this->estudiante->id_estudiante]);
    }

    public function test_ambiente_correcto_muestra_coincidencia_y_registra_en_el_ambiente_controlado(): void
    {
        $this->actingAs($this->control)->postJson('/verificacion/verificar', [
            'id_examen' => $this->examen->id,
            'metodo_verificacion' => 'CODIGO_UNIV',
            'codigo_universitario' => $this->estudiante->codigo_universitario,
            'ambiente_control_id' => $this->ambienteAsignado->id,
        ])->assertOk()
            ->assertJsonPath('tipo', 'habilitado')
            ->assertJsonPath('ambiente_coincide', true)
            ->assertJsonPath('ambiente_asignado.nombre', $this->ambienteAsignado->nombre)
            ->assertJsonPath('ambiente_asignado.ubicacion', 'Planta baja, Bloque Central');

        $this->actingAs($this->control)->postJson('/verificacion/ingreso', [
            'id_estudiante' => $this->estudiante->id_estudiante,
            'id_examen' => $this->examen->id,
            'metodo' => 'CODIGO_UNIV',
            'ambiente_control_id' => $this->ambienteAsignado->id,
        ])->assertCreated();

        $this->assertDatabaseHas('ingreso', [
            'estudiante_id' => $this->estudiante->id_estudiante,
            'examen_id' => $this->examen->id,
            'ambiente_id' => $this->ambienteAsignado->id,
        ]);
    }

    public function test_ambiente_incorrecto_no_permite_registrar_el_ingreso(): void
    {
        $this->actingAs($this->control)->postJson('/verificacion/verificar', [
            'id_examen' => $this->examen->id,
            'metodo_verificacion' => 'CODIGO_UNIV',
            'codigo_universitario' => $this->estudiante->codigo_universitario,
            'ambiente_control_id' => $this->ambienteControl->id,
        ])->assertOk()
            ->assertJsonPath('tipo', 'habilitado')
            ->assertJsonPath('ambiente_coincide', false)
            ->assertJsonPath('ambiente_asignado.ubicacion', 'Planta baja, Bloque Central');

        $this->actingAs($this->control)->postJson('/verificacion/ingreso', [
            'id_estudiante' => $this->estudiante->id_estudiante,
            'id_examen' => $this->examen->id,
            'metodo' => 'CODIGO_UNIV',
            'ambiente_control_id' => $this->ambienteControl->id,
        ])->assertStatus(422)
            ->assertJsonPath('tipo', 'ambiente_incorrecto')
            ->assertJsonPath('ambiente_asignado.nombre', $this->ambienteAsignado->nombre);

        $this->assertSame(0, Ingreso::where('estudiante_id', $this->estudiante->id_estudiante)->count());
    }

    public function test_pantalla_expone_el_selector_y_ubicacion_del_catalogo(): void
    {
        $this->actingAs($this->control)->get('/verificacion')
            ->assertOk()
            ->assertSee('Ambiente donde controlo')
            ->assertSee('Planta baja, Bloque Central');
    }
}
