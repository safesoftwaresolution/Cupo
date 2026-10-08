<?php

namespace Tests\Feature;

use App\Support\WhatsApp\CloudApiCliente;
use App\Support\WhatsApp\LogCliente;
use App\Support\WhatsApp\WhatsAppCliente;
use App\Support\WhatsApp\WhatsAppError;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class WhatsAppClienteTest extends TestCase
{
    private function cliente(): CloudApiCliente
    {
        return new CloudApiCliente('1234567890', 'token-secreto', 'v21.0');
    }

    public function test_envia_la_plantilla_con_variables_y_botones_en_el_formato_de_meta(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.ABC']]])]);

        $id = $this->cliente()->enviarPlantilla('573001234567', 'recordatorio_cita', 'es', ['Juan', 'Mi Barbería'], ['confirmar:abc', 'cancelar:abc']);

        $this->assertSame('wamid.ABC', $id);

        Http::assertSent(function (Request $request) {
            $this->assertSame('https://graph.facebook.com/v21.0/1234567890/messages', $request->url());
            $this->assertSame('Bearer token-secreto', $request->header('Authorization')[0]);
            $this->assertSame([
                'messaging_product' => 'whatsapp',
                'to' => '573001234567',
                'type' => 'template',
                'template' => [
                    'name' => 'recordatorio_cita',
                    'language' => ['code' => 'es'],
                    'components' => [
                        ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => 'Juan'], ['type' => 'text', 'text' => 'Mi Barbería']]],
                        ['type' => 'button', 'sub_type' => 'quick_reply', 'index' => '0', 'parameters' => [['type' => 'payload', 'payload' => 'confirmar:abc']]],
                        ['type' => 'button', 'sub_type' => 'quick_reply', 'index' => '1', 'parameters' => [['type' => 'payload', 'payload' => 'cancelar:abc']]],
                    ],
                ],
            ], $request->data());

            return true;
        });
    }

    public function test_envia_un_texto_libre(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.TXT']]])]);

        $this->assertSame('wamid.TXT', $this->cliente()->enviarTexto('573001234567', 'Hola'));

        Http::assertSent(fn (Request $r) => $r->data()['type'] === 'text' && $r->data()['text']['body'] === 'Hola');
    }

    public function test_un_rechazo_de_meta_no_se_reintenta_y_explica_el_motivo(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Template name does not exist', 'code' => 132001]], 400)]);

        try {
            $this->cliente()->enviarPlantilla('573001234567', 'no_existe', 'es', []);
            $this->fail('Debió lanzar WhatsAppError.');
        } catch (WhatsAppError $e) {
            $this->assertFalse($e->reintentable);
            $this->assertStringContainsString('132001', $e->getMessage());
            $this->assertStringContainsString('Template name does not exist', $e->getMessage());
        }
    }

    public function test_los_errores_del_servidor_y_el_limite_de_uso_si_se_reintentan(): void
    {
        foreach ([500, 503, 429] as $codigo) {
            Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'x']], $codigo)]);

            try {
                $this->cliente()->enviarTexto('573001234567', 'Hola');
                $this->fail("El código {$codigo} debió lanzar WhatsAppError.");
            } catch (WhatsAppError $e) {
                $this->assertTrue($e->reintentable, "El código {$codigo} debe poder reintentarse.");
            }
        }
    }

    public function test_un_fallo_de_conexion_se_reintenta(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        try {
            $this->cliente()->enviarTexto('573001234567', 'Hola');
            $this->fail('Debió lanzar WhatsAppError.');
        } catch (WhatsAppError $e) {
            $this->assertTrue($e->reintentable);
        }
    }

    public function test_una_respuesta_sin_identificador_es_un_error(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => []])]);

        $this->expectException(WhatsAppError::class);
        $this->cliente()->enviarTexto('573001234567', 'Hola');
    }

    public function test_el_modo_de_pruebas_no_sale_a_internet_y_deja_registro(): void
    {
        Http::fake();
        Log::spy();

        $id = (new LogCliente)->enviarPlantilla('573001234567', 'recordatorio_cita', 'es', ['Juan']);

        $this->assertStringStartsWith('log.', $id);
        Http::assertNothingSent();
        Log::shouldHaveReceived('info')->once();
    }

    public function test_el_driver_configurado_decide_que_cliente_se_usa(): void
    {
        config(['whatsapp.driver' => 'log']);
        $this->assertInstanceOf(LogCliente::class, app(WhatsAppCliente::class));

        config(['whatsapp.driver' => 'cloud', 'whatsapp.phone_number_id' => '1', 'whatsapp.token' => 't', 'whatsapp.api_version' => 'v21.0']);
        $this->assertInstanceOf(CloudApiCliente::class, app(WhatsAppCliente::class));
    }

    public function test_por_defecto_el_modo_es_de_pruebas(): void
    {
        $this->assertSame('log', config('whatsapp.driver'));
    }
}
