<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Concerns\EsquemaClinica;

class ExampleTest extends TestCase
{
    use EsquemaClinica;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        // La portada lee especialidades y médicos de la base de datos
        $this->crearEsquemaClinica();

        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
