<?php

namespace Tests\Feature;

use App\Models\Asignatura;
use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\Expulsion;
use App\Models\Habilitacion;
use App\Models\Ingreso;
use App\Models\IntentoIngreso;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * RQ20: expulsar a un estudiante que ya ingresó (motivo obligatorio, hora del servidor, usuario que registra),
 * impedir su reingreso y listar los expulsados del examen.
 *
 * Usa DatabaseTransactions: lo que crea se deshace al terminar cada prueba y no toca tus datos.
 * Requiere la base migrada (php artisan migrate).
 */
class Rq20ExpulsionTest extends TestCase
{
    use DatabaseTransactions;

    private User $control;
    private Examen $examen;
    private Estudiante $a;      // ingresó
    private Estudiante $b;      // ingresó
    private Estudiante $c;      // habilitado, todavía no ingresó

    protected function setUp(): void
    {
        parent::setUp();

        $this->control = User::create([
            'name' => 'Control R20', 'nombre' => 'Control', 'apellido' => 'R20',
            'email' => 'control.rq20@test.local', 'password' => bcrypt('x'), 'rol' => 'control', 'estado' => 'activo',
        ]);

        $asignatura = Asignatura::create(['codigo' => 'R20-A', 'nombre' => 'Cálculo R20']);
        $this->examen = Examen::create([
            'asignatura_id' => $asignatura->id_asignatura, 'carrera' => 'Carrera R20',
            'fecha' => now()->addDay()->toDateString(), 'hora_inicio' => '08:00', 'duracion_min' => 120,
            'ambiente' => 'Aula R20', 'capacidad' => 50, 'estado' => 'programado',
        ]);

        foreach (['a' => '920000001', 'b' => '920000002', 'c' => '920000003'] as $var => $codigo) {
            $e = Estudiante::create([
                'codigo_universitario' => $codigo, 'documento_identidad' => '92'.substr($codigo, -5), 'nombres' => 'Nombre'.$var,
                'apellidos' => 'Apellido'.$var, 'carrera' => 'Carrera R20', 'estado' => 'ACTIVO',
            ]);
            $e->forceFill(['foto_path' => 'fotos_estudiantes/prueba.jpg', 'foto_estado' => 'APROBADA'])->save();
            \DB::table('estudiante_asignatura')->insert([
                'id_estudiante' => $e->id_estudiante, 'id_asignatura' => $asignatura->id_asignatura, 'gestion' => '2026-II', 'estado' => 'ACTIVO',
            ]);
            $this->$var = $e;
        }

        Habilitacion::habilitar($this->examen, [$this->a->id_estudiante, $this->b->id_estudiante, $this->c->id_estudiante]);

        foreach ([$this->a, $this->b] as $e) {
            Ingreso::create([
                'estudiante_id' => $e->id_estudiante, 'examen_id' => $this->examen->id,
                'ambiente_id' => null, 'controlador_id' => $this->control->id, 'metodo_verificacion' => 'CODIGO_UNIV',
            ]);
        }
    }

    private function expulsar(Estudiante $e, ?string $motivo = 'Usaba el celular durante el examen.', array $extra = [])
    {
        $cuerpo = ['examen_id' => $this->examen->id, 'estudiante_id' => $e->id_estudiante] + $extra;
        if ($motivo !== null) {
            $cuerpo['motivo'] = $motivo;
        }

        return $this->actingAs($this->control)->postJson('/control-ingreso/expulsiones', $cuerpo);
    }

    private function verificar(Estudiante $e)
    {
        return $this->actingAs($this->control)->postJson('/control-ingreso/verificar', [
            'id_examen' => $this->examen->id, 'metodo_verificacion' => 'CODIGO_UNIV', 'codigo_universitario' => $e->codigo_universitario,
        ]);
    }

    public function test_expulsa_con_motivo_y_la_hora_y_el_usuario_los_pone_el_servidor(): void
    {
        $r = $this->expulsar($this->a, 'Usaba el celular durante el examen.', [
            'fecha_hora' => '2000-01-01 00:00:00',   // el navegador no puede fijar la hora
            'registrado_por' => 999999,              // ni el usuario
        ]);

        $r->assertCreated()
            ->assertJsonPath('expulsion.estudiante_id', $this->a->id_estudiante)
            ->assertJsonPath('expulsion.motivo', 'Usaba el celular durante el examen.')
            ->assertJsonPath('expulsion.registrado_por', 'Control R20');

        $fila = Expulsion::where('estudiante_id', $this->a->id_estudiante)->where('examen_id', $this->examen->id)->firstOrFail();
        $this->assertSame($this->control->id, (int) $fila->registrado_por);
        $this->assertTrue($fila->fecha_hora->greaterThan(now()->subMinute()), 'la hora debe ser la del servidor');
        $this->assertSame(1, Expulsion::where('examen_id', $this->examen->id)->count());
    }

    public function test_el_motivo_es_obligatorio(): void
    {
        $this->expulsar($this->a, null)->assertStatus(422)->assertJsonValidationErrors('motivo');
        $this->expulsar($this->a, '   ')->assertStatus(422)->assertJsonValidationErrors('motivo');
        $this->expulsar($this->a, 'abc')->assertStatus(422)->assertJsonValidationErrors('motivo');
        $this->expulsar($this->a, str_repeat('x', 501))->assertStatus(422)->assertJsonValidationErrors('motivo');

        $this->assertSame(0, Expulsion::where('examen_id', $this->examen->id)->count());
    }

    public function test_solo_se_expulsa_a_quien_ya_ingreso(): void
    {
        $this->expulsar($this->c)->assertStatus(422)->assertJsonPath('tipo', 'sin_ingreso');

        $this->assertSame(0, Expulsion::where('estudiante_id', $this->c->id_estudiante)->count());
    }

    public function test_no_se_expulsa_dos_veces_al_mismo_estudiante(): void
    {
        $this->expulsar($this->a)->assertCreated();
        $this->expulsar($this->a, 'Otro motivo distinto.')->assertStatus(422)->assertJsonPath('tipo', 'ya_expulsado');

        $this->assertSame(1, Expulsion::where('estudiante_id', $this->a->id_estudiante)->where('examen_id', $this->examen->id)->count());
    }

    public function test_el_expulsado_no_puede_volver_a_entrar_y_queda_registrado_el_intento(): void
    {
        $this->expulsar($this->a, 'Copiaba del compañero.')->assertCreated();

        $this->verificar($this->a)->assertStatus(409)
            ->assertJsonPath('tipo', 'expulsado')
            ->assertJsonPath('motivo', 'Copiaba del compañero.')
            ->assertJsonPath('alerta', true);

        $this->assertDatabaseHas('verificacion', [
            'estudiante_id' => $this->a->id_estudiante, 'examen_id' => $this->examen->id, 'resultado' => 'EXPULSADO',
        ]);
        $this->assertDatabaseHas('intento_ingreso', [
            'estudiante_id' => $this->a->id_estudiante, 'examen_id' => $this->examen->id, 'motivo' => 'ESTUDIANTE_EXPULSADO', 'resultado' => 'RECHAZADO',
        ]);

        // El servidor también lo rechaza si alguien se salta la pantalla.
        $this->actingAs($this->control)->postJson('/control-ingreso/ingreso', [
            'id_estudiante' => $this->a->id_estudiante, 'id_examen' => $this->examen->id,
        ])->assertStatus(409)->assertJsonPath('tipo', 'expulsado');

        $this->assertSame(1, Ingreso::where('estudiante_id', $this->a->id_estudiante)->where('examen_id', $this->examen->id)->count());
    }

    public function test_quien_no_fue_expulsado_sigue_igual_que_antes(): void
    {
        $this->expulsar($this->a)->assertCreated();

        // RQ11: B ya ingresó y no fue expulsado -> "ya ingresó", no "expulsado".
        $this->verificar($this->b)->assertStatus(409)->assertJsonPath('tipo', 'ya_ingreso');
        $this->actingAs($this->control)->postJson('/control-ingreso/ingreso', [
            'id_estudiante' => $this->b->id_estudiante, 'id_examen' => $this->examen->id,
        ])->assertStatus(409)->assertJsonPath('tipo', 'ya_registrado');

        // C todavía no ingresó: se verifica como habilitado y puede entrar.
        $this->verificar($this->c)->assertOk()->assertJsonPath('tipo', 'habilitado');
        $this->assertSame(0, IntentoIngreso::where('motivo', 'ESTUDIANTE_EXPULSADO')->where('estudiante_id', $this->c->id_estudiante)->count());
    }

    public function test_la_lista_de_expulsados_y_la_de_ingresaron_lo_reflejan(): void
    {
        $this->expulsar($this->a, 'Usaba el celular durante el examen.')->assertCreated();

        $this->actingAs($this->control)->getJson("/control-ingreso/examen/{$this->examen->id}/expulsados")
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('expulsados.0.codigo', '920000001')
            ->assertJsonPath('expulsados.0.motivo', 'Usaba el celular durante el examen.')
            ->assertJsonPath('expulsados.0.registrado_por', 'Control R20')
            ->assertJsonStructure(['expulsados' => [['id', 'estudiante_id', 'codigo', 'ci', 'nombre', 'ambiente', 'motivo', 'fecha_hora_local']]]);

        // RQ14: Control en vivo ya marca como expulsado a quien figure en la tabla y cuenta el resumen.
        $r = $this->actingAs($this->control)->getJson("/control-ingreso/examen/{$this->examen->id}/ingresados")->assertOk();
        $r->assertJsonPath('resumen.expulsados', 1);
        $marcas = collect($r->json('nuevos'))->pluck('expulsado', 'codigo');
        $this->assertTrue($marcas['920000001']);
        $this->assertFalse($marcas['920000002']);
    }

    public function test_un_docente_sin_acceso_al_examen_no_puede_expulsar_ni_ver_la_lista(): void
    {
        $docente = User::create([
            'name' => 'Docente R20', 'nombre' => 'Docente', 'apellido' => 'R20',
            'email' => 'docente.rq20@test.local', 'password' => bcrypt('x'), 'rol' => 'docente', 'estado' => 'activo',
        ]);

        $this->actingAs($docente)->postJson('/control-ingreso/expulsiones', [
            'examen_id' => $this->examen->id, 'estudiante_id' => $this->a->id_estudiante, 'motivo' => 'Motivo de prueba.',
        ])->assertForbidden();
        $this->actingAs($docente)->getJson("/control-ingreso/examen/{$this->examen->id}/expulsados")->assertForbidden();

        $this->assertSame(0, Expulsion::where('examen_id', $this->examen->id)->count());
    }

    public function test_sin_iniciar_sesion_no_se_puede_expulsar(): void
    {
        $this->postJson('/control-ingreso/expulsiones', [
            'examen_id' => $this->examen->id, 'estudiante_id' => $this->a->id_estudiante, 'motivo' => 'Motivo de prueba.',
        ])->assertUnauthorized();
    }
}
