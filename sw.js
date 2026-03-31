const CACHE_NAME = 'maids-ng-v1';
const ASSETS = [
    '/',
    '/index.html',
    '/login.html',
    '/employer.html',
    '/agency.html',
    '/admin.html',
    '/js/api-service.js',
    '/img/logo.png'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(ASSETS))
    );
});

self.addEventListener('fetch', (event) => {
    event.respondWith(
        caches.match(event.request)
            .then((response) => response || fetch(event.request))
    );
});
