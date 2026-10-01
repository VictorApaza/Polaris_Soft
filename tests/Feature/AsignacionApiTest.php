<?php

namespace Tests\Feature;

use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AsignacionApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Estudiante $estudiante;
    private Materia $materia;
    private Grupo $grupo;
    private Docente $docente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['rol' => 'administrador', 'estado' => 'activo']);
        $this->estudiante = Estudiante::forceCreate([
            'id_estudiante' => 42,
            'codigo_universitario' => '2026-00042',
            'documento_identidad' => 'PRUEBA-42',
            'nombres' => 'Ana', 'apellidos' => 'Prueba',
        ]);
        $this->materia = Materia::create(['nombre' => 'Materia A']);
        $this->docente = Docente::create(['nombre' => 'Docente A', 'ci' => 'PRUEBA-A']);
        $this->grupo = Grupo::create([
            'nombre' => 'Grupo A',
            'materia_id' => $this->materia->id,
            'docente_id' => $this->docente->id,
        ]);
    }

    private function datos(): array
    {
        return [
            'materia_id' => $this->materia->id,
            'grupo_id' => $this->grupo->id,
            'docente_id' => $this->docente->id,
        ];
    }

    public function test_el_formulario_usa_el_diseno_del_sistema_y_la_clave_del_estudiante(): void
    {
        $this->actingAs($this->admin)->get('/estudiantes/42/asignaciones/create')
            ->assertOk()
            ->assertSee('css/sciem.css', false)
            ->assertSee('css/asignacion.css', false)
            ->assertSee('js/asignacion-api.js', false)
            ->assertSee('data-estudiante-id="42"', false)
            ->assertSee('Prueba, Ana');
    }

    public function test_los_catalogos_filtran_grupos_y_docentes_por_su_relacion(): void
    {
        $otraMateria = Materia::create(['nombre' => 'Materia B']);
        Grupo::create(['nombre' => 'Grupo B', 'materia_id' => $otraMateria->id]);

        $this->actingAs($this->admin)->getJson('/api/materias')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/materias/'.$this->materia->id.'/grupos')
            ->assertOk()->assertExactJson(['data' => [['id' => $this->grupo->id, 'nombre' => 'Grupo A']]]);
        $this->getJson('/api/grupos/'.$this->grupo->id.'/docentes')
            ->assertOk()->assertExactJson(['data' => [['id' => $this->docente->id, 'nombre' => 'Docente A']]]);
    }

    public function test_los_catalogos_vacios_devuelven_arreglos_vacios(): void
    {
        $materia = Materia::create(['nombre' => 'Sin grupos']);
        $grupo = Grupo::create(['nombre' => 'Sin docente', 'materia_id' => $this->materia->id]);
        $this->actingAs($this->admin)->getJson('/api/materias/'.$materia->id.'/grupos')
            ->assertOk()->assertExactJson(['data' => []]);
        $this->getJson('/api/grupos/'.$grupo->id.'/docentes')->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_guarda_la_asignacion_con_id_estudiante(): void
    {
        $this->actingAs($this->admin)->postJson('/api/estudiantes/42/asignaciones', $this->datos())
            ->assertCreated()->assertJsonPath('data.estudiante_id', 42);
        $this->assertDatabaseHas('asignaciones', ['estudiante_id' => 42] + $this->datos());
    }

    public function test_la_ruta_actual_muestra_la_asignacion_guardada_y_su_accion_quitar(): void
    {
        $asignacionId = $this->actingAs($this->admin)
            ->postJson('/api/estudiantes/42/asignaciones', $this->datos())
            ->assertCreated()->json('data.id');

        $this->get('/estudiantes/42/asignaciones')->assertOk()
            ->assertSee('Materia A')->assertSee('Grupo A')->assertSee('Docente A')
            ->assertSee('data-asignacion-id="'.$asignacionId.'"', false)
            ->assertSee('Quitar Materia A')->assertSee('1 materias asignadas');
    }

    public function test_conserva_el_guardado_web_de_main(): void
    {
        $this->actingAs($this->admin)->from('/estudiantes/42/asignaciones')
            ->post('/estudiantes/42/asignaciones', $this->datos())
            ->assertRedirect('/estudiantes/42/asignaciones')
            ->assertSessionHas('success', 'Asignación registrada correctamente.');

        $this->assertDatabaseHas('asignaciones', ['estudiante_id' => 42] + $this->datos());
    }

    public function test_quitar_elimina_solo_la_asignacion_elegida_y_regresa_a_la_ruta_actual(): void
    {
        $asignacionId = $this->actingAs($this->admin)
            ->postJson('/api/estudiantes/42/asignaciones', $this->datos())
            ->assertCreated()->json('data.id');
        $otraMateria = Materia::create(['nombre' => 'Materia B']);
        $otroGrupo = Grupo::create([
            'nombre' => 'Grupo B', 'materia_id' => $otraMateria->id, 'docente_id' => $this->docente->id,
        ]);
        $otraAsignacionId = $this->postJson('/api/estudiantes/42/asignaciones',
            array_replace($this->datos(), ['materia_id' => $otraMateria->id, 'grupo_id' => $otroGrupo->id]))
            ->assertCreated()->json('data.id');

        $this->delete('/asignaciones/'.$asignacionId)
            ->assertRedirect('/estudiantes/42/asignaciones')
            ->assertSessionHas('success', 'Asignación eliminada.');
        $this->assertDatabaseMissing('asignaciones', ['id' => $asignacionId]);
        $this->assertDatabaseHas('asignaciones', ['id' => $otraAsignacionId, 'estudiante_id' => 42]);
        $this->assertDatabaseCount('asignaciones', 1);
    }

    public function test_rechaza_un_grupo_de_otra_materia(): void
    {
        $otraMateria = Materia::create(['nombre' => 'Materia B']);
        $this->actingAs($this->admin)->postJson('/api/estudiantes/42/asignaciones',
            array_replace($this->datos(), ['materia_id' => $otraMateria->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('grupo_id');
        $this->assertDatabaseCount('asignaciones', 0);
    }

    public function test_rechaza_un_docente_que_no_corresponde_al_grupo(): void
    {
        $otroDocente = Docente::create(['nombre' => 'Docente B', 'ci' => 'PRUEBA-B']);
        $this->actingAs($this->admin)->postJson('/api/estudiantes/42/asignaciones',
            array_replace($this->datos(), ['docente_id' => $otroDocente->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('docente_id');
        $this->assertDatabaseCount('asignaciones', 0);
    }

    public function test_no_duplica_una_materia_asignada(): void
    {
        $this->actingAs($this->admin)->postJson('/api/estudiantes/42/asignaciones', $this->datos())->assertCreated();
        $this->postJson('/api/estudiantes/42/asignaciones', $this->datos())
            ->assertUnprocessable()->assertJsonValidationErrors('materia_id');
        $this->assertDatabaseCount('asignaciones', 1);
    }

    public function test_valida_identificadores_inexistentes(): void
    {
        $this->actingAs($this->admin)->postJson('/api/estudiantes/42/asignaciones', [
            'materia_id' => 999, 'grupo_id' => 999, 'docente_id' => 999,
        ])->assertUnprocessable()->assertJsonValidationErrors(['materia_id', 'grupo_id', 'docente_id']);
        $this->assertDatabaseCount('asignaciones', 0);
    }

    public function test_exige_sesion_para_cargar_y_guardar(): void
    {
        $this->getJson('/api/materias')->assertUnauthorized();
        $this->postJson('/api/estudiantes/42/asignaciones', $this->datos())->assertUnauthorized();
        $this->get('/estudiantes/42/asignaciones/create')->assertRedirect('/login');
    }

    public function test_docentes_y_control_no_pueden_asignar(): void
    {
        foreach (['docente', 'control'] as $rol) {
            $usuario = User::factory()->create(['rol' => $rol, 'estado' => 'activo']);
            $this->actingAs($usuario)->getJson('/api/materias')->assertForbidden();
            $this->get('/estudiantes/42/asignaciones/create')->assertForbidden();
            $this->postJson('/api/estudiantes/42/asignaciones', $this->datos())->assertForbidden();
        }
        $this->assertDatabaseCount('asignaciones', 0);
    }

    public function test_un_estudiante_inexistente_responde_404(): void
    {
        $this->actingAs($this->admin)->get('/estudiantes/999/asignaciones/create')->assertNotFound();
        $this->postJson('/api/estudiantes/999/asignaciones', $this->datos())->assertNotFound();
        $this->assertDatabaseCount('asignaciones', 0);
    }
}
