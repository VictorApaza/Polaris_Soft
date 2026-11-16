<?php

namespace Tests\Feature;

use App\Mail\CredencialesUsuario;
use App\Models\Ambiente;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\User;
use App\Support\Catalogo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class GestionBasicaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['rol' => 'administrador', 'estado' => 'activo']));
    }

    private function carrera(): array
    {
        return ['facultad' => Catalogo::facultadDeCarrera('Ingeniería de Sistemas'), 'carrera' => 'Ingeniería de Sistemas'];
    }

    private function grupo(): Grupo
    {
        $docente = Docente::create(['nombre' => 'Docente', 'apellido' => 'Demo', 'ci' => '9900800']);
        $materia = Materia::create($this->carrera() + ['nombre' => 'Calculo Demo', 'sigla' => 'DEMO-CSV']);

        return Grupo::create(['nombre' => 'Grupo Demo', 'materia_id' => $materia->id, 'docente_id' => $docente->id]);
    }

    public function test_crear_editar_y_eliminar_estudiante_conserva_validaciones_y_formato(): void
    {
        $datos = $this->carrera() + ['codigo_universitario' => '202600701', 'documento_identidad' => '9900701',
            'nombres' => 'aNa', 'apellidos' => 'dEmO', 'estado' => 'ACTIVO'];
        $this->post('/estudiantes', $datos)->assertRedirect('/estudiantes')->assertSessionHasNoErrors();
        $estudiante = Estudiante::firstOrFail();
        $this->assertSame('Ana', $estudiante->nombres);
        $this->post('/estudiantes', $datos)->assertSessionHasErrors(['codigo_universitario', 'documento_identidad']);
        $this->put('/estudiantes/'.$estudiante->getKey(), array_replace($datos, ['nombres' => 'lUiSa']))
            ->assertRedirect('/estudiantes')->assertSessionHasNoErrors();
        $this->assertSame('Luisa', $estudiante->fresh()->nombres);
        $this->delete('/estudiantes/'.$estudiante->getKey())->assertRedirect('/estudiantes');
        $this->assertDatabaseCount('estudiante', 0);
    }

    public function test_crear_editar_y_eliminar_catalogos_desde_sus_rutas(): void
    {
        $docenteDatos = ['nombre' => 'lAuRa', 'apellido' => 'dEmO', 'grado' => 'Ingeniero(a)', 'ci' => '9900702'];
        $this->post('/docentes', $docenteDatos)->assertRedirect()->assertSessionHasNoErrors();
        $docente = Docente::firstOrFail();
        $this->put('/docentes/'.$docente->id, array_replace($docenteDatos, ['grado' => 'Doctor(a)']))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Doctor(a)', $docente->fresh()->grado);

        $materiaDatos = $this->carrera() + ['nombre' => 'Calculo Demo', 'sigla' => 'DEM-2026'];
        $this->post('/materias', $materiaDatos)->assertRedirect()->assertSessionHasNoErrors();
        $materia = Materia::firstOrFail();
        $this->put('/materias/'.$materia->id, array_replace($materiaDatos, ['nombre' => 'Calculo Actualizado']))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Calculo Actualizado', $materia->fresh()->nombre);

        $grupoDatos = ['nombre' => 'Grupo Demo', 'materia_id' => $materia->id, 'docente_id' => $docente->id];
        $this->post('/grupos', $grupoDatos)->assertRedirect()->assertSessionHasNoErrors();
        $grupo = Grupo::firstOrFail();
        $this->put('/grupos/'.$grupo->id, array_replace($grupoDatos, ['nombre' => 'Grupo Editado']))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Grupo Editado', $grupo->fresh()->nombre);

        $this->post('/ambientes', ['nombre' => 'Aula Demo', 'capacidad' => 40])
            ->assertRedirect('/ambientes')->assertSessionHasNoErrors();
        $ambiente = Ambiente::firstOrFail();
        $this->put('/ambientes/'.$ambiente->id, ['nombre' => 'Aula Editada', 'capacidad' => 50])
            ->assertRedirect('/ambientes')->assertSessionHasNoErrors();
        $this->assertSame(50, $ambiente->fresh()->capacidad);
        $this->delete('/ambientes/'.$ambiente->id)->assertRedirect();
        $this->delete('/grupos/'.$grupo->id)->assertRedirect();
        $this->delete('/materias/'.$materia->id)->assertRedirect();
        $this->delete('/docentes/'.$docente->id)->assertRedirect();
        foreach (['ambientes', 'grupos', 'materias', 'docentes'] as $tabla) {
            $this->assertDatabaseCount($tabla, 0);
        }
    }

    public function test_importacion_csv_guarda_estudiantes_asignaciones_e_inscripciones(): void
    {
        $grupo = $this->grupo();
        $archivo = UploadedFile::fake()->createWithContent('estudiantes.csv',
            "codigo_universitario,ci,nombres,apellidos\n202600801,9900801,Ana,Demo\n202600802,9900802,Luis,Demo\n");
        $this->post('/estudiantes-importar', $this->carrera() + [
            'materia_id' => $grupo->materia_id, 'grupo_id' => $grupo->id, 'archivo' => $archivo,
        ])->assertRedirect('/estudiantes')->assertSessionHasNoErrors();
        $this->assertDatabaseCount('estudiante', 2);
        $this->assertDatabaseCount('asignaciones', 2);
        $this->assertDatabaseCount('estudiante_asignatura', 2);
    }

    public function test_una_fila_invalida_del_csv_impide_importaciones_parciales(): void
    {
        $grupo = $this->grupo();
        $archivo = UploadedFile::fake()->createWithContent('estudiantes.csv',
            "codigo_universitario,ci,nombres,apellidos\n202600801,9900801,Ana,Demo\nincorrecto,9900802,Luis,Demo\n");
        $this->post('/estudiantes-importar', $this->carrera() + [
            'materia_id' => $grupo->materia_id, 'grupo_id' => $grupo->id, 'archivo' => $archivo,
        ])->assertRedirect()->assertSessionHasErrors('importacion');
        $this->assertDatabaseCount('estudiante', 0);
        $this->assertDatabaseCount('asignaciones', 0);
    }

    public function test_gestion_de_usuario_notifica_y_conserva_la_clave_al_editar_sin_reemplazarla(): void
    {
        Mail::fake();
        $datos = ['nombre' => 'Laura', 'apellido' => 'Demo', 'grado' => 'Ingeniero(a)',
            'email' => 'control@polaris.test', 'rol' => 'control', 'estado' => 'activo',
            'password' => 'ClavePruebaSegura2026!', 'password_confirmation' => 'ClavePruebaSegura2026!'];
        $this->post('/usuarios', $datos)->assertRedirect('/usuarios')->assertSessionHasNoErrors();
        Mail::assertSent(CredencialesUsuario::class);
        $usuario = User::where('email', $datos['email'])->firstOrFail();
        $hash = $usuario->password;
        $this->put('/usuarios/'.$usuario->id, array_replace($datos, ['nombre' => 'Maria', 'password' => '', 'password_confirmation' => '']))
            ->assertRedirect('/usuarios')->assertSessionHasNoErrors();
        $this->assertSame($hash, $usuario->fresh()->password);
        $this->patch('/usuarios/'.$usuario->id.'/estado')->assertRedirect();
        $this->assertSame('inactivo', $usuario->fresh()->estado);
    }

    public function test_el_guardado_web_tambien_rechaza_combinaciones_incompatibles(): void
    {
        $grupo = $this->grupo();
        $estudiante = Estudiante::create($this->carrera() + ['codigo_universitario' => '202600803',
            'documento_identidad' => '9900803', 'nombres' => 'Ana', 'apellidos' => 'Demo']);
        $otraMateria = Materia::create(['nombre' => 'Otra', 'sigla' => 'OTRA-DEMO']);
        $this->post('/estudiantes/'.$estudiante->getKey().'/asignaciones', [
            'materia_id' => $otraMateria->id, 'grupo_id' => $grupo->id, 'docente_id' => $grupo->docente_id,
        ])->assertSessionHasErrors('grupo_id');
        $this->assertDatabaseCount('asignaciones', 0);
    }
}
