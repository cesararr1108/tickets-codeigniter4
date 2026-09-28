# Tickets API (CodeIgniter 4)

API REST para el sistema de tickets (Companies, Branches, Users, Roles,
Category/SubCategory, Tickets, TicketMessages, TicketAttachments,
TicketForms), basada en el starter oficial de CodeIgniter 4.

## Configuración

1. Copia `env` a `.env`.
2. Configura la conexión a SQL Server (`database.default.*`, driver `SQLSRV`).
3. Define las credenciales de la API:

   ```
   api.token = <token secreto>
   api.key   = <clave secreta>
   ```

## Autenticación de la API

Todas las rutas bajo `/api/*` pasan por el filtro
`app/Filters/ApiAuthFilter.php` (alias `apiauth`, registrado en
`app/Config/Filters.php` y aplicado al grupo de rutas en
`app/Config/Routes.php`). Cada petición debe incluir:

| Header          | Valor                    |
|-----------------|--------------------------|
| `Authorization` | `Bearer {token}`         |
| `X-Api-Key`     | `{key}`                  |

Si falta algún header, o el token/clave no coinciden con los valores de
`.env`, la API responde `401 Unauthorized` en JSON sin llegar al
controlador.

Ejemplo:

```bash
curl -H "Authorization: Bearer <token>" \
     -H "X-Api-Key: <key>" \
     http://localhost:8080/api/tickets
```

## Recursos disponibles

`companies`, `branches`, `categories`, `subcategories`, `roles`,
`ticket-forms`, `users`, `tickets`, `ticket-attachments`,
`ticket-messages` — cada uno con las rutas REST estándar
(`GET`, `POST`, `PUT`, `DELETE`) generadas por `$routes->resource()`.
Además:

- `GET /api/subcategories/category/{idCategory}`
- `GET /api/tickets/{id}/messages` y `POST /api/tickets/{id}/messages`
- `GET /api/tickets/{id}/attachments` y `POST /api/tickets/{id}/attachments`

## Panel web (dashboard)

Panel para agentes en `/panel` (con `index.php`: `/index.php/panel`).

| Página | Ruta | Contenido |
|---|---|---|
| Inicio | `/panel` | Indicadores, pendientes por categoría, top 5 urgentes, tendencia semanal, actividad reciente |
| Tickets | `/panel/tickets` | Listado con búsqueda, filtros (estado, prioridad, compañía, categoría, agente) y paginación |
| Detalle | `/panel/tickets/{id}` | Chat con el cliente (`TicketMessages`), cambio de estado/prioridad/agente, adjuntos |
| Nueva solicitud | `/panel/tickets/nuevo` | Alta de ticket con matriz impacto × urgencia |
| Reportes | `/panel/reportes` | Volumen por compañía, agente, categoría y prioridad por periodo |
| Catálogo | `/panel/catalogo` | Categorías y subcategorías con su volumen |
| Administración | `/panel/admin` | Alta, edición y baja de compañías, sucursales, categorías y subcategorías |

### Acceso

Se inicia sesión en `/login` con un usuario activo de la tabla `Users`
(`Email` + contraseña verificada contra `PasswordHash` con `password_verify`).
Para asignar o cambiar la contraseña de un usuario:

```bash
php spark user:password karen@empresa.com
```

### Configuración

`app/Config/Tickets.php`:

- `targetHours`: meta de atención por prioridad (la BD no guarda SLA). Un ticket
  pendiente que la supera se marca "fuera de meta".
- `displayTimezone`: zona horaria para mostrar fechas (`CreatedAt` se guarda en UTC).
- `perPage`, `chatPollSeconds`.

### Administración de catálogos

En `/panel/admin` se crean, editan y eliminan compañías, sucursales, categorías
y subcategorías. No se puede eliminar un registro que esté en uso (por ejemplo,
una compañía con sucursales o tickets) y los códigos (`CodCompanies`,
`CodBranches`) no se cambian una vez creados.

Por defecto cualquier usuario con sesión puede administrar. Para limitarlo a
ciertos roles (`Roles.Descripcion`), edita `adminRoles` en `app/Config/Tickets.php`:

```php
public array $adminRoles = ['Administrador'];
```

Los catálogos se definen en `app/Libraries/CatalogAdmin.php`; para agregar otro
basta con añadir su definición (tabla, clave, campos y tablas que lo usan).

### Chat

El chat usa la tabla `TicketMessages`. Si no existe, créala con
`app/Database/sql/TicketMessages.sql`. Las respuestas del panel se guardan con
`SenderType = 'agente'`; el chat consulta mensajes nuevos cada `chatPollSeconds` segundos.

### Archivos

```text
app/Controllers/Panel/   Auth, Dashboard, Tickets, Reports, Catalog, Admin, Lookups
app/Libraries/           TicketRepository (listado/detalle), TicketStats (indicadores), CatalogAdmin (catálogos)
app/Views/panel/         layout, vistas y parciales (gráficos SVG sin librerías)
app/Helpers/panel_helper.php
app/Filters/PanelAuthFilter.php
public/panel/            panel.css, panel.js
```

## What is CodeIgniter?

CodeIgniter is a PHP full-stack web framework that is light, fast, flexible and secure.
More information can be found at the [official site](https://codeigniter.com).

This repository holds a composer-installable app starter.
It has been built from the
[development repository](https://github.com/codeigniter4/CodeIgniter4).

More information about the plans for version 4 can be found in [CodeIgniter 4](https://forum.codeigniter.com/forumdisplay.php?fid=28) on the forums.

You can read the [user guide](https://codeigniter.com/user_guide/)
corresponding to the latest version of the framework.

## Installation & updates

`composer create-project codeigniter4/appstarter` then `composer update` whenever
there is a new release of the framework.

When updating, check the release notes to see if there are any changes you might need to apply
to your `app` folder. The affected files can be copied or merged from
`vendor/codeigniter4/framework/app`.

## Setup

Copy `env` to `.env` and tailor for your app, specifically the baseURL
and any database settings.

## Important Change with index.php

`index.php` is no longer in the root of the project! It has been moved inside the *public* folder,
for better security and separation of components.

This means that you should configure your web server to "point" to your project's *public* folder, and
not to the project root. A better practice would be to configure a virtual host to point there. A poor practice would be to point your web server to the project root and expect to enter *public/...*, as the rest of your logic and the
framework are exposed.

**Please** read the user guide for a better explanation of how CI4 works!

## Repository Management

We use GitHub issues, in our main repository, to track **BUGS** and to track approved **DEVELOPMENT** work packages.
We use our [forum](http://forum.codeigniter.com) to provide SUPPORT and to discuss
FEATURE REQUESTS.

This repository is a "distribution" one, built by our release preparation script.
Problems with it can be raised on our forum, or as issues in the main repository.

## Server Requirements

PHP version 8.2 or higher is required, with the following extensions installed:

- [intl](http://php.net/manual/en/intl.requirements.php)
- [mbstring](http://php.net/manual/en/mbstring.installation.php)

> [!WARNING]
> - The end of life date for PHP 7.4 was November 28, 2022.
> - The end of life date for PHP 8.0 was November 26, 2023.
> - The end of life date for PHP 8.1 was December 31, 2025.
> - If you are still using below PHP 8.2, you should upgrade immediately.
> - The end of life date for PHP 8.2 will be December 31, 2026.

Additionally, make sure that the following extensions are enabled in your PHP:

- json (enabled by default - don't turn it off)
- [mysqlnd](http://php.net/manual/en/mysqlnd.install.php) if you plan to use MySQL
- [libcurl](http://php.net/manual/en/curl.requirements.php) if you plan to use the HTTP\CURLRequest library
