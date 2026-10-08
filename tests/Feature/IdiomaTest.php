<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdiomaTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_app_esta_en_espanol(): void
    {
        $this->assertSame('es', app()->getLocale());
    }

    public function test_los_errores_de_validacion_salen_en_espanol_y_no_como_claves(): void
    {
        $respuesta = $this->post('/register', []);

        $errores = session('errors')->getBag('default')->all();

        $this->assertNotEmpty($errores);
        foreach ($errores as $mensaje) {
            $this->assertStringNotContainsString('validation.', $mensaje);
        }
        $respuesta->assertSessionHasErrors(['barberia' => 'El campo nombre de la barbería es obligatorio.']);
    }

    public function test_el_error_de_credenciales_incorrectas_esta_traducido(): void
    {
        User::factory()->create(['email' => 'dueno@cupo.test']);

        $this->post('/login', ['email' => 'dueno@cupo.test', 'password' => 'incorrecta'])
            ->assertSessionHasErrors(['email' => 'Estas credenciales no coinciden con nuestros registros.']);
    }

    public function test_los_mensajes_de_los_formularios_del_panel_usan_nombres_legibles(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post('/servicios', ['nombre' => '', 'duracion_minutos' => 1, 'precio' => 100, 'activo' => true])
            ->assertSessionHasErrors([
                'nombre' => 'El campo nombre es obligatorio.',
                'duracion_minutos' => 'Duración debe estar entre 5 y 480.',
            ]);
    }
}
