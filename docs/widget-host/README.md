# Integrar el widget en una página anfitriona

El widget **no lleva claves**. Se identifica con un **token firmado** que genera
el *servidor* de tu sitio (nunca el navegador). El servidor de tickets verifica la
firma y usa los datos del token —correo, compañía, sucursal, nombre, teléfono— en
lugar de los que envíe el navegador.

```
 Navegador del usuario                 Tu sitio (servidor)            Servidor de tickets
 ─────────────────────                 ───────────────────            ───────────────────
 1. abre tu página ─────────────────▶  2. ya sabe quién es el usuario
                                       3. firma un token (10 min)
 4. ◀───────────── HTML con data-token
 5. widget ─── X-Widget-Token ──────────────────────────────────────▶ 6. verifica la firma
                                                                       7. usa los datos DEL TOKEN
```

## 1. Configuración (una sola vez)

**En el servidor de tickets** (`.env`):

```ini
# php spark widget:token secret   → imprime uno aleatorio
widget.secret  = 3f9c...64 caracteres hexadecimales...
widget.origins = 'https://intranet.empresa.com,https://portal.empresa.com'
```

- `widget.secret`: mínimo 32 caracteres. Sin él, la API del widget responde 503.
- `widget.origins`: dominios (esquema + host + puerto) desde donde se abre el widget.
  Vacío = ningún dominio externo.

**En tu sitio anfitrión:** guarda el mismo valor como variable de entorno
`WIDGET_SECRET`. Nunca en HTML, JavaScript ni en un repositorio.

## 2. Generar el token (en el servidor de tu sitio)

Ejemplos listos para copiar, sin dependencias:

| Lenguaje | Archivo |
|---|---|
| PHP | [`php/widget_token.php`](php/widget_token.php) (+ [`example-page.php`](php/example-page.php), [`token-endpoint.php`](php/token-endpoint.php)) |
| Node.js | [`node/widget-token.js`](node/widget-token.js) |
| Python | [`python/widget_token.py`](python/widget_token.py) |

Es un JWT estándar HS256, así que también sirve cualquier librería JWT
(`firebase/php-jwt`, `jsonwebtoken`, `System.IdentityModel.Tokens.Jwt`, …).

### Datos que puede llevar

| Campo | Qué es | Efecto en el widget |
|---|---|---|
| `exp` | **Obligatorio.** Vencimiento (Unix). Máximo 1 hora. Recomendado: 10 min | — |
| `iat` | Momento de emisión | — |
| `email` | Correo **verificado** del usuario | Se rellena y no se puede editar. Habilita "Mis tickets" |
| `name` | Nombre | Va al primer mensaje del ticket |
| `phone` | Teléfono | Va al primer mensaje del ticket |
| `company` | `CodCompanies` | Solo esa compañía; se omite el paso 1 si también hay sucursal |
| `branch` | `CodBranches` | Solo esa sucursal |

Todos son opcionales salvo `exp`. Sin `company`/`branch` el usuario las elige; sin
`email` lo escribe (y "Mis tickets" queda vacío porque no hay un correo confiable).

## 3. Poner el widget en la página

```html
<div id="tickets-widget"></div>

<script
    src="https://tu-servidor:8081/widgets/tickets.js"
    data-container="tickets-widget"
    data-token="<?= htmlspecialchars($token, ENT_QUOTES) ?>"
    data-token-url="/token-endpoint.php"
    data-version="1"></script>
```

| Atributo | Para qué |
|---|---|
| `data-token` | Token generado al servir la página |
| `data-token-url` | *(Recomendado)* URL de **tu** sitio que devuelve `{"token": "..."}`. El widget la usa solo si el token venció o está por vencer, así puedes usar tokens de 10 minutos y la página puede quedar abierta horas |
| `data-api` | *(Opcional)* Por defecto `https://tu-servidor:8081/widget` |
| `data-version` | Cambia el número para que el navegador descargue archivos nuevos |

`data-token-url` debe estar protegido por la **sesión de tu sitio**: solo responde a
usuarios autenticados y siempre firma con los datos de esa sesión (ver
[`token-endpoint.php`](php/token-endpoint.php)). Se llama con las cookies de tu dominio.

Desde JavaScript también se puede renovar a mano: `TicketsWidget.setToken(nuevoToken)`.

## 4. Qué NO hacer

- ❌ Firmar el token en JavaScript o poner el secreto en el HTML.
- ❌ Aceptar `email`, `company` o `branch` desde la URL o un formulario para firmarlos:
  se firman **solo** los datos de la sesión del usuario ya autenticado.
- ❌ Tokens de horas o días. Máximo 1 hora (el servidor rechaza más); mejor 10 minutos.
- ❌ Mostrar el widget a quien no inició sesión, si es un sistema interno.

## 5. Refuerzos recomendados en la página anfitriona

```html
<!-- Solo permite cargar scripts y llamar a tu servidor de tickets -->
<meta http-equiv="Content-Security-Policy"
      content="script-src 'self' https://tu-servidor:8081; connect-src 'self' https://tu-servidor:8081;">
```

Si necesitas otros scripts, añádelos a la política. El widget usa Shadow DOM y su
propio CSS, así que no necesita `unsafe-inline` en tu página.

## 6. Nginx del servidor de tickets

Los módulos JS, plantillas HTML y CSS del widget se descargan desde otro dominio
(el de tu sitio), así que `/widgets/` necesita CORS. Son archivos públicos **sin
secretos**, por eso `*` es correcto aquí:

```nginx
location /widgets/ {
    add_header Access-Control-Allow-Origin "*" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Cache-Control "public, max-age=300" always;
}
```

La API (`/widget/*`) **no** usa `*`: responde CORS solo a los dominios de
`widget.origins`.

Recomendado además: certificado válido (Let's Encrypt o el de tu empresa) en lugar
de la IP con certificado autofirmado, y `server_tokens off;`.

## 7. Probar

```bash
# En el servidor de tickets: genera un token de prueba
php spark widget:token sign -e ana@empresa.com -c 03 -b B05 -n "Ana Pérez" -p 0991234567

# Comprueba lo que la API ve en el token
curl -H "X-Widget-Token: <token>" https://tu-servidor:8081/widget/me
```

| Respuesta | Significa |
|---|---|
| `200` con tus datos | Todo bien |
| `401` "El token expiró" | Renueva (o usa `data-token-url`) |
| `401` "Firma de token inválida" | `WIDGET_SECRET` distinto en ambos lados |
| `401` "El token dura más de lo permitido" | `exp` a más de 1 hora |
| `401` "Token emitido en el futuro" | Relojes desincronizados entre servidores (usa NTP) |
| `429` | Demasiadas solicitudes desde esa IP |
| `503` | Falta `widget.secret` en el `.env` del servidor de tickets |
| En el navegador: error de CORS | El dominio de tu página no está en `widget.origins` |

## Qué protege el servidor de tickets

- La API interna (`/api`, con `Authorization` + `X-Api-Key`) **ya no la usa el
  navegador**. Reservada para sistemas servidor a servidor.
- `/widget/*` solo permite: leer compañías, sucursales y categorías; crear un ticket;
  y listar **los tickets del correo del token**.
- El ticket siempre se crea `abierto` y sin agente; la compañía, sucursal y correo del
  token no se pueden cambiar; la sucursal debe pertenecer a la compañía.
- Adjuntos: extensiones permitidas, máx. 5 MB, verificación del contenido, nombre
  aleatorio y guardados **fuera de `public/`** (se descargan desde el panel con sesión).
- Límites: por minuto y por IP a toda la API, y por hora al crear tickets (por IP y por correo).
