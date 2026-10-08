<?php

namespace App\Providers;

use App\Support\TenantContext;
use App\Support\WhatsApp\CloudApiCliente;
use App\Support\WhatsApp\LogCliente;
use App\Support\WhatsApp\WhatsAppCliente;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Una instancia por petición (y por job), para que el contexto no se filtre entre barberías.
        $this->app->scoped(TenantContext::class);

        // WhatsApp: modo de pruebas por defecto; `cloud` envía de verdad con las credenciales de config/whatsapp.php.
        $this->app->bind(WhatsAppCliente::class, function () {
            return match (config('whatsapp.driver')) {
                'cloud' => new CloudApiCliente(
                    (string) config('whatsapp.phone_number_id'),
                    (string) config('whatsapp.token'),
                    (string) config('whatsapp.api_version'),
                ),
                default => new LogCliente,
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
