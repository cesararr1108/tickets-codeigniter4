/* Service worker de la PWA "Mis tickets": deja la página abrir sin conexión.
 * Solo guarda el cascarón (HTML/CSS/JS propios); la API y los demás dominios
 * siempre van a la red. Sube VERSION al cambiar archivos de la PWA. */
const VERSION = 'mis-tickets-v2';
const SHELL = ['./', 'index.html', 'css/pwa.css', 'js/pwa.js', 'js/api.js', 'js/my-tickets.js',
    'js/companies.js', 'js/branches.js', 'manifest.webmanifest', '../icons/icon-192.png'];

self.addEventListener('install', event => {
    event.waitUntil(caches.open(VERSION).then(cache => cache.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys()
            .then(keys => Promise.all(keys.filter(k => k !== VERSION).map(k => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', event => {
    const req = event.request;
    const url = new URL(req.url);

    // Solo GET del mismo origen y dentro de /widgets/ (el widget y la API no se tocan).
    if (req.method !== 'GET' || url.origin !== location.origin || !url.pathname.startsWith(new URL('./', self.location).pathname)) {
        return;
    }

    // Red primero (siempre lo último); si no hay conexión, la copia guardada.
    event.respondWith(
        fetch(req)
            .then(res => {
                if (res.ok) {
                    const copy = res.clone();
                    caches.open(VERSION).then(cache => cache.put(req, copy));
                }
                return res;
            })
            .catch(() => caches.match(req).then(hit => hit || caches.match('index.html')))
    );
});
