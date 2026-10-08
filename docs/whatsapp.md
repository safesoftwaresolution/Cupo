# Recordatorios por WhatsApp

Cupo avisa a los clientes antes de su cita y deja que **confirmen o cancelen con un toque** desde el mismo mensaje. Esto es lo que más baja las faltas.

## Cómo funciona

1. Cada 5 minutos corre `php artisan recordatorios:enviar`. Busca las citas que empiezan dentro de las horas que configuró la barbería (por defecto 24 h), siguen activas y cuyo cliente **autorizó recibir mensajes** al reservar.
2. Por cada una crea un registro en la tabla `mensajes_whatsapp` y manda un trabajo a la cola. Ese registro es único por cita: aunque el comando se ejecute varias veces, **cada cita recibe un solo recordatorio**.
3. El trabajo envía la plantilla por la WhatsApp Cloud API. Si falla por algo pasajero (red, límite de uso) se reintenta 3 veces; si Meta lo rechaza de forma definitiva (número inválido) queda registrado con el motivo.
4. El cliente toca **Confirmar** o **Cancelar**. Meta llama al webhook `POST /webhooks/whatsapp`, Cupo actualiza la cita y le responde con un texto (esa respuesta no tiene costo porque el cliente acaba de escribir).
5. Meta también avisa si el mensaje fue entregado o leído. Todo se ve en **Mensajes** dentro del panel.

No se envía nada si: la cita se canceló o ya pasó, el cliente no autorizó mensajes, la barbería desactivó los recordatorios o faltan menos de 30 minutos.

## Modo de pruebas (por defecto)

Con `WHATSAPP_DRIVER=log` no sale ningún mensaje real: todo queda en `storage/logs/laravel.log` y en la pantalla Mensajes aparece un aviso de "Modo de pruebas". Sirve para desarrollar y para enseñar el flujo.

Para probarlo en local:

```bash
php artisan queue:work          # procesa los envíos (composer run dev ya lo incluye)
php artisan schedule:work       # ejecuta el comando cada 5 minutos (composer run dev ya lo incluye)
php artisan recordatorios:enviar   # o corrido a mano, una sola vez
```

## Conectar WhatsApp de verdad

Necesitas una cuenta de Meta Business. Los costos de mensajes por conversación están en el documento de negocio.

### 1. App y número en Meta

1. Entra a [developers.facebook.com](https://developers.facebook.com), crea una **app de tipo Business** y agrégale el producto **WhatsApp**.
2. En *WhatsApp → Configuración de la API* agrega y verifica el número desde el que se enviará. Anota el **ID del número de teléfono** (no es el teléfono).
3. Crea un **usuario del sistema** en el Administrador de Negocios, asígnale la app y genera un **token permanente** con los permisos `whatsapp_business_messaging` y `whatsapp_business_management`. El token temporal de 24 h no sirve en producción.
4. En *Configuración de la app → Básica* copia el **Secreto de la app**.

### 2. La plantilla

En el Administrador de WhatsApp crea una plantilla y espera su aprobación (minutos a un par de días):

| Campo | Valor |
| --- | --- |
| Nombre | `recordatorio_cita` |
| Categoría | **Utilidad** |
| Idioma | Español (`es`) |
| Cuerpo | `Hola {{1}}, te recordamos tu cita en {{2}}: {{3}} el {{4}} a las {{5}}. ¿Nos confirmas?` |
| Botones | Respuesta rápida: `Confirmar` y `Cancelar` (en ese orden) |

Las variables son: nombre del cliente, nombre de la barbería, servicios, fecha ("lunes 12 de octubre") y hora ("10:00 a. m."). Si cambias el texto o el orden, ajusta `app/Support/WhatsApp/MensajeRecordatorio.php`.

### 3. El webhook

En *WhatsApp → Configuración → Webhook* registra:

- **URL de devolución de llamada:** `https://TU-DOMINIO/webhooks/whatsapp`
- **Token de verificación:** la palabra que pongas en `WHATSAPP_VERIFY_TOKEN`
- Suscríbete al campo **`messages`**.

### 4. Variables de entorno

```env
WHATSAPP_DRIVER=cloud
WHATSAPP_PHONE_NUMBER_ID=123456789012345
WHATSAPP_TOKEN=EAAG...
WHATSAPP_APP_SECRET=abcdef...
WHATSAPP_VERIFY_TOKEN=una-palabra-larga-y-secreta
WHATSAPP_TEMPLATE_RECORDATORIO=recordatorio_cita
```

`WHATSAPP_APP_SECRET` es obligatorio en producción: con él Cupo comprueba la firma de cada llamada del webhook. **Sin el secreto, en producción el webhook rechaza todo**, para que nadie pueda confirmar o cancelar citas haciéndose pasar por Meta.

### 5. Procesos en producción (Railway)

Además del servicio web hacen falta dos procesos, como servicios aparte del mismo repositorio:

| Servicio | Comando |
| --- | --- |
| Cola | `php artisan queue:work --tries=3 --max-time=3600` |
| Programador | `php artisan schedule:work` |

Sin el programador no se programa ningún recordatorio; sin la cola se programan pero no salen.

## Qué falta

- Cada barbería usa hoy el **mismo número de WhatsApp de Cupo**, configurado en `.env`. Que cada barbería conecte su propio número requiere el flujo *Embedded Signup* de Meta y la revisión de la app.
- Los mensajes de texto libre del cliente se ignoran. El asistente que agenda por WhatsApp es la siguiente etapa.
