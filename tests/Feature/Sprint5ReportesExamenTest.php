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
use App\Services\ExamenNoCorrespondeService;
use App\Services\ReporteExamenService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Sprint 5 · RQ16 (asistencia), RQ17 (ausentes), RQ18 (horarios y cantidad de registrados) y RQ19 (intentos a un examen que no corresponde).
 *
 * Usa DatabaseTransactions: lo que crea se deshace al terminar cada prueba y no toca tus datos.
 * Requiere la base migrada (php artisan migrate).
 */
class Sprint5ReportesExamenTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private User $control;
    private Examen $examen;
    private Estudiante $a;   // ingresó
    private Estudiante $b;   // ingresó y fue expulsado
    private Estudiante $c;   // habilitado que no ingresó
    private Estudiante $d;   // inhabilitado
    private Estudiante $x;   // de otra materia: intentó entrar a este examen

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->usuario('administrador', 'adm');
        $this->control = $this->usuario('control', 'ctl');

        $asig = Asignatura::create(['codigo' => 'S5-A', 'nombre' => 'Cálculo S5']);
        $otra = Asignatura::create(['codigo' => 'S5-B', 'nombre' => 'Física S5']);

        $this->examen = Examen::create([
            'asignatura_id' => $asig->id_asignatura, 'carrera' => 'Carrera S5',
            'fecha' => now()->toDateString(), 'hora_inicio' => '08:00', 'duracion_min' => 120,
            'ambiente' => 'Aula S5', 'capacidad' => 50, 'estado' => 'abierto',
        ]);
        $examenOtro = Examen::create([
            'asignatura_id' => $otra->id_asignatura, 'carrera' => 'Carrera S5',
            'fecha' => now()->addDays(2)->toDateString(), 'hora_inicio' => '10:00', 'duracion_min' => 120,
            'ambiente' => 'Aula S5-B', 'capacidad' => 50, 'estado' => 'programado',
        ]);

        $this->a = $this->estudiante('950000001', $asig);
        $this->b = $this->estudiante('950000002', $asig);
        $this->c = $this->estudiante('950000003', $asig);
        $this->d = $this->estudiante('950000004', $asig);
        $this->x = $this->estudiante('950000005', $otra);

        Habilitacion::habilitar($this->examen, [$this->a->id_estudiante, $this->b->id_estudiante, $this->c->id_estudiante, $this->d->id_estudiante]);
        Habilitacion::habilitar($examenOtro, [$this->x->id_estudiante]);
        Habilitacion::where('estudiante_id', $this->d->id_estudiante)->where('examen_id', $this->examen->id)->update(['estado' => 'inhabilitado']);

        foreach ([$this->a, $this->b] as $e) {
            Ingreso::create([
                'estudiante_id' => $e->id_estudiante, 'examen_id' => $this->examen->id,
                'ambiente_id' => null, 'controlador_id' => $this->control->id, 'metodo_verificacion' => 'QR',
            ]);
        }
        Expulsion::create(['examen_id' => $this->examen->id, 'estudiante_id' => $this->b->id_estudiante, 'motivo' => 'Copiaba del compañero.', 'registrado_por' => $this->control->id]);

        // Dos intentos de x (RQ12) y uno por otro motivo (no debe aparecer en RQ19).
        foreach ([ExamenNoCorrespondeService::MOTIVO, ExamenNoCorrespondeService::MOTIVO, 'ESTUDIANTE_NO_HABILITADO'] as $motivo) {
            IntentoIngreso::create([
                'estudiante_id' => $this->x->id_estudiante, 'examen_id' => $this->examen->id, 'controlador_id' => $this->control->id,
                'tipo' => 'CODIGO_UNIV', 'dato_verificado' => $this->x->codigo_universitario, 'resultado' => 'RECHAZADO', 'motivo' => $motivo, 'fecha_hora' => now(),
            ]);
        }
    }

    private function usuario(string $rol, string $clave): User
    {
        return User::create([
            'name' => 'Usuario '.$clave, 'nombre' => 'Usuario', 'apellido' => $clave,
            'email' => "{$clave}.sprint5@test.local", 'password' => bcrypt('x'), 'rol' => $rol, 'estado' => 'activo',
        ]);
    }

    private function estudiante(string $codigo, Asignatura $asig): Estudiante
    {
        $e = Estudiante::create([
            'codigo_universitario' => $codigo, 'documento_identidad' => '95'.substr($codigo, -5), 'nombres' => 'Nombre'.substr($codigo, -1),
            'apellidos' => 'Apellido'.substr($codigo, -1), 'carrera' => 'Carrera S5', 'estado' => 'ACTIVO',
        ]);
        \DB::table('estudiante_asignatura')->insert([
            'id_estudiante' => $e->id_estudiante, 'id_asignatura' => $asig->id_asignatura, 'gestion' => '2026-II', 'estado' => 'ACTIVO',
        ]);

        return $e;
    }

    // ---------------- RQ16 ----------------

    public function test_rq16_la_asistencia_coincide_con_los_ingresos_registrados(): void
    {
        $datos = app(ReporteExamenService::class)->asistencia($this->examen);

        $this->assertSame(Ingreso::where('examen_id', $this->examen->id)->count(), count($datos['filas']));
        $this->assertSame(3, $datos['resumen']['habilitados']);   // a, b, c (d está inhabilitado)
        $this->assertSame(2, $datos['resumen']['ingresaron']);
        $this->assertSame(1, $datos['resumen']['ausentes']);
        $this->assertSame(1, $datos['resumen']['expulsados']);
        $this->assertSame(66.7, $datos['resumen']['porcentaje']);
        $this->assertTrue($datos['resumen']['cuadra']);
        $this->assertSame(['Presente', 'Expulsado'], array_column($datos['filas'], 'estado'));

        $this->actingAs($this->admin)->get(route('reportes.asistencia', $this->examen))
            ->assertOk()->assertSee('Apellido1')->assertSee('Expulsado')->assertSee('Descargar PDF');
    }

    public function test_rq16_se_exporta_en_pdf_y_en_excel(): void
    {
        $pdf = $this->actingAs($this->admin)->get(route('reportes.asistencia', [$this->examen, 'formato' => 'pdf']));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->assertStringContainsString('attachment; filename="reporte-asistencia-', (string) $pdf->headers->get('Content-Disposition'));

        $xlsx = $this->actingAs($this->admin)->get(route('reportes.asistencia', [$this->examen, 'formato' => 'xlsx']));
        $xlsx->assertOk();
        $this->assertStringStartsWith('PK', $xlsx->getContent());   // un .xlsx es un zip

        $this->actingAs($this->admin)->get(route('reportes.asistencia', [$this->examen, 'formato' => 'doc']))->assertStatus(422);
    }

    // ---------------- RQ17 ----------------

    public function test_rq17_los_ausentes_son_los_habilitados_que_no_ingresaron(): void
    {
        $datos = app(ReporteExamenService::class)->ausentes($this->examen);

        $this->assertSame(['950000003'], array_column($datos['filas'], 'codigo'));   // solo c: d está inhabilitado
        $this->assertSame(3, $datos['resumen']['habilitados']);
        $this->assertSame(1, $datos['resumen']['ausentes']);
        $this->assertSame(2, $datos['resumen']['presentes']);
        $this->assertSame(1, $datos['resumen']['inhabilitados']);

        $this->actingAs($this->admin)->get(route('reportes.ausentes', $this->examen))
            ->assertOk()->assertSee('Apellido3')->assertDontSee('Apellido1');
        $this->actingAs($this->admin)->get(route('reportes.ausentes', [$this->examen, 'formato' => 'pdf']))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    // ---------------- RQ18 ----------------

    public function test_rq18_las_franjas_suman_los_estudiantes_registrados(): void
    {
        $datos = app(ReporteExamenService::class)->horarios($this->examen, 15);

        $this->assertSame(2, $datos['resumen']['registrados']);
        $this->assertSame(2, array_sum(array_column($datos['franjas'], 'cantidad')));
        $this->assertSame(2, end($datos['franjas'])['acumulado']);
        $this->assertSame(100.0, end($datos['franjas'])['porcentaje_acumulado']);
        $this->assertSame(2, $datos['por_ambiente'][0]['cantidad']);
        $this->assertTrue(collect($datos['comparativo'])->contains(fn ($c) => $c['actual'] && $c['registrados'] === 2));

        // Cualquier franja válida da el mismo total.
        foreach (ReporteExamenService::FRANJAS_VALIDAS as $m) {
            $this->assertSame(2, array_sum(array_column(app(ReporteExamenService::class)->horarios($this->examen, $m)['franjas'], 'cantidad')));
        }

        $this->actingAs($this->admin)->get(route('reportes.horarios', [$this->examen, 'franja' => 30]))->assertOk();
        $this->actingAs($this->admin)->get(route('reportes.horarios', [$this->examen, 'franja' => 7]))->assertSessionHasErrors('franja');
        $this->actingAs($this->admin)->get(route('reportes.horarios', [$this->examen, 'formato' => 'xlsx']))->assertOk();
    }

    // ---------------- RQ19 ----------------

    public function test_rq19_los_intentos_coinciden_con_los_registros_de_rq12(): void
    {
        $datos = app(ReporteExamenService::class)->intentosIndebidos($this->examen);

        $this->assertSame(2, $datos['resumen']['intentos']);          // el de otro motivo no cuenta
        $this->assertSame(1, $datos['resumen']['estudiantes']);
        $this->assertSame(1, $datos['resumen']['reincidentes']);
        $this->assertSame([1, 2], array_column($datos['filas'], 'intento'));
        $this->assertStringContainsString('Física S5', $datos['filas'][0]['le_corresponde']);

        // Mismo total que la consulta de RQ12.
        $json = $this->actingAs($this->admin)->getJson(route('intentos.examen', ['examen' => $this->examen, 'motivo' => ExamenNoCorrespondeService::MOTIVO]))->json();
        $this->assertSame($json['total'], $datos['resumen']['intentos']);

        $this->actingAs($this->admin)->get(route('reportes.intentos', $this->examen))
            ->assertOk()->assertSee('Apellido5')->assertSee('Física S5');
        $this->actingAs($this->admin)->get(route('reportes.intentos', [$this->examen, 'formato' => 'pdf']))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    // ---------------- Permisos ----------------

    public function test_el_personal_de_control_no_entra_a_los_reportes(): void
    {
        foreach (['reportes.asistencia', 'reportes.ausentes', 'reportes.horarios', 'reportes.intentos'] as $ruta) {
            $this->actingAs($this->control)->get(route($ruta, $this->examen))->assertForbidden();
        }
    }
}
