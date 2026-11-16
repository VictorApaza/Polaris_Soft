<?php

namespace Tests\Feature;

use App\Models\Ambiente;
use App\Models\HistorialOperacion;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use LogicException;
use Tests\TestCase;

class HistorialOperacionTest extends TestCase
{
    use DatabaseTransactions;

    private function usuario(string $rol): User
    {
        return User::create([
            'name' => ucfirst($rol).' Historial',
            'nombre' => ucfirst($rol),
            'apellido' => 'Historial',
            'email' => "{$rol}.historial@test.local",
            'password' => bcrypt('x'),
            'rol' => $rol,
            'estado' => 'activo',
        ]);
    }

    public function test_el_historial_es_solo_para_administradores(): void
    {
        $admin = $this->usuario('administrador');
        $docente = $this->usuario('docente');

        HistorialOperacion::create([
            'usuario_id' => $admin->id,
            'usuario_nombre' => $admin->name,
            'usuario_rol' => $admin->rol,
            'accion' => 'Registrar estudiante',
            'detalle' => 'Estudiante #12',
            'registros_afectados' => 1,
            'estado' => 'correcto',
            'fecha_hora' => now(),
        ]);

        $this->actingAs($docente)->get(route('historial-operaciones.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('historial-operaciones.index'))
            ->assertOk()
            ->assertSee('Historial de operaciones')
            ->assertSee('Registrar estudiante')
            ->assertSee('Solo lectura');
    }

    public function test_las_operaciones_se_registran_sin_interrumpir_la_accion(): void
    {
        $admin = $this->usuario('administrador');

        $respuesta = $this->actingAs($admin)->post(route('ambientes.store'), [
            'nombre' => 'Auditoria '.uniqid(),
            'capacidad' => 30,
            'ubicacion' => 'Bloque A',
        ]);

        $respuesta->assertRedirect(route('ambientes.index'));
        $this->assertDatabaseHas('historial_operaciones', [
            'usuario_id' => $admin->id,
            'accion' => 'Registrar ambiente',
            'estado' => 'correcto',
        ]);

        $this->assertSame(1, (int) HistorialOperacion::where('usuario_id', $admin->id)->latest('id')->value('registros_afectados'));
    }

    public function test_rechazos_quedan_registrados_y_el_historial_no_se_puede_editar(): void
    {
        $admin = $this->usuario('administrador');
        $ambiente = Ambiente::create(['nombre' => 'Auditoria duplicado', 'capacidad' => 20]);

        $this->actingAs($admin)->post(route('ambientes.store'), [
            'nombre' => $ambiente->nombre,
            'capacidad' => 30,
        ])->assertRedirect();

        $registro = HistorialOperacion::latest('id')->firstOrFail();
        $this->assertSame('error', $registro->estado);
        $this->assertSame(0, $registro->registros_afectados);

        $this->expectException(LogicException::class);
        $registro->delete();
    }
}
