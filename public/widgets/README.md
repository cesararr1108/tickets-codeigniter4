# Tickets Widget

Widget de soporte para insertar en cualquier página.

## Estructura

```text
widget/
├── tickets.js
├── README.md
└── js/
    ├── api.js
    ├── companies.js
    ├── branches.js
    └── tickets.js
```

## Instalación

Copiar esta carpeta dentro de:

```text
/var/www/tickets-codeigniter4/public/widget/
```

Debe quedar:

```text
/var/www/tickets-codeigniter4/public/widget/tickets.js
/var/www/tickets-codeigniter4/public/widget/js/api.js
/var/www/tickets-codeigniter4/public/widget/js/companies.js
/var/www/tickets-codeigniter4/public/widget/js/branches.js
/var/www/tickets-codeigniter4/public/widget/js/tickets.js
```

## Uso desde otra página

```html
<div id="tickets-widget"></div>

<script
    src="https://200.122.206.204:8081/widget/tickets.js"
    data-container="tickets-widget"
    data-api="https://200.122.206.204:8081/api">
</script>
```

## Endpoints utilizados actualmente

```text
GET  /api/companies
GET  /api/branches/{companyId}
POST /api/tickets
GET  /api/tickets
```

La parte de branches está preparada para cambiar fácilmente si el backend usa:

```text
/branches?company=1
```

en lugar de:

```text
/branches/1
```

## Importante

El archivo `tickets.js` carga los módulos con `import()` desde:

```text
https://200.122.206.204:8081/widget/js/
```

Por eso los archivos deben estar disponibles públicamente mediante Nginx/CodeIgniter.

Si el navegador muestra errores de CORS, hay que configurar CORS en CodeIgniter para permitir el dominio de la página que incrusta el widget.

Si el certificado HTTPS no es válido para la IP pública, el navegador también puede bloquear las peticiones. Lo recomendable para producción es utilizar un dominio con certificado TLS válido.
