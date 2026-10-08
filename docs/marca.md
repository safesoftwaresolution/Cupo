# Cupo — Guía de marca

Fuente de verdad del diseño. Los valores viven en `resources/css/app.css` (tokens) y `tailwind.config.js`.

## Idea

**Cupo** es el espacio en la agenda de un barbero. La marca habla de lo que el dueño más quiere: cupos llenos, sin cruces y sin perder tiempo en el WhatsApp.

- **Personalidad:** oficio, confianza, orden. Una barbería tradicional con herramientas modernas.
- **Tono de voz:** cercano y directo. Tuteo ("tu barbería", "tus clientes"), frases cortas, cosas concretas.
- **Promesa:** "Tu agenda llena. Tu WhatsApp en paz."

### Cómo escribir

| Sí | No |
| --- | --- |
| "Crea tu primer servicio" | "Proceda a configurar un nuevo servicio" |
| "Este barbero tiene citas pendientes" | "Error 409: conflicto de recursos" |
| "Cupo libre" | "Slot disponible" |
| Pesos con punto de miles: $69.900 | $69,900 / 69900 |

## Logo

Una **"C" abierta** (el hueco de la agenda) con un **punto** dentro (el cliente que reservó), sobre un cuadro de esquinas redondeadas.

| Versión | Uso | Archivo |
| --- | --- | --- |
| Cuadro tinta + C dorada | Sobre fondos claros | `AppLogoIcon` (`sobre-claro`) |
| Cuadro dorado + C tinta | Sobre fondos tinta (barra lateral, pie) | `AppLogoIcon` (`sobre-oscuro`) |
| Marca + palabra "cupo" | Encabezados, login, landing | `BrandLogo` |
| Favicon | Pestaña del navegador | `public/favicon.svg` |
| Logo horizontal | Documentos, redes | `public/logo.svg` |

- La palabra va en minúscula, **Outfit ExtraBold**, espaciado apretado (-0,05em).
- Espacio libre alrededor: al menos la mitad del alto del cuadro.
- Tamaño mínimo: 24 px de alto para la marca sola.
- **No:** cambiar los colores, inclinarla, añadir sombras, ni poner la versión tinta sobre fondos oscuros.

## Color

Base cálida: tinta (casi negro con un toque marrón), hueso y arena. Un solo color de acento, el dorado latón, usado con intención.

| Rol | Nombre | Claro | Oscuro | Uso |
| --- | --- | --- | --- | --- |
| Texto y botón principal | Tinta | `#1C1917` | `#FAFAF9` | Texto, botón principal (claro) |
| Fondo | Hueso | `#FAFAF9` | `#0C0A09` | Fondo de la app |
| Superficie | Blanco / Tinta suave | `#FFFFFF` | `#1C1917` | Tarjetas, diálogos |
| Acento de marca | Dorado latón | `#A16207` | `#FCD34D` | Enlaces, énfasis, estados "pendiente", botón principal (oscuro) |
| Acento suave | Dorado pálido | `#FEF5DD` | `#322815` | Fondos de insignias e íconos |
| Éxito | Verde cupo libre | `#15803D` | `#47D17A` | Confirmada, completado |
| Error | Rojo | `#DC2626` | `#E14747` | Errores y acciones destructivas |
| Texto secundario | Piedra | `#57534E` | `#A8A29E` | Descripciones, ayudas |
| Borde de campo | Piedra media | `#9A948F` | `#6B6561` | Contorno de inputs (3:1) |

**Contraste verificado:** el dorado `#A16207` sobre blanco da 4,9:1 y el texto secundario sobre hueso 7:1; ambos cumplen WCAG AA (4,5:1). No uses el dorado claro `#FCD34D` como texto sobre fondos claros.

**Reglas**
- 80% neutros, 15% tinta, 5% dorado. Si todo es dorado, nada destaca.
- Un solo botón principal por pantalla.
- El color nunca es la única señal: estados llevan texto ("Confirmada", "Sin horario").
- La barra lateral es siempre tinta, en modo claro y oscuro.
- En modo oscuro, el botón principal pasa a dorado con texto tinta.

**Textura de marca:** franjas diagonales tipo poste de barbero, muy tenues (`.bg-poste`). Solo en fondos grandes (paneles de marca, estados vacíos), nunca detrás de texto largo.

## Tipografía

| Uso | Fuente | Pesos |
| --- | --- | --- |
| Títulos, cifras grandes, logo | **Outfit** | 600, 700, 800 |
| Texto, interfaz, formularios | **Plus Jakarta Sans** | 400, 500, 600, 700 |

- Cuerpo base 16 px, interlineado 1,5. Nada de texto de cuerpo menor a 12 px.
- Títulos con `tracking-tight`. Cifras con `tabular-nums` para que alineen.
- Cargadas desde Bunny Fonts (`resources/views/app.blade.php`).

## Interfaz

- **Tacto:** objetivos de 44 px en móvil (botones, campos, casillas). En escritorio se compactan a 40 px.
- **Foco visible:** anillo dorado de 2 px en todo lo interactivo.
- **Movimiento:** transiciones de 150 a 300 ms; se desactivan con `prefers-reduced-motion`.
- **Estados vacíos:** ícono + una frase de qué es + un botón con la primera acción.
- **Formularios:** etiqueta siempre visible (no solo placeholder), error junto al campo.
- **Íconos:** Lucide, trazo uniforme, nunca emojis.
- **Esquinas:** 10 px (`--radius`); tarjetas grandes 14 px.

## Componentes de marca

| Componente | Archivo |
| --- | --- |
| Logo / marca | `components/AppLogoIcon.vue`, `components/BrandLogo.vue` |
| Encabezado de página | `components/PageHeader.vue` |
| Estado vacío | `components/EmptyState.vue` |
| Insignias (`default`, `muted`, `brand`, `success`, `outline`) | `components/ui/badge` |
| Botón (`default`, `brand`, `outline`, `ghost`, `destructive`) | `components/ui/button` |
