<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Permisos por rol y paneles de Inicio:
 *  - solo el administrador gestiona cuentas y cambia roles;
 *  - solo el personal de control verifica ingresos (ni docente ni administrador);
 *  - cada rol ve su panel y su menú.
 */
class Sprint4RolesYMenuTest extends TestCase
{
    use DatabaseTransactions;

    private function usuario(string $rol): User
    {
        return User::create([
            'name' => ucfirst($rol).' Menu', 'nombre' => ucfirst($rol), 'apellido' => 'Menu',
            'email' => "{$rol}.menu@test.local", 'password' => bcrypt('x'), 'rol' => $rol, 'estado' => 'activo',
        ]);
    }

    public function test_solo_el_administrador_gestiona_cuentas_y_roles(): void
    {
        $admin = $this->usuario('administrador');

        foreach (['docente', 'control'] as $rol) {
            $u = $this->usuario($rol);
            $this->actingAs($u)->get('/usuarios')->assertForbidden();
            $this->actingAs($u)->put("/usuarios/{$admin->id}", ['rol' => 'docente'])->assertForbidden();
            $this->actingAs($u)->patch("/usuarios/{$admin->id}/estado")->assertForbidden();
            $u->delete();
        }

        $this->actingAs($admin)->get('/usuarios')->assertOk();
    }

    public function test_solo_el_personal_de_control_verifica_ingresos(): void
    {
        foreach (['administrador', 'docente'] as $rol) {
            $u = $this->usuario($rol);
            $this->actingAs($u)->get('/verificacion')->assertForbidden();
            $this->actingAs($u)->postJson('/verificacion/verificar', [])->assertForbidden();
            $this->actingAs($u)->postJson('/verificacion/ingreso', [])->assertForbidden();
            $u->delete();
        }

        $this->actingAs($this->usuario('control'))->get('/verificacion')->assertOk();
    }

    public function test_cada_rol_ve_su_panel_y_su_menu(): void
    {
        $this->actingAs($this->usuario('administrador'))->get('/admin/dashboard')
            ->assertOk()->assertSee('Panel del administrador')->assertSee('Usuarios')->assertDontSee('Verificar ingreso');
    }

    public function test_panel_del_docente_y_del_personal_de_control(): void
    {
        $this->actingAs($this->usuario('docente'))->get('/docente/dashboard')
            ->assertOk()->assertSee('Panel del docente')->assertSee('Habilitar estudiantes')->assertDontSee('Cuentas y roles')->assertDontSee('Verificar ingreso');

        $this->actingAs($this->usuario('control'))->get('/control/dashboard')
            ->assertOk()->assertSee('Panel del personal de control')->assertSee('Verificar ingreso')->assertDontSee('Habilitar estudiantes');
    }
}
