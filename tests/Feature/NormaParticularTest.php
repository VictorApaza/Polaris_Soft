<?php

namespace Tests\Feature;

use App\Models\Asignatura;
use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\Habilitacion;
use App\Models\NormaParticular;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Requerimiento general 2: normas particulares de un estudiante en un examen.
 * Usa DatabaseTransactions: lo que crea se deshace al terminar cada prueba. Requiere la base migrada.
 */
class NormaParticularTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private User $control;
    private Examen $examen;
    private Estudiante $est;
    private Estudiante $ajeno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->usuario('administrador', 'adm');
        $this->control = $this->usuario('control', 'ctl');

        $asig = Asignatura::create(['codigo' => 'NP-A', 'nombre' => 'Materia Normas']);
        $this->examen = Examen::create([
            'asignatura_id' => $asig->id_asignatura, 'carrera' => 'Carrera NP',
            'fecha' => now()->addDay()->toDateString(), 'hora_inicio' => '08:00', 'duracion_min' => 120,
            'ambiente' => 'Aula NP', 'capacidad' => 40, 'estado' => 'programado',
            'normas_admision' => 'Ingresar con carnet.', 'normas_salida' => 'No salir durante la primera hora.',
        ]);

        $this->est = Estudiante::create([
            'codigo_universitario' => '960000001', 'documento_identidad' => '9600001', 'nombres' => 'Ana',
            'apellidos' => 'Norma', 'carrera' => 'Carrera NP', 'estado' => 'ACTIVO',
        ]);
        $this->est->forceFill(['foto_path' => 'fotos_estudiantes/np.jpg', 'foto_estado' => 'APROBADA'])->save();
        DB::table('estudiante_asignatura')->insert([
            'id_estudiante' => $this->est->id_estudiante, 'id_asignatura' => $asig->id_asignatura, 'gestion' => '2026-II', 'estado' => 'ACTIVO',
        ]);
        Habilitacion::habilitar($this->examen, [$this->est->id_estudiante]);

        $this->ajeno = Estudiante::create([
            'codigo_universitario' => '960000002', 'documento_identidad' => '9600002', 'nombres' => 'Beto',
            'apellidos' => 'Ajeno', 'carrera' => 'Carrera NP', 'estado' => 'ACTIVO',
        ]);
    }

    private function usuario(string $rol, string $clave): User
    {
        return User::create([
            'name' => 'Usuario '.$clave, 'nombre' => 'Usuario', 'apellido' => $clave,
            'email' => "{$clave}.normas@test.local", 'password' => bcrypt('x'), 'rol' => $rol, 'estado' => 'activo',
        ]);
    }

    private function guardar(?string $texto, ?Estudiante $est = null, ?User $quien = null)
    {
        return $this->actingAs($quien ?? $this->admin)
            ->put(route('normas.guardar', [$this->examen, $est ?? $this->est]), ['norma' => $texto]);
    }

    public function test_se_guarda_se_actualiza_y_se_quita_la_norma(): void
    {
        $this->guardar('Puede usar calculadora científica.')->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseHas('norma_particular', [
            'examen_id' => $this->examen->id, 'estudiante_id' => $this->est->id_estudiante,
            'norma' => 'Puede usar calculadora científica.', 'registrado_por' => $this->admin->id,
        ]);

        $this->guardar('Tiene 30 minutos adicionales.');
        $this->assertSame(1, NormaParticular::where('examen_id', $this->examen->id)->count());
        $this->assertSame('Tiene 30 minutos adicionales.', NormaParticular::textoDe($this->est->id_estudiante, $this->examen->id));

        $this->guardar('   ')->assertSessionHas('status');
        $this->assertNull(NormaParticular::textoDe($this->est->id_estudiante, $this->examen->id));
    }

    public function test_la_norma_se_ve_en_la_pantalla_de_habilitados_y_sobrevive_a_inhabilitar(): void
    {
        $this->guardar('Puede usar calculadora científica.');

        $this->actingAs($this->admin)->get(route('habilitaciones.index', $this->examen))
            ->assertOk()->assertSee('Norma particular:')->assertSee('Puede usar calculadora científica.');

        // Habilitar de nuevo no borra la norma.
        Habilitacion::habilitar($this->examen, [$this->est->id_estudiante]);
        $this->assertSame('Puede usar calculadora científica.', NormaParticular::textoDe($this->est->id_estudiante, $this->examen->id));
    }

    public function test_el_control_ve_las_normas_al_verificar_el_ingreso(): void
    {
        $this->guardar('Tiene 30 minutos adicionales.');

        $this->actingAs($this->control)->postJson('/verificacion/verificar', [
            'id_examen' => $this->examen->id, 'metodo_verificacion' => 'CODIGO_UNIV',
            'codigo_universitario' => $this->est->codigo_universitario,
        ])->assertOk()
            ->assertJsonPath('tipo', 'habilitado')
            ->assertJsonPath('normas.particular', 'Tiene 30 minutos adicionales.')
            ->assertJsonPath('normas.admision', 'Ingresar con carnet.')
            ->assertJsonPath('normas.salida', 'No salir durante la primera hora.');
    }

    public function test_sin_norma_particular_el_campo_viene_vacio(): void
    {
        $this->actingAs($this->control)->postJson('/verificacion/verificar', [
            'id_examen' => $this->examen->id, 'metodo_verificacion' => 'CODIGO_UNIV',
            'codigo_universitario' => $this->est->codigo_universitario,
        ])->assertOk()->assertJsonPath('normas.particular', null);
    }

    public function test_validaciones_y_permisos(): void
    {
        // El personal de control no administra normas.
        $this->guardar('x', null, $this->control)->assertForbidden();
        // Largo máximo.
        $this->guardar(str_repeat('a', 1001))->assertSessionHasErrors('norma');
        // Un estudiante que no es de la materia del examen no puede recibir norma.
        $this->guardar('Algo', $this->ajeno)->assertSessionHasErrors('norma');
        $this->assertDatabaseMissing('norma_particular', ['estudiante_id' => $this->ajeno->id_estudiante]);
    }
}
