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
    ├── api.js              ← peticiones a /widget con el token (sin claves)
    ├── identity.js         ← lee /widget/me
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

El widget **no lleva claves**: se identifica con un token firmado que genera el
servidor de la página anfitriona. Guía completa, con ejemplos en PHP, Node y
Python, en [`docs/widget-host/README.md`](../../docs/widget-host/README.md).

```html
<div id="tickets-widget"></div>

<script
    src="https://tu-servidor:8081/widgets/tickets.js"
    data-container="tickets-widget"
    data-token="<token firmado por tu servidor>"
    data-token-url="/token-endpoint.php"
    data-version="1">
</script>
```

- Los módulos, plantillas y CSS se cargan **relativos a la URL de `tickets.js`**.
- `data-token-url` (recomendado) renueva el token cuando vence.
- `data-api` es opcional: por defecto `https://tu-servidor:8081/widget`.
- `data-version` se agrega como `?v=` para forzar la descarga de archivos nuevos.

## Endpoints utilizados (API pública `/widget`)

```text
GET  /widget/me                           perfil firmado en el token
GET  /widget/companies
GET  /widget/branches?company={CodCompanies}
GET  /widget/categories
GET  /widget/subcategories
GET  /widget/subcategories/category/{id}
GET  /widget/ticket-forms
GET  /widget/users                        solo nombres de usuarios activos
GET  /widget/tickets/mine                 Mis tickets (pendientes/resueltos, fechas, paginación)
GET  /widget/tickets/{id}/detalle         solo si el ticket es del correo del token
POST /widget/tickets/{id}/responder       el solicitante escribe en su ticket
POST /widget/tickets                      crea ticket (+ primer mensaje, formulario y adjuntos)
```

Todos exigen el header `X-Widget-Token`.

## Importante

- Como las plantillas y el CSS se leen con `fetch()` y los módulos con `import()`,
  Nginx debe enviar `Access-Control-Allow-Origin: *` en `/widgets/` (son archivos
  públicos sin secretos). Ver la sección 6 de la guía del anfitrión.
- La API `/widget` responde CORS solo a los dominios de `widget.origins` (`.env`).
- Si el certificado HTTPS no es válido para la IP, el navegador bloqueará las
  peticiones. Para producción usa un dominio con certificado TLS válido.
