<?php

namespace Tests\Feature;

use App\Models\Asignatura;
use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\Habilitacion;
use App\Models\Ingreso;
use App\Models\IntentoIngreso;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * RQ14 (ingresaron), RQ15 (pendientes), RQ12 (intentos por examen) y consulta de foto (RQ29).
 * DatabaseTransactions: no deja datos en la base. Requiere la base migrada (php artisan migrate).
 */
class Sprint4ControlEnVivoTest extends TestCase
{
    use DatabaseTransactions;

    private User $control;
    private Examen $examen;
    private array $est = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->control = User::create([
            'name' => 'Control S4', 'nombre' => 'Control', 'apellido' => 'S4',
            'email' => 'control.s4@test.local', 'password' => bcrypt('x'), 'rol' => 'control', 'estado' => 'activo',
        ]);

        $asignatura = Asignatura::create(['codigo' => 'S4-A', 'nombre' => 'Cálculo S4']);
        $this->examen = Examen::create([
            'asignatura_id' => $asignatura->id_asignatura, 'carrera' => 'Carrera S4',
            'fecha' => now()->addDay()->toDateString(), 'hora_inicio' => '08:00', 'duracion_min' => 120,
            'ambiente' => 'Aula S4', 'capacidad' => 50, 'estado' => 'programado',
        ]);

        foreach (['910000001', '910000002', '910000003'] as $i => $codigo) {
            $e = Estudiante::create([
                'codigo_universitario' => $codigo, 'documento_identidad' => '91000'.$i, 'nombres' => 'Nombre'.$i,
                'apellidos' => 'Apellido'.$i, 'carrera' => 'Carrera S4', 'estado' => 'ACTIVO',
            ]);
            \DB::table('estudiante_asignatura')->insert([
                'id_estudiante' => $e->id_estudiante, 'id_asignatura' => $asignatura->id_asignatura, 'gestion' => '2026-II', 'estado' => 'ACTIVO',
            ]);
            $this->est[] = $e;
        }

        Habilitacion::habilitar($this->examen, array_map(fn ($e) => $e->id_estudiante, $this->est));

        Ingreso::create([
            'estudiante_id' => $this->est[0]->id_estudiante, 'examen_id' => $this->examen->id,
            'ambiente_id' => null, 'controlador_id' => $this->control->id, 'metodo_verificacion' => 'QR',
        ]);
    }

    public function test_ingresados_devuelve_solo_a_quien_ya_ingreso_y_los_totales(): void
    {
        $this->actingAs($this->control)->getJson("/control-ingreso/examen/{$this->examen->id}/ingresados")
            ->assertOk()
            ->assertJsonPath('resumen.habilitados', 3)
            ->assertJsonPath('resumen.ingresaron', 1)
            ->assertJsonPath('resumen.pendientes', 2)
            ->assertJsonCount(1, 'nuevos')
            ->assertJsonPath('nuevos.0.codigo', '910000001');
    }

    public function test_con_desde_futuro_no_trae_ingresos_ya_conocidos(): void
    {
        $desde = now()->addDay()->format('Y-m-d H:i:s');

        $this->actingAs($this->control)->getJson("/control-ingreso/examen/{$this->examen->id}/ingresados?desde=".urlencode($desde))
            ->assertOk()->assertJsonCount(0, 'nuevos');
    }

    public function test_pendientes_son_los_habilitados_sin_ingreso(): void
    {
        $r = $this->actingAs($this->control)->getJson("/control-ingreso/examen/{$this->examen->id}/pendientes")
            ->assertOk()
            ->assertJsonPath('resumen.pendientes', 2)
            ->assertJsonCount(2, 'pendientes');

        $codigos = collect($r->json('pendientes'))->pluck('codigo')->all();
        $this->assertNotContains('910000001', $codigos);
        $this->assertContains('910000002', $codigos);
    }

    public function test_un_docente_sin_grupos_no_puede_ver_el_examen(): void
    {
        $docente = User::create([
            'name' => 'Docente S4', 'nombre' => 'Docente', 'apellido' => 'SinGrupos',
            'email' => 'docente.s4@test.local', 'password' => bcrypt('x'), 'rol' => 'docente', 'estado' => 'activo',
        ]);

        $this->actingAs($docente)->getJson("/control-ingreso/examen/{$this->examen->id}/pendientes")->assertForbidden();
    }

    public function test_intentos_por_examen_lista_y_cuenta_por_motivo(): void
    {
        IntentoIngreso::create([
            'estudiante_id' => $this->est[1]->id_estudiante, 'examen_id' => $this->examen->id, 'controlador_id' => $this->control->id,
            'tipo' => 'CODIGO_UNIV', 'dato_verificado' => '910000002', 'motivo' => 'EXAMEN_NO_CORRESPONDE', 'fecha_hora' => now(),
        ]);

        $this->actingAs($this->control)->getJson("/intentos-ingreso/examen/{$this->examen->id}?motivo=EXAMEN_NO_CORRESPONDE")
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('por_motivo.EXAMEN_NO_CORRESPONDE', 1)
            ->assertJsonPath('intentos.0.codigo_universitario', '910000002');
    }

    public function test_consulta_de_foto_sin_parametros_o_sin_foto(): void
    {
        $this->actingAs($this->control)->getJson('/fotos/consultar')->assertStatus(422);
        $this->actingAs($this->control)->getJson('/fotos/consultar?codigo_universitario=910000001')->assertNotFound();
    }
}
