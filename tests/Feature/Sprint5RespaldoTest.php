<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\RespaldoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/**
 * Sprint 5 · RQ26: respaldo y restauración.
 *
 * Los respaldos de prueba se guardan en un disco simulado (storage/framework/testing), no en storage/app/backups.
 * La prueba de RESTAURAR solo corre si la base de pruebas es SQLite: en MySQL restaurar hace DROP TABLE de verdad
 * y no debe ejecutarse contra tus datos (esa parte se prueba a mano, ver docs/Sprint5_RQ26.md).
 */
class Sprint5RespaldoTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->admin = User::create([
            'name' => 'Admin Respaldo', 'nombre' => 'Admin', 'apellido' => 'Respaldo',
            'email' => 'admin.respaldo@test.local', 'password' => bcrypt('x'), 'rol' => 'administrador', 'estado' => 'activo',
        ]);
    }

    public function test_crea_un_respaldo_con_manifiesto_y_huella_y_pasa_la_verificacion(): void
    {
        Storage::disk('local')->put('fotos_estudiantes/prueba.jpg', 'contenido-de-foto');

        $r = app(RespaldoService::class)->crear('manual', $this->admin);

        $this->assertMatchesRegularExpression('/^respaldo-\d{8}-\d{6}-manual$/', $r['nombre']);
        $this->assertGreaterThan(0, $r['bytes']);
        $this->assertSame(1, $r['archivos']);
        $this->assertSame(DB::table('users')->count(), $this->manifiesto($r['nombre'])['tablas']['users']);
        $this->assertSame('Admin Respaldo', $r['creado_por']);
        Storage::disk('local')->assertExists('backups/'.$r['nombre'].'.zip.sha256');

        $v = app(RespaldoService::class)->verificar($r['nombre']);
        $this->assertTrue($v['ok'], implode(' ', $v['mensajes']));
    }

    public function test_detecta_un_respaldo_alterado(): void
    {
        $servicio = app(RespaldoService::class);
        $r = $servicio->crear('manual', $this->admin);

        file_put_contents(Storage::disk('local')->path('backups/'.$r['nombre'].'.zip'), 'basura', FILE_APPEND);

        $v = $servicio->verificar($r['nombre']);
        $this->assertFalse($v['ok']);
        $this->assertStringContainsString('SHA-256', implode(' ', $v['mensajes']));
    }

    public function test_el_administrador_crea_descarga_y_elimina_desde_la_pantalla(): void
    {
        $this->actingAs($this->admin)->get(route('respaldos.index'))->assertOk()->assertSee('Respaldos de la información');

        $this->actingAs($this->admin)->post(route('respaldos.store'))->assertRedirect(route('respaldos.index'))->assertSessionHas('status');
        $lista = app(RespaldoService::class)->listar();
        $this->assertCount(1, $lista);
        $nombre = $lista[0]['nombre'];

        $this->actingAs($this->admin)->get(route('respaldos.index'))->assertOk()->assertSee($lista[0]['archivo']);
        $this->actingAs($this->admin)->get(route('respaldos.descargar', $nombre))->assertOk()->assertHeader('content-disposition');
        $this->actingAs($this->admin)->post(route('respaldos.verificar', $nombre))->assertSessionHas('verificacion.ok', true);
        $this->actingAs($this->admin)->delete(route('respaldos.destroy', $nombre))->assertRedirect();
        $this->assertCount(0, app(RespaldoService::class)->listar());
    }

    public function test_restaurar_exige_escribir_la_palabra_de_confirmacion(): void
    {
        $nombre = app(RespaldoService::class)->crear('manual', $this->admin)['nombre'];

        $this->actingAs($this->admin)->post(route('respaldos.restaurar', $nombre), ['confirmacion' => 'si'])->assertSessionHasErrors('confirmacion');
        $this->actingAs($this->admin)->post(route('respaldos.restaurar', $nombre))->assertSessionHasErrors('confirmacion');
    }

    public function test_solo_el_administrador_gestiona_respaldos_y_no_se_aceptan_nombres_raros(): void
    {
        $docente = User::create([
            'name' => 'Docente R', 'nombre' => 'Docente', 'apellido' => 'R',
            'email' => 'docente.respaldo@test.local', 'password' => bcrypt('x'), 'rol' => 'docente', 'estado' => 'activo',
        ]);

        $this->actingAs($docente)->get(route('respaldos.index'))->assertForbidden();
        $this->actingAs($docente)->post(route('respaldos.store'))->assertForbidden();

        $this->actingAs($this->admin)->get('/respaldos/..%2F..%2F.env/descargar')->assertNotFound();
        $this->actingAs($this->admin)->get('/respaldos/respaldo-20260101-000000/descargar')->assertNotFound();
    }

    public function test_los_respaldos_automaticos_viejos_se_borran_pero_los_manuales_no(): void
    {
        $servicio = app(RespaldoService::class);
        // 5 automáticos antiguos + 1 manual antiguo: se simula la antigüedad cambiando la fecha del manifiesto.
        $nombres = [];
        foreach (['automatico', 'automatico', 'automatico', 'automatico', 'automatico', 'manual'] as $i => $tipo) {
            $nombres[] = $this->respaldoConFecha($tipo, now()->subDays(60 + $i));
        }

        $borrados = $servicio->aplicarRetencion(14);

        $this->assertSame(2, $borrados);   // quedan los 3 automáticos más recientes
        $tipos = array_count_values(array_column($servicio->listar(), 'tipo'));
        $this->assertSame(3, $tipos['automatico']);
        $this->assertSame(1, $tipos['manual']);
    }

    public function test_restaurar_devuelve_la_base_y_las_fotos_al_momento_del_respaldo(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Restaurar hace DROP TABLE: solo se prueba automáticamente con SQLite.');
        }

        $servicio = app(RespaldoService::class);
        Storage::disk('local')->put('fotos_estudiantes/original.jpg', 'foto-original');
        $antes = DB::table('users')->count();
        $nombre = $servicio->crear('manual', $this->admin)['nombre'];

        // Se rompe todo: se borran usuarios, se agrega basura y se pierde la foto.
        DB::table('users')->where('id', '!=', $this->admin->id)->delete();
        User::create(['name' => 'Intruso', 'nombre' => 'I', 'apellido' => 'I', 'email' => 'intruso@test.local', 'password' => 'x', 'rol' => 'control', 'estado' => 'activo']);
        Storage::disk('local')->delete('fotos_estudiantes/original.jpg');

        $r = $servicio->restaurar($nombre, $this->admin);

        $this->assertTrue($r['ok'], implode(' ', $r['diferencias']));
        $this->assertSame($antes, DB::table('users')->count());
        $this->assertDatabaseMissing('users', ['email' => 'intruso@test.local']);
        Storage::disk('local')->assertExists('fotos_estudiantes/original.jpg');
        // Se guardó una copia del estado anterior a restaurar.
        $this->assertContains('previo', array_column($servicio->listar(), 'tipo'));
    }

    // ------------------------------------------------------------------

    private function manifiesto(string $nombre): array
    {
        $zip = new ZipArchive();
        $zip->open(Storage::disk('local')->path('backups/'.$nombre.'.zip'));
        $datos = json_decode($zip->getFromName('manifest.json'), true);
        $zip->close();

        return $datos;
    }

    /** Crea un respaldo y le cambia la fecha del manifiesto (y el nombre) para simular que es antiguo. */
    private function respaldoConFecha(string $tipo, \Carbon\Carbon $fecha): string
    {
        static $n = 0;
        $n++;
        $nombre = 'respaldo-'.$fecha->format('Ymd').'-'.sprintf('%06d', $n).'-'.$tipo;
        $ruta = Storage::disk('local')->path('backups/'.$nombre.'.zip');
        Storage::disk('local')->makeDirectory('backups');

        $zip = new ZipArchive();
        $zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('manifest.json', json_encode(['tipo' => $tipo, 'creado_en' => $fecha->toIso8601String(), 'motor' => 'sqlite', 'total_tablas' => 0, 'total_filas' => 0, 'total_archivos' => 0]));
        $zip->close();

        return $nombre;
    }
}
