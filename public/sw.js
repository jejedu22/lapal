/*
 * Service worker de l'application (PWA).
 *
 * - Pages : toujours demandées au serveur (les commandes doivent être à jour) ;
 *   hors connexion, on affiche la page offline.html.
 * - Fichiers statiques (CSS, JS, polices, images) : servis depuis le cache puis
 *   rafraîchis en arrière-plan.
 *
 * Changer CACHE_VERSION pour forcer le renouvellement du cache après une mise à jour.
 */
const CACHE_VERSION = 'lapal-v1';
const OFFLINE_URL = 'offline.html';

const PRECACHE = [
    OFFLINE_URL,
    'icons/icon-192.png',
    'icons/icon-512.png',
    'icons/favicon-32.png',
    'plugins/fontawesome/css/all.min.css',
    'plugins/fontawesome/webfonts/fa-solid-900.woff2',
    'fonts/source-sans-pro.css',
    'fonts/source-sans-pro/source-sans-pro-latin-400-normal.woff2',
    'dist/css/adminlte.min.css',
    'dist/css/style.css',
    'plugins/jquery/jquery.min.js',
    'plugins/bootstrap/js/bootstrap.bundle.min.js',
    'dist/js/adminlte.min.js',
];

const STATIQUE = /\/(dist|plugins|fonts|icons|uploads\/logo)\//;

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_VERSION)
            .then((cache) => cache.addAll(PRECACHE.map((url) => new Request(new URL(url, self.registration.scope), { cache: 'reload' }))))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((cles) => Promise.all(cles.filter((cle) => cle !== CACHE_VERSION).map((cle) => caches.delete(cle))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const requete = event.request;
    if (requete.method !== 'GET') {
        return;
    }
    const url = new URL(requete.url);
    if (url.origin !== self.location.origin) {
        return;
    }

    if (requete.mode === 'navigate') {
        event.respondWith(
            fetch(requete).catch(() => caches.match(new URL(OFFLINE_URL, self.registration.scope).href))
        );
        return;
    }

    if (STATIQUE.test(url.pathname)) {
        event.respondWith(
            caches.open(CACHE_VERSION).then((cache) =>
                cache.match(requete).then((enCache) => {
                    const reseau = fetch(requete)
                        .then((reponse) => {
                            if (reponse.ok) {
                                cache.put(requete, reponse.clone());
                            }
                            return reponse;
                        })
                        .catch(() => enCache);
                    if (enCache) {
                        event.waitUntil(reseau);
                    }
                    return enCache || reseau;
                })
            )
        );
    }
});
