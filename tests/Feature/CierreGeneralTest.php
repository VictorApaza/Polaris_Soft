<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Disponibilidad: el punto de salud responde sin iniciar sesión y no revela datos. */
class CierreGeneralTest extends TestCase
{
    public function test_el_punto_de_salud_es_publico_y_responde_ok(): void
    {
        $this->getJson('/salud')->assertOk()
            ->assertJsonPath('estado', 'ok')
            ->assertJsonPath('base_datos', 'ok')
            ->assertJsonMissingPath('usuarios')
            ->assertHeader('Cache-Control', 'no-store, private');
    }
}
