<?php

namespace Tests\Feature;

use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VerificacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_control_user_can_open_verification_page(): void
    {
        $user = User::factory()->create(['rol' => 'control']);

        $this->actingAs($user)
            ->get(route('verificacion.index'))
            ->assertOk()
            ->assertSee('Verificación de identidad');
    }

    public function test_all_authenticated_users_can_open_verification_page(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);
        $docente = User::factory()->create(['rol' => 'docente']);

        $this->actingAs($admin)
            ->get(route('verificacion.index'))
            ->assertOk();

        $this->actingAs($docente)
            ->get(route('verificacion.index'))
            ->assertOk();
    }

    public function test_verification_endpoint_accepts_eligible_student(): void
    {
        $user = User::factory()->create(['rol' => 'control']);

        $estudiante = Estudiante::create([
            'codigo_universitario' => '202400001',
            'documento_identidad' => '1234567',
            'nombres' => 'Ana',
            'apellidos' => 'Pérez',
            'carrera' => 'Ingeniería de Sistemas',
            'correo_institucional' => '202400001@universidad.edu',
            'estado' => 'ACTIVO',
            'facultad' => 'Facultad de Ingeniería',
            'ci_complemento' => 'A',
        ]);

        $idAsignatura = DB::table('asignatura')->insertGetId([
            'codigo' => 'MAT-101',
            'nombre' => 'Matemáticas I',
        ]);

        DB::table('estudiante_asignatura')->insert([
            'id_estudiante' => $estudiante->id_estudiante,
            'id_asignatura' => $idAsignatura,
            'gestion' => '2026',
            'estado' => 'ACTIVO',
        ]);

        $examen = Examen::create([
            'asignatura_id' => $idAsignatura,
            'carrera' => 'Ingeniería de Sistemas',
            'fecha' => now()->addDay()->toDateString(),
            'hora_inicio' => '09:00:00',
            'duracion_min' => 90,
            'ambiente' => 'Aula 101',
            'capacidad' => 30,
            'estado' => 'programado',
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('verificacion.verificar'), [
                'id_examen' => $examen->id,
                'codigo_universitario' => '202400001',
            ]);

        $response->assertOk()
            ->assertJsonPath('tipo', 'habilitado')
            ->assertJsonPath('estudiante.codigo', '202400001')
            ->assertJsonPath('estudiante.nombres', 'Ana')
            ->assertJsonPath('estudiante.apellidos', 'Pérez')
            ->assertJsonPath('estudiante.documento_identidad', '1234567')
            ->assertJsonPath('estudiante.carrera', 'Ingeniería de Sistemas');
    }

    public function test_verification_returns_alert_when_university_code_is_not_registered(): void
    {
        $user = User::factory()->create(['rol' => 'control']);

        $examen = Examen::create([
            'asignatura_id' => null,
            'carrera' => 'Ingeniería de Sistemas',
            'fecha' => now()->addDay()->toDateString(),
            'hora_inicio' => '09:00:00',
            'duracion_min' => 90,
            'ambiente' => 'Aula 101',
            'capacidad' => 30,
            'estado' => 'programado',
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('verificacion.verificar'), [
                'id_examen' => $examen->id,
                'codigo_universitario' => '202499999',
            ]);

        $response->assertStatus(404)
            ->assertJsonPath('tipo', 'codigo_no_registrado')
            ->assertJsonPath('mensaje', 'Código universitario no registrado');
    }

    public function test_verification_block_student_without_habilitacion_record(): void
    {
        $user = User::factory()->create(['rol' => 'control']);

        $estudiante = Estudiante::create([
            'codigo_universitario' => '202400002',
            'documento_identidad' => '7654321',
            'nombres' => 'Luis',
            'apellidos' => 'Mendoza',
            'carrera' => 'Ingeniería de Sistemas',
            'correo_institucional' => '202400002@universidad.edu',
            'estado' => 'ACTIVO',
            'facultad' => 'Facultad de Ingeniería',
            'ci_complemento' => null,
        ]);

        $idAsignatura = DB::table('asignatura')->insertGetId([
            'codigo' => 'MAT-202',
            'nombre' => 'Matemáticas II',
        ]);

        $examen = Examen::create([
            'asignatura_id' => $idAsignatura,
            'carrera' => 'Ingeniería de Sistemas',
            'fecha' => now()->addDay()->toDateString(),
            'hora_inicio' => '10:00:00',
            'duracion_min' => 90,
            'ambiente' => 'Aula 102',
            'capacidad' => 28,
            'estado' => 'programado',
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('verificacion.verificar'), [
                'id_examen' => $examen->id,
                'codigo_universitario' => '202400002',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('tipo', 'no_habilitado')
            ->assertJsonPath('motivo_inhabilitacion', 'No tiene habilitación para este examen.');
    }
}
