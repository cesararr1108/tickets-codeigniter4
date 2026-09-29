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
    src="https://200.122.206.204:8081/widgets/tickets.js"
    data-container="tickets-widget"
    data-api="https://200.122.206.204:8081/api"
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
