<?php

namespace Tests\Feature;

use App\Models\Asignatura;
use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\Habilitacion;
use App\Models\Ingreso;
use App\Models\IntentoIngreso;
use App\Models\MotivoInhabilitacion;
use App\Models\User;
use App\Models\Verificacion;
use App\Services\QrEstudianteService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * RQ12 (Sprint 3): intento de ingreso a un examen que no le corresponde al estudiante.
 *
 * Usa DatabaseTransactions: todo lo que crea se deshace al terminar cada prueba, así que
 * NO borra ni deja datos en la base de desarrollo (a diferencia de RefreshDatabase).
 * Requiere la base ya migrada (php artisan migrate).
 */
class Rq12ExamenNoCorrespondeTest extends TestCase
{
    use DatabaseTransactions;

    private User $control;
    private Examen $examenA;
    private Examen $examenB;
    private Estudiante $habilitado;
    private Estudiante $deOtraMateria;
    private Estudiante $candidatoSinHabilitar;
    private Estudiante $inhabilitado;

    protected function setUp(): void
    {
        parent::setUp();

        $this->control = User::create([
            'name' => 'Control RQ12', 'nombre' => 'Control', 'apellido' => 'RQ12',
            'email' => 'control.rq12@test.local', 'password' => bcrypt('x'), 'rol' => 'control', 'estado' => 'activo',
        ]);

        $asigA = Asignatura::create(['codigo' => 'T12-A', 'nombre' => 'Cálculo T12']);
        $asigB = Asignatura::create(['codigo' => 'T12-B', 'nombre' => 'Física T12']);

        $this->examenA = $this->crearExamen($asigA, 'Aula T12-A', '08:00');
        $this->examenB = $this->crearExamen($asigB, 'Aula T12-B', '10:30');

        $this->habilitado = $this->crearEstudiante('900000001', '9000001', [$asigA]);
        $this->deOtraMateria = $this->crearEstudiante('900000002', '9000002', [$asigB]);
        $this->candidatoSinHabilitar = $this->crearEstudiante('900000003', '9000003', [$asigA]);
        $this->inhabilitado = $this->crearEstudiante('900000004', '9000004', [$asigA]);

        Habilitacion::habilitar($this->examenA, [$this->habilitado->id_estudiante, $this->inhabilitado->id_estudiante]);
        Habilitacion::habilitar($this->examenB, [$this->deOtraMateria->id_estudiante]);

        Habilitacion::where('estudiante_id', $this->inhabilitado->id_estudiante)->where('examen_id', $this->examenA->id)
            ->update([
                'estado' => 'inhabilitado',
                'motivo_inhabilitacion_id' => MotivoInhabilitacion::firstOrCreate(['descripcion' => 'Deuda económica pendiente'])->id,
            ]);
    }

    private function crearExamen(Asignatura $asignatura, string $aula, string $hora): Examen
    {
        return Examen::create([
            'asignatura_id' => $asignatura->id_asignatura, 'carrera' => 'Carrera T12',
            'fecha' => now()->addDay()->toDateString(), 'hora_inicio' => $hora, 'duracion_min' => 120,
            'ambiente' => $aula, 'capacidad' => 50, 'estado' => 'programado',
        ]);
    }

    private function crearEstudiante(string $codigo, string $ci, array $asignaturas): Estudiante
    {
        $e = Estudiante::create([
            'codigo_universitario' => $codigo, 'documento_identidad' => $ci, 'nombres' => 'Nombre'.$codigo,
            'apellidos' => 'Apellido'.$codigo, 'carrera' => 'Carrera T12', 'estado' => 'ACTIVO',
        ]);
        // La foto es obligatoria para poder verificar el ingreso.
        $e->forceFill(['foto_path' => 'fotos_estudiantes/prueba.jpg', 'foto_estado' => 'APROBADA'])->save();
        foreach ($asignaturas as $a) {
            \DB::table('estudiante_asignatura')->insert([
                'id_estudiante' => $e->id_estudiante, 'id_asignatura' => $a->id_asignatura, 'gestion' => '2026-II', 'estado' => 'ACTIVO',
            ]);
        }

        return $e;
    }

    private function verificar(Estudiante $est, string $metodo = 'CODIGO_UNIV', ?Examen $examen = null)
    {
        $cuerpo = ['id_examen' => ($examen ?? $this->examenA)->id, 'metodo_verificacion' => $metodo];
        if ($metodo === 'CODIGO_UNIV') {
            $cuerpo['codigo_universitario'] = $est->codigo_universitario;
        } elseif ($metodo === 'CI') {
            $cuerpo['documento_identidad'] = $est->documento_identidad;
        } else {
            $cuerpo['token_qr'] = app(QrEstudianteService::class)->generarToken($est);
        }

        return $this->actingAs($this->control)->postJson('/verificacion/verificar', $cuerpo);
    }

    public function test_examen_que_no_le_corresponde_queda_registrado_y_genera_alerta(): void
    {
        $r = $this->verificar($this->deOtraMateria);

        $r->assertStatus(422)
            ->assertJsonPath('tipo', 'no_corresponde')
            ->assertJsonPath('alerta', true)
            ->assertJsonPath('numero_intento', 1)
            ->assertJsonPath('examenes_asignados.0.id', $this->examenB->id)
            ->assertJsonPath('examenes_asignados.0.ambiente', 'Aula T12-B')
            ->assertJsonPath('estudiante.codigo', '900000002');

        $this->assertDatabaseHas('intento_ingreso', [
            'estudiante_id' => $this->deOtraMateria->id_estudiante, 'examen_id' => $this->examenA->id,
            'controlador_id' => $this->control->id, 'resultado' => 'RECHAZADO', 'motivo' => 'EXAMEN_NO_CORRESPONDE',
            'tipo' => 'CODIGO_UNIVERSITARIO',
        ]);
        $this->assertDatabaseHas('verificacion', [
            'estudiante_id' => $this->deOtraMateria->id_estudiante, 'examen_id' => $this->examenA->id, 'resultado' => 'EXAMEN_NO_CORRESPONDE',
        ]);
        $this->assertSame(1, IntentoIngreso::where('estudiante_id', $this->deOtraMateria->id_estudiante)->count(), 'un solo intento por verificación');
    }

    public function test_cada_nuevo_intento_se_registra_y_se_cuenta(): void
    {
        $this->verificar($this->deOtraMateria)->assertJsonPath('numero_intento', 1);
        $this->verificar($this->deOtraMateria, 'CI')->assertJsonPath('numero_intento', 2);
        $this->verificar($this->deOtraMateria, 'QR')->assertStatus(422)->assertJsonPath('numero_intento', 3);

        $this->assertSame(3, IntentoIngreso::where('motivo', 'EXAMEN_NO_CORRESPONDE')
            ->where('estudiante_id', $this->deOtraMateria->id_estudiante)->count());
        $this->assertEqualsCanonicalizing(
            ['CODIGO_UNIVERSITARIO', 'DOCUMENTO_IDENTIDAD', 'CODIGO_QR'],
            IntentoIngreso::where('motivo', 'EXAMEN_NO_CORRESPONDE')
                ->where('estudiante_id', $this->deOtraMateria->id_estudiante)
                ->where('examen_id', $this->examenA->id)
                ->pluck('tipo')->all()
        );
    }

    public function test_el_servidor_rechaza_el_ingreso_aunque_se_salte_la_pantalla(): void
    {
        $this->actingAs($this->control)->postJson('/verificacion/ingreso', [
            'id_estudiante' => $this->deOtraMateria->id_estudiante, 'id_examen' => $this->examenA->id, 'metodo' => 'CI',
        ])->assertStatus(422)->assertJsonPath('tipo', 'no_corresponde')->assertJsonPath('examenes_asignados.0.id', $this->examenB->id);

        $this->assertSame(0, Ingreso::where('estudiante_id', $this->deOtraMateria->id_estudiante)->count());
        $this->assertDatabaseHas('intento_ingreso', [
            'estudiante_id' => $this->deOtraMateria->id_estudiante, 'examen_id' => $this->examenA->id,
            'motivo' => 'EXAMEN_NO_CORRESPONDE', 'tipo' => 'DOCUMENTO_IDENTIDAD',
        ]);
    }

    public function test_sin_examenes_pendientes_la_alerta_lo_indica(): void
    {
        Habilitacion::where('estudiante_id', $this->deOtraMateria->id_estudiante)->delete();

        $this->verificar($this->deOtraMateria)->assertStatus(422)
            ->assertJsonPath('tipo', 'no_corresponde')->assertJsonPath('examenes_asignados', []);
    }

    public function test_los_casos_de_rq9_y_rq11_no_cambian(): void
    {
        // Candidato de la materia sin habilitación: sigue siendo RQ9 (no_habilitado), NO "no corresponde".
        $this->verificar($this->candidatoSinHabilitar)->assertStatus(422)->assertJsonPath('tipo', 'no_habilitado')
            ->assertJsonPath('motivo', 'No tiene habilitación registrada para este examen.');
        $this->assertSame(0, IntentoIngreso::where('motivo', 'EXAMEN_NO_CORRESPONDE')
            ->where('estudiante_id', $this->candidatoSinHabilitar->id_estudiante)->count());

        // Inhabilitado con motivo: RQ4/RQ9.
        $this->verificar($this->inhabilitado)->assertStatus(422)->assertJsonPath('tipo', 'no_habilitado')
            ->assertJsonPath('motivo_inhabilitacion', 'Deuda económica pendiente');

        // Habilitado: pasa, no deja intento rechazado, y su ingreso se registra una sola vez (RQ11).
        $this->verificar($this->habilitado)->assertOk()->assertJsonPath('tipo', 'habilitado');
        $this->assertSame(0, IntentoIngreso::where('estudiante_id', $this->habilitado->id_estudiante)->count());

        $cuerpo = ['id_estudiante' => $this->habilitado->id_estudiante, 'id_examen' => $this->examenA->id];
        $this->actingAs($this->control)->postJson('/verificacion/ingreso', $cuerpo)->assertCreated();
        $this->actingAs($this->control)->postJson('/verificacion/ingreso', $cuerpo)->assertStatus(409);
        $this->verificar($this->habilitado)->assertStatus(409)->assertJsonPath('tipo', 'ya_ingreso');
        $this->assertSame(1, Ingreso::where('estudiante_id', $this->habilitado->id_estudiante)->count());
    }

    public function test_examen_sin_asignatura_ni_materia_conserva_el_comportamiento_de_rq9(): void
    {
        $huerfano = Examen::create([
            'carrera' => 'Carrera T12', 'fecha' => now()->addDays(2)->toDateString(), 'hora_inicio' => '14:00',
            'duracion_min' => 90, 'ambiente' => 'Aula T12-C', 'capacidad' => 10, 'estado' => 'programado',
        ]);

        $this->verificar($this->deOtraMateria, 'CODIGO_UNIV', $huerfano)->assertStatus(422)->assertJsonPath('tipo', 'no_habilitado');
    }

    public function test_sin_foto_valida_no_puede_ingresar(): void
    {
        $this->habilitado->forceFill(['foto_path' => null, 'foto_estado' => 'SIN_FOTO'])->save();

        $this->verificar($this->habilitado)->assertStatus(422)->assertJsonPath('tipo', 'sin_foto');

        $cuerpo = ['id_estudiante' => $this->habilitado->id_estudiante, 'id_examen' => $this->examenA->id];
        $this->actingAs($this->control)->postJson('/verificacion/ingreso', $cuerpo)->assertStatus(422)->assertJsonPath('tipo', 'sin_foto');
    }
}
