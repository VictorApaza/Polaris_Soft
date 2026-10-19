<?php

namespace Tests\Feature;

use App\Models\Ambiente;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\Grupo;
use App\Models\Habilitacion;
use App\Models\Materia;
use App\Models\MotivoInhabilitacion;
use App\Models\User;
use App\Services\QrEstudianteService;
use App\Support\Catalogo;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FlujoDemostracionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $control;

    private Estudiante $estudiante;

    private Materia $materia;

    private Grupo $grupo;

    private Examen $examen;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['rol' => 'administrador', 'estado' => 'activo']);
        $this->control = User::factory()->create(['rol' => 'control', 'estado' => 'activo']);
        $this->estudiante = Estudiante::create([
            'codigo_universitario' => '202600101', 'documento_identidad' => '7654321',
            'nombres' => 'Ana', 'apellidos' => 'Demostracion', 'estado' => 'ACTIVO',
            'carrera' => 'Ingeniería de Sistemas',
            'facultad' => Catalogo::facultadDeCarrera('Ingeniería de Sistemas'),
            'correo_institucional' => 'estudiante@polaris.test',
        ]);
        $docente = Docente::create(['nombre' => 'Laura', 'apellido' => 'Demo', 'ci' => '7654322']);
        $this->materia = Materia::create([
            'nombre' => 'Calculo', 'sigla' => 'MAT-DEMO', 'carrera' => 'Ingeniería de Sistemas',
        ]);
        $this->grupo = Grupo::create([
            'nombre' => 'Grupo A', 'materia_id' => $this->materia->id, 'docente_id' => $docente->id,
        ]);
        $this->actingAs($this->admin)->postJson('/api/estudiantes/'.$this->estudiante->getKey().'/asignaciones', [
            'materia_id' => $this->materia->id, 'grupo_id' => $this->grupo->id, 'docente_id' => $docente->id,
        ])->assertCreated();
        Ambiente::create(['nombre' => 'Aula Demo', 'capacidad' => 30]);
        $this->post('/examenes', [
            'asignatura_id' => DB::table('asignatura')->where('codigo', 'MAT-DEMO')->value('id_asignatura'),
            'carrera' => 'Ingeniería de Sistemas', 'fecha' => now()->addDays(2)->toDateString(),
            'hora_inicio' => '09:00', 'duracion_min' => 90, 'ambiente' => 'Aula Demo', 'estado' => 'abierto',
        ])->assertRedirect('/examenes')->assertSessionHasNoErrors();
        $this->examen = Examen::firstOrFail();
        $this->post('/examenes/'.$this->examen->id.'/habilitados', [
            'estudiante_ids' => [$this->estudiante->getKey()],
        ])->assertRedirect()->assertSessionHasNoErrors();
    }

    private function ingreso(): array
    {
        return ['estudiante_id' => $this->estudiante->getKey(), 'examen_id' => $this->examen->id, 'metodo' => 'CODIGO_UNIV'];
    }

    public function test_el_recorrido_asignacion_examen_habilitacion_ingreso_y_auditoria_funciona(): void
    {
        $this->actingAs($this->control)->get('/control/dashboard')->assertRedirect('/verificacion');
        $this->actingAs($this->control)->postJson('/verificar/codigo', [
            'id_examen' => $this->examen->id, 'codigo_universitario' => '202600101',
        ])->assertOk()->assertJsonPath('tipo', 'habilitado');
        $this->postJson('/verificacion/ingreso', $this->ingreso())->assertCreated();
        $this->getJson('/transparencia/datos')->assertOk()->assertJsonPath('total', 1)
            ->assertJsonPath('registros.0.estudiante_codigo', '202600101');
        $this->postJson('/verificacion/ingreso', $this->ingreso())->assertConflict();
        $this->assertDatabaseCount('transparencia_ingreso', 1);
        $this->assertDatabaseHas('transparencia_ingreso', [
            'estudiante_id' => $this->estudiante->getKey(), 'controlador_id' => $this->control->id,
        ]);
    }

    public function test_el_endpoint_alternativo_tambien_controla_los_ingresos_duplicados(): void
    {
        $this->actingAs($this->control)->postJson('/transparencia/autorizar', $this->ingreso())->assertCreated();
        $this->postJson('/transparencia/autorizar', $this->ingreso())->assertConflict();
        $this->assertDatabaseCount('transparencia_ingreso', 1);
    }

    public function test_se_revalida_el_estado_del_estudiante_al_autorizar_en_ambas_rutas(): void
    {
        $this->estudiante->update(['estado' => 'INACTIVO']);
        foreach (['/verificacion/ingreso', '/transparencia/autorizar'] as $url) {
            $this->actingAs($this->control)->postJson($url, $this->ingreso())->assertUnprocessable();
        }
        $this->assertDatabaseCount('transparencia_ingreso', 0);
    }

    public function test_inhabilitar_y_rehabilitar_actualizan_la_misma_fila(): void
    {
        $motivo = MotivoInhabilitacion::create(['descripcion' => 'Documentación incompleta']);
        $id = Habilitacion::firstOrFail()->getKey();
        $url = '/examenes/'.$this->examen->id.'/habilitados';
        $this->post($url.'/'.$this->estudiante->getKey().'/inhabilitar', [
            'motivo_inhabilitacion_id' => $motivo->id,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('habilitacion', ['id_habilitacion' => $id, 'estado' => 'inhabilitado']);
        $this->actingAs($this->control)->postJson('/verificar/codigo', [
            'id_examen' => $this->examen->id, 'codigo_universitario' => '202600101',
        ])->assertUnprocessable()->assertJsonPath('tipo', 'no_habilitado');
        $this->actingAs($this->admin)->post($url, ['estudiante_ids' => [$this->estudiante->getKey()]])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('habilitacion', ['id_habilitacion' => $id, 'estado' => 'habilitado']);
        $this->assertDatabaseCount('habilitacion', 1);
    }

    public function test_codigo_ci_qr_valido_y_qr_alterado_dejan_bitacora(): void
    {
        $token = app(QrEstudianteService::class)->generarToken($this->estudiante);
        $this->actingAs($this->control)->postJson('/verificar/ci', [
            'id_examen' => $this->examen->id, 'documento_identidad' => '7654321',
        ])->assertOk()->assertJsonPath('tipo', 'habilitado');
        $this->postJson('/verificar/qr', ['id_examen' => $this->examen->id, 'token_qr' => $token])
            ->assertOk()->assertJsonPath('tipo', 'habilitado');
        $this->postJson('/verificar/qr', ['id_examen' => $this->examen->id, 'token_qr' => 'SCIEM1.alterado'])
            ->assertNotFound()->assertJsonPath('tipo', 'codigo_no_registrado');
        $this->assertDatabaseCount('verificacion', 3);
        $this->assertDatabaseCount('intento_ingreso', 1);
    }

    public function test_el_docente_no_puede_operar_ningun_alias_de_ingreso(): void
    {
        $docente = User::factory()->create(['rol' => 'docente', 'estado' => 'activo']);
        $this->actingAs($docente)->get('/docente/examenes')->assertOk();
        foreach (['/verificacion/ingreso', '/control-ingreso/ingreso', '/transparencia/autorizar'] as $url) {
            $this->postJson($url, $this->ingreso())->assertForbidden();
        }
        $this->assertDatabaseCount('transparencia_ingreso', 0);
    }

    public function test_una_cuenta_desactivada_pierde_acceso_aunque_conserve_sesion(): void
    {
        $this->admin->update(['estado' => 'inactivo']);
        $this->getJson('/api/materias')->assertForbidden();
        $this->actingAs($this->control);
        $this->control->update(['estado' => 'inactivo']);
        $this->postJson('/verificacion/ingreso', $this->ingreso())->assertForbidden();
    }

    public function test_el_filtro_por_grupo_funciona_en_examenes_de_asignaturas_sincronizadas(): void
    {
        $otroGrupo = Grupo::create([
            'nombre' => 'Grupo B', 'materia_id' => $this->materia->id, 'docente_id' => $this->grupo->docente_id,
        ]);
        $otro = $this->estudiante->replicate();
        $otro->codigo_universitario = '202600102';
        $otro->documento_identidad = '7654323';
        $otro->save();
        $this->postJson('/api/estudiantes/'.$otro->getKey().'/asignaciones', [
            'materia_id' => $this->materia->id, 'grupo_id' => $otroGrupo->id, 'docente_id' => $otroGrupo->docente_id,
        ])->assertCreated();
        $this->get('/examenes/'.$this->examen->id.'/habilitados?grupo_id='.$this->grupo->id)
            ->assertOk()->assertViewHas('usaGrupos', true)
            ->assertViewHas('estudiantes', fn ($estudiantes) => $estudiantes->modelKeys() === [$this->estudiante->getKey()]);
    }

    public function test_las_pantallas_del_administrador_cargan_con_los_datos_integrados(): void
    {
        foreach (['/admin/dashboard', '/estudiantes', '/materias', '/grupos', '/docentes', '/ambientes',
            '/examenes', '/usuarios', '/verificacion', '/transparencia', '/estudiantes/'.$this->estudiante->getKey().'/qr'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_recuperacion_de_contrasena_y_rechazo_de_token_reutilizado(): void
    {
        Notification::fake();
        $this->post('/logout')->assertRedirect('/login');
        $this->post('/forgot-password', ['email' => $this->admin->email])->assertRedirect();
        $token = null;
        Notification::assertSentTo($this->admin, ResetPassword::class, function ($notification) use (&$token) {
            $token = $notification->token;

            return true;
        });
        $datos = ['email' => $this->admin->email, 'token' => $token,
            'password' => 'ClaveDemoSegura2026!', 'password_confirmation' => 'ClaveDemoSegura2026!'];
        $this->post('/reset-password', $datos)->assertRedirect('/login')->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check($datos['password'], $this->admin->fresh()->password));
        $this->post('/reset-password', $datos)->assertSessionHasErrors('email');
    }

    public function test_la_base_impide_modificar_la_auditoria(): void
    {
        $this->actingAs($this->control)->postJson('/verificacion/ingreso', $this->ingreso())->assertCreated();
        $this->expectException(QueryException::class);
        DB::table('transparencia_ingreso')->update(['estudiante_nombre' => 'Cambio no autorizado']);
    }
}
