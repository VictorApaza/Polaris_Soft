<?php

namespace Tests\Feature;

use Tests\TestCase;

class ConfiguracionDespliegueTest extends TestCase
{
    public function test_en_produccion_el_acceso_http_se_redirige_al_dominio_https_configurado(): void
    {
        config(['app.env' => 'production', 'app.url' => 'https://polarissoft.tis.cs.umss.edu.bo']);
        $this->withHeader('Host', 'dominio-ajeno.example')->get('/login?origen=prueba')
            ->assertStatus(308)->assertRedirect('https://polarissoft.tis.cs.umss.edu.bo/login?origen=prueba');
    }

    public function test_la_peticion_https_no_produce_un_bucle_y_aplica_cabeceras_de_seguridad(): void
    {
        config(['app.env' => 'production', 'app.url' => 'https://polarissoft.tis.cs.umss.edu.bo']);
        $this->get('https://polarissoft.tis.cs.umss.edu.bo/login')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_el_entorno_local_http_conserva_el_formulario_de_acceso(): void
    {
        config(['app.env' => 'production', 'app.url' => 'http://127.0.0.1:8018']);
        $this->get('/login')->assertOk();
    }
}
