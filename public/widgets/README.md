# Tickets Widget

Widget de soporte para insertar en cualquier página. Es un asistente de 4 pasos:
**Compañía → Categoría → Detalle → Revisión y envío**, con compañías y categorías
mostradas como cards seleccionables.

## Estructura

```text
widgets/
├── tickets.js              ← único script que incluye la página anfitriona
├── css/
│   └── widget.css          ← todos los estilos (se inyectan en el Shadow DOM)
├── templates/              ← HTML de los componentes (se editan como HTML normal)
│   ├── widget.html         ← estructura principal: modal, pasos, formulario
│   ├── option-card.html    ← card de compañía / categoría
│   ├── chip.html           ← opción de sucursal / subcategoría
│   ├── review.html         ← resumen del paso "Revisión y envío"
│   ├── ticket-item.html    ← ticket en "Mis tickets"
│   └── state.html          ← mensajes de carga / vacío / error
└── js/
    ├── app.js              ← controlador: eventos, pasos, validaciones
    ├── template.js         ← carga los .html y reemplaza los {{marcadores}}
    ├── icons.js            ← colores e iconos de las cards
    ├── api.js
    ├── companies.js
    ├── branches.js
    ├── categories.js
    └── tickets.js
```

## Componentes en HTML

Los componentes **no se escriben dentro del JS**. Cada uno es un archivo `.html`
dentro de `templates/` con marcadores:

| Marcador      | Resultado                                     |
|---------------|-----------------------------------------------|
| `{{campo}}`   | valor escapado (texto seguro)                 |
| `{{{campo}}}` | valor sin escapar (solo para SVG/HTML propio) |

Ejemplo, `templates/chip.html`:

```html
<button type="button" class="tw-chip" data-value="{{value}}">
    {{label}}
</button>
```

Y desde JS:

```js
import { render, renderList } from "./template.js";

// Un elemento
const chip = await render("chip", { value: "B1", label: "Matriz" });

// Una lista
lista.replaceChildren(
    await renderList("chip", sucursales, s => ({ value: s.id, label: s.name }))
);
```

Para crear un componente nuevo: agrega `templates/mi-componente.html` y llámalo con
`render("mi-componente", {...})`. Las plantillas se descargan una sola vez y quedan en caché.

## Uso desde otra página

```html
<div id="tickets-widget"></div>

<script
    src="https://api.pwmultiroma.com/widgets/tickets.js"
    data-container="tickets-widget"
    data-api="https://api.pwmultiroma.com/api"
    data-version="1">
</script>
```

- Los módulos, plantillas y CSS se cargan **relativos a la URL de `tickets.js`**.
- `data-version` (opcional) se agrega como `?v=` para forzar al navegador a
  descargar los archivos nuevos después de un cambio.

## Endpoints utilizados

```text
GET  /api/companies
GET  /api/branches?company={CodCompanies}
GET  /api/categories
GET  /api/subcategories                 (resumen dentro de cada card)
GET  /api/subcategories/category/{id}   (respaldo si el anterior falla)
POST /api/tickets
POST /api/tickets/{id}/messages         (guarda la descripción como primer mensaje)
GET  /api/tickets
```

El ticket se envía con los campos del modelo `TicketModel`:
`CodCompanies, CodBranches, IdCategory, IdSubCategory, RequesterEmail, Subject, Priority, Status`.

## Importante

Como las plantillas y el CSS se leen con `fetch()`, si el widget se incrusta en otro
dominio, Nginx/CodeIgniter debe responder con CORS también para `/widgets/*`
(igual que ya se necesita para los módulos `import()`).

Si el certificado HTTPS no es válido para la IP pública, el navegador también puede
bloquear las peticiones. Lo recomendable para producción es utilizar un dominio con
certificado TLS válido.

## PWA "Mis tickets" (`/widgets/`)

`index.html` es una app instalable (PWA) para montar y seguir tickets desde el celular,
incluso sin estar dentro del sistema anfitrión:

- Muestra los tickets del solicitante (pendientes / resueltos) y el botón **Montar ticket**,
  que abre este mismo widget; al tocar un ticket abre su detalle y conversación.
- El anfitrión puede enlazarla pasando los datos del usuario por la URL, p. ej.
  `https://api.pwmultiroma.com/widgets/?email=ana@empresa.com&name=Ana%20Pérez&company=01&branch=001&area=Comercial&user=aperez&phone=300...&rol=Usuario`.
  Se guardan en el dispositivo y se quitan de la barra de direcciones.
- Quien entra sin esos datos ve un formulario de perfil una sola vez (nombre, correo, compañía, sede, área).
- Archivos: `index.html`, `manifest.webmanifest`, `sw.js` (funciona sin conexión), `css/pwa.css`, `js/pwa.js`.
  Al cambiar archivos de la PWA, sube `VERSION` en `sw.js`.
- Las notificaciones push usan `/firebase-messaging-sw.js` (en iPhone requieren instalar la app primero). Si no están activas en el dispositivo, el inicio muestra un aviso rojo para activarlas; el token se registra en la API de notificaciones a través de `POST /api/fcm-tokens`.
