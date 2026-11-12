<?php

namespace Tests\Feature;

use App\Models\Ambiente;
use App\Models\Asignatura;
use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\Ingreso;
use App\Models\SituacionExcepcional;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * RQ21: registrar situaciones excepcionales (problemas de identificación, cambios de ambiente, estudiante no
 * habilitado, otras), listarlas por examen y marcarlas como REVISADAS.
 *
 * Usa DatabaseTransactions: lo que crea se deshace al terminar cada prueba y no toca tus datos.
 * Requiere la base migrada (php artisan migrate).
 */
class Rq21SituacionExcepcionalTest extends TestCase
{
    use DatabaseTransactions;

    private User $control;
    private Examen $examen;
    private Examen $otroExamen;
    private Estudiante $a;          // ya ingresó al Aula A
    private Estudiante $b;          // todavía no ingresó
    private Ambiente $aulaA;
    private Ambiente $aulaB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->control = User::create([
            'name' => 'Control R21', 'nombre' => 'Control', 'apellido' => 'R21',
            'email' => 'control.rq21@test.local', 'password' => bcrypt('x'), 'rol' => 'control', 'estado' => 'activo',
        ]);

        $this->aulaA = Ambiente::create(['nombre' => 'Aula R21 A', 'capacidad' => 50]);
        $this->aulaB = Ambiente::create(['nombre' => 'Aula R21 B', 'capacidad' => 50]);

        $asignatura = Asignatura::create(['codigo' => 'R21-A', 'nombre' => 'Física R21']);
        $nuevoExamen = fn (string $hora) => Examen::create([
            'asignatura_id' => $asignatura->id_asignatura, 'carrera' => 'Carrera R21',
            'fecha' => now()->addDay()->toDateString(), 'hora_inicio' => $hora, 'duracion_min' => 120,
            'ambiente' => 'Aula R21 A', 'capacidad' => 50, 'estado' => 'programado',
        ]);
        $this->examen = $nuevoExamen('08:00');
        $this->otroExamen = $nuevoExamen('14:00');

        foreach (['a' => '921000001', 'b' => '921000002'] as $var => $codigo) {
            $this->$var = Estudiante::create([
                'codigo_universitario' => $codigo, 'documento_identidad' => '93'.substr($codigo, -5), 'nombres' => 'Nombre'.$var,
                'apellidos' => 'Apellido'.$var, 'carrera' => 'Carrera R21', 'estado' => 'ACTIVO',
            ]);
        }

        Ingreso::create([
            'estudiante_id' => $this->a->id_estudiante, 'examen_id' => $this->examen->id,
            'ambiente_id' => $this->aulaA->id, 'controlador_id' => $this->control->id, 'metodo_verificacion' => 'CODIGO_UNIV',
        ]);
    }

    private function registrar(array $cuerpo = [], ?User $usuario = null)
    {
        return $this->actingAs($usuario ?? $this->control)->postJson('/control-ingreso/situaciones', $cuerpo + [
            'examen_id' => $this->examen->id,
            'categoria' => 'OTRA',
            'detalle' => 'El carnet del estudiante está dañado.',
        ]);
    }

    private function filas(): int
    {
        return SituacionExcepcional::where('examen_id', $this->examen->id)->count();
    }

    // ---------------------------------------------------------------- registrar

    public function test_registra_con_usuario_y_hora_del_servidor_y_ignora_lo_que_manda_el_navegador(): void
    {
        $r = $this->registrar([
            'categoria' => 'PROBLEMA_IDENTIFICACION',
            'detalle' => 'La foto del carnet no se parece al estudiante.',
            'estudiante_id' => $this->b->id_estudiante,
            'fecha_hora' => '2000-01-01 00:00:00',   // el navegador no puede fijar la hora
            'registrado_por' => 999999,              // ni el usuario
            'estado' => 'REVISADA',                  // ni el estado
            'ambiente_destino_id' => $this->aulaB->id, // ni un destino fuera de un cambio de ambiente
        ]);

        $r->assertCreated()
            ->assertJsonPath('situacion.categoria', 'PROBLEMA_IDENTIFICACION')
            ->assertJsonPath('situacion.categoria_etiqueta', 'Problema de identificación')
            ->assertJsonPath('situacion.codigo', '921000002')
            ->assertJsonPath('situacion.registrado_por', 'Control R21')
            ->assertJsonPath('situacion.estado', 'PENDIENTE')
            ->assertJsonPath('situacion.ambiente_destino', null);

        $fila = SituacionExcepcional::firstOrFail();
        $this->assertSame($this->control->id, (int) $fila->registrado_por);
        $this->assertSame('PENDIENTE', $fila->estado);
        $this->assertTrue($fila->fecha_hora->greaterThan(now()->subMinute()), 'la hora debe ser la del servidor');
        $this->assertNull($fila->ambiente_origen_id);
        $this->assertNull($fila->ambiente_destino_id);
        $this->assertNull($fila->revisada_por);
    }

    public function test_acepta_las_cuatro_categorias(): void
    {
        $this->registrar(['categoria' => 'PROBLEMA_IDENTIFICACION'])->assertCreated();
        $this->registrar(['categoria' => 'ESTUDIANTE_NO_HABILITADO', 'estudiante_id' => $this->b->id_estudiante])->assertCreated();
        $this->registrar(['categoria' => 'OTRA'])->assertCreated();
        $this->registrar([
            'categoria' => 'CAMBIO_AMBIENTE', 'estudiante_id' => $this->b->id_estudiante, 'ambiente_destino_id' => $this->aulaB->id,
        ])->assertCreated();

        $this->assertSame(4, $this->filas());
    }

    public function test_la_categoria_es_obligatoria_y_debe_ser_una_de_las_definidas(): void
    {
        $this->registrar(['categoria' => null])->assertStatus(422)->assertJsonValidationErrors('categoria');
        $this->registrar(['categoria' => 'INVENTADA'])->assertStatus(422)->assertJsonValidationErrors('categoria');

        $this->assertSame(0, $this->filas());
    }

    public function test_el_detalle_es_obligatorio(): void
    {
        $this->registrar(['detalle' => null])->assertStatus(422)->assertJsonValidationErrors('detalle');
        $this->registrar(['detalle' => '   '])->assertStatus(422)->assertJsonValidationErrors('detalle');
        $this->registrar(['detalle' => 'abc'])->assertStatus(422)->assertJsonValidationErrors('detalle');
        $this->registrar(['detalle' => str_repeat('x', 1001)])->assertStatus(422)->assertJsonValidationErrors('detalle');

        $this->assertSame(0, $this->filas());
    }

    public function test_el_cambio_de_ambiente_exige_ambiente_destino(): void
    {
        $base = ['categoria' => 'CAMBIO_AMBIENTE', 'estudiante_id' => $this->b->id_estudiante];

        $this->registrar($base)->assertStatus(422)->assertJsonValidationErrors('ambiente_destino_id');
        $this->registrar($base + ['ambiente_destino_id' => 99999999])->assertStatus(422)->assertJsonValidationErrors('ambiente_destino_id');

        $this->assertSame(0, $this->filas());
    }

    public function test_el_cambio_de_ambiente_guarda_origen_y_destino(): void
    {
        // B no ingresó: el origen es el aula asignada al examen (Aula A).
        $this->registrar([
            'categoria' => 'CAMBIO_AMBIENTE', 'estudiante_id' => $this->b->id_estudiante, 'ambiente_destino_id' => $this->aulaB->id,
            'detalle' => 'El aula A está llena.',
        ])->assertCreated()
            ->assertJsonPath('situacion.ambiente_origen', 'Aula R21 A')
            ->assertJsonPath('situacion.ambiente_destino', 'Aula R21 B');

        $fila = SituacionExcepcional::firstOrFail();
        $this->assertSame($this->aulaA->id, (int) $fila->ambiente_origen_id);
        $this->assertSame($this->aulaB->id, (int) $fila->ambiente_destino_id);
    }

    public function test_el_origen_es_el_aula_donde_ingreso_el_estudiante(): void
    {
        // A ya ingresó al Aula B (distinta de la asignada al examen): el origen debe ser esa.
        Ingreso::where('estudiante_id', $this->a->id_estudiante)->where('examen_id', $this->examen->id)->delete();
        Ingreso::create([
            'estudiante_id' => $this->a->id_estudiante, 'examen_id' => $this->examen->id,
            'ambiente_id' => $this->aulaB->id, 'controlador_id' => $this->control->id, 'metodo_verificacion' => 'QR',
        ]);

        $this->registrar([
            'categoria' => 'CAMBIO_AMBIENTE', 'estudiante_id' => $this->a->id_estudiante, 'ambiente_destino_id' => $this->aulaA->id,
        ])->assertCreated()
            ->assertJsonPath('situacion.ambiente_origen', 'Aula R21 B')
            ->assertJsonPath('situacion.ambiente_destino', 'Aula R21 A');
    }

    public function test_el_ambiente_destino_no_puede_ser_el_mismo_donde_esta_el_estudiante(): void
    {
        $this->registrar([
            'categoria' => 'CAMBIO_AMBIENTE', 'estudiante_id' => $this->b->id_estudiante, 'ambiente_destino_id' => $this->aulaA->id,
        ])->assertStatus(422)->assertJsonValidationErrors('ambiente_destino_id');

        $this->assertSame(0, $this->filas());
    }

    public function test_cambio_de_ambiente_y_no_habilitado_exigen_estudiante_pero_las_otras_no(): void
    {
        $this->registrar(['categoria' => 'CAMBIO_AMBIENTE', 'ambiente_destino_id' => $this->aulaB->id])
            ->assertStatus(422)->assertJsonValidationErrors('estudiante_ref');
        $this->registrar(['categoria' => 'ESTUDIANTE_NO_HABILITADO'])
            ->assertStatus(422)->assertJsonValidationErrors('estudiante_ref');
        $this->assertSame(0, $this->filas());

        $this->registrar(['categoria' => 'PROBLEMA_IDENTIFICACION'])->assertCreated()->assertJsonPath('situacion.nombre', null);
        $this->registrar(['categoria' => 'OTRA'])->assertCreated();
    }

    public function test_el_estudiante_se_puede_indicar_por_codigo_o_por_cedula(): void
    {
        $this->registrar(['estudiante_ref' => '921000002'])->assertCreated()->assertJsonPath('situacion.estudiante_id', $this->b->id_estudiante);
        $this->registrar(['estudiante_ref' => $this->a->documento_identidad])->assertCreated()->assertJsonPath('situacion.estudiante_id', $this->a->id_estudiante);

        $this->registrar(['estudiante_ref' => '000000000'])->assertStatus(422)->assertJsonValidationErrors('estudiante_ref');
        $this->assertSame(2, $this->filas());
    }

    public function test_un_docente_sin_acceso_al_examen_no_puede_registrar_ni_ver_ni_revisar(): void
    {
        $situacion = $this->registrar()->assertCreated()->json('situacion');

        $docente = User::create([
            'name' => 'Docente R21', 'nombre' => 'Docente', 'apellido' => 'R21',
            'email' => 'docente.rq21@test.local', 'password' => bcrypt('x'), 'rol' => 'docente', 'estado' => 'activo',
        ]);

        $this->registrar([], $docente)->assertForbidden();
        $this->actingAs($docente)->getJson("/control-ingreso/examen/{$this->examen->id}/situaciones")->assertForbidden();
        $this->actingAs($docente)->patchJson("/control-ingreso/situaciones/{$situacion['id']}/revisar")->assertForbidden();

        $this->assertSame(1, $this->filas());
        $this->assertSame('PENDIENTE', SituacionExcepcional::find($situacion['id'])->estado);
    }

    public function test_sin_iniciar_sesion_no_se_puede_usar(): void
    {
        $this->postJson('/control-ingreso/situaciones', [
            'examen_id' => $this->examen->id, 'categoria' => 'OTRA', 'detalle' => 'Detalle de prueba.',
        ])->assertUnauthorized();
        $this->getJson("/control-ingreso/examen/{$this->examen->id}/situaciones")->assertUnauthorized();
        $this->patchJson('/control-ingreso/situaciones/1/revisar')->assertUnauthorized();
    }

    // ---------------------------------------------------------------- listar

    public function test_lista_las_situaciones_del_examen_de_la_mas_reciente_a_la_mas_antigua(): void
    {
        $this->registrar(['detalle' => 'Primera situación.', 'estudiante_id' => $this->a->id_estudiante])->assertCreated();
        $this->registrar(['detalle' => 'Segunda situación.', 'categoria' => 'PROBLEMA_IDENTIFICACION'])->assertCreated();
        // De otro examen: no debe aparecer en este.
        $this->registrar(['examen_id' => $this->otroExamen->id, 'detalle' => 'De otro examen.'])->assertCreated();

        $r = $this->actingAs($this->control)->getJson("/control-ingreso/examen/{$this->examen->id}/situaciones")
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('pendientes', 2)
            ->assertJsonPath('situaciones.0.detalle', 'Segunda situación.')
            ->assertJsonPath('situaciones.1.detalle', 'Primera situación.')
            ->assertJsonPath('situaciones.1.nombre', 'Nombrea Apellidoa')
            ->assertJsonPath('situaciones.1.registrado_por', 'Control R21')
            ->assertJsonStructure(['hora_servidor', 'situaciones' => [[
                'id', 'categoria', 'categoria_etiqueta', 'codigo', 'ci', 'nombre', 'detalle',
                'ambiente_origen', 'ambiente_destino', 'registrado_por', 'fecha_hora_local', 'estado', 'revisada_por', 'revisada_at_local',
            ]]]);

        $this->assertMatchesRegularExpression('#^\d{2}/\d{2}/\d{4} \d{2}:\d{2}:\d{2}$#', $r->json('situaciones.0.fecha_hora_local'));
    }

    public function test_la_lista_se_puede_filtrar_por_estado_y_por_categoria(): void
    {
        $id = $this->registrar(['detalle' => 'Esta se revisa.'])->assertCreated()->json('situacion.id');
        $this->registrar(['detalle' => 'Esta queda pendiente.', 'categoria' => 'PROBLEMA_IDENTIFICACION'])->assertCreated();
        $this->actingAs($this->control)->patchJson("/control-ingreso/situaciones/{$id}/revisar")->assertOk();

        $url = "/control-ingreso/examen/{$this->examen->id}/situaciones";
        $this->actingAs($this->control)->getJson($url.'?estado=REVISADA')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('situaciones.0.detalle', 'Esta se revisa.');
        $this->actingAs($this->control)->getJson($url.'?estado=PENDIENTE')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('pendientes', 1);
        $this->actingAs($this->control)->getJson($url.'?categoria=PROBLEMA_IDENTIFICACION')->assertOk()->assertJsonPath('total', 1);
        $this->actingAs($this->control)->getJson($url.'?estado=OTRO')->assertStatus(422);
    }

    // ---------------------------------------------------------------- revisar

    public function test_marcar_como_revisada_guarda_quien_y_cuando_y_no_toca_lo_demas(): void
    {
        $id = $this->registrar(['detalle' => 'Detalle original.'])->assertCreated()->json('situacion.id');

        $admin = User::create([
            'name' => 'Admin R21', 'nombre' => 'Admin', 'apellido' => 'R21',
            'email' => 'admin.rq21@test.local', 'password' => bcrypt('x'), 'rol' => 'administrador', 'estado' => 'activo',
        ]);

        $this->actingAs($admin)->patchJson("/control-ingreso/situaciones/{$id}/revisar", [
            'estado' => 'PENDIENTE', 'detalle' => 'Cambiado a escondidas', 'revisada_por' => 999999,   // se ignora todo
        ])->assertOk()
            ->assertJsonPath('ya_estaba_revisada', false)
            ->assertJsonPath('situacion.estado', 'REVISADA')
            ->assertJsonPath('situacion.revisada_por', 'Admin R21');

        $fila = SituacionExcepcional::findOrFail($id);
        $this->assertSame('REVISADA', $fila->estado);
        $this->assertSame($admin->id, (int) $fila->revisada_por);
        $this->assertTrue($fila->revisada_at->greaterThan(now()->subMinute()));
        $this->assertSame('Detalle original.', $fila->detalle);
        $this->assertSame($this->control->id, (int) $fila->registrado_por);
    }

    public function test_revisar_dos_veces_es_inofensivo_y_no_pisa_a_quien_la_reviso_primero(): void
    {
        $id = $this->registrar()->assertCreated()->json('situacion.id');
        $this->actingAs($this->control)->patchJson("/control-ingreso/situaciones/{$id}/revisar")->assertOk();

        $otro = User::create([
            'name' => 'Otro R21', 'nombre' => 'Otro', 'apellido' => 'R21',
            'email' => 'otro.rq21@test.local', 'password' => bcrypt('x'), 'rol' => 'administrador', 'estado' => 'activo',
        ]);

        $this->actingAs($otro)->patchJson("/control-ingreso/situaciones/{$id}/revisar")
            ->assertOk()
            ->assertJsonPath('ya_estaba_revisada', true)
            ->assertJsonPath('situacion.revisada_por', 'Control R21');

        $this->assertSame($this->control->id, (int) SituacionExcepcional::findOrFail($id)->revisada_por);
    }

    public function test_revisar_una_situacion_que_no_existe_da_404(): void
    {
        $this->actingAs($this->control)->patchJson('/control-ingreso/situaciones/99999999/revisar')->assertNotFound();
    }
}
