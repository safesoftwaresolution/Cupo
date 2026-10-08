<?php

namespace Tests\Feature\Auth;

use App\Enums\Rol;
use App\Models\Barberia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function datos(array $extra = []): array
    {
        return [
            'barberia' => 'Barbería El Corte',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            ...$extra,
        ];
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register()
    {
        $response = $this->post('/register', $this->datos());

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_registrarse_crea_la_barberia_y_deja_al_usuario_como_dueno()
    {
        $this->post('/register', $this->datos());

        $barberia = Barberia::firstWhere('nombre', 'Barbería El Corte');
        $usuario = User::firstWhere('email', 'test@example.com');

        $this->assertNotNull($barberia);
        $this->assertSame('barberia-el-corte', $barberia->slug);
        $this->assertTrue($barberia->enPrueba());
        $this->assertSame($barberia->id, $usuario->barberia_id);
        $this->assertSame(Rol::Dueno, $usuario->rol);
    }

    public function test_dos_barberias_con_el_mismo_nombre_obtienen_slugs_distintos()
    {
        $this->post('/register', $this->datos());
        auth()->logout();
        $this->post('/register', $this->datos(['email' => 'otro@example.com']));

        $slugs = Barberia::pluck('slug');

        $this->assertCount(2, $slugs);
        $this->assertCount(2, $slugs->unique());
    }

    public function test_el_nombre_de_la_barberia_es_obligatorio()
    {
        $response = $this->post('/register', $this->datos(['barberia' => '']));

        $response->assertSessionHasErrors('barberia');
        $this->assertGuest();
        $this->assertSame(0, Barberia::count());
    }

    public function test_no_se_puede_elegir_barberia_ni_rol_desde_el_formulario()
    {
        $ajena = Barberia::factory()->create();

        $this->post('/register', $this->datos(['barberia_id' => $ajena->id, 'rol' => 'barbero']));

        $usuario = User::firstWhere('email', 'test@example.com');

        $this->assertNotSame($ajena->id, $usuario->barberia_id);
        $this->assertSame(Rol::Dueno, $usuario->rol);
    }
}
