const CACHE_NAME = 'seirokan-pwa-v3';
const urlsToCache = [
    '/assets/images/logo.png'
];

self.addEventListener('install', event => {
    self.skipWaiting();
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                // Jangan paksa precache URL dinamis, hanya logo
                return cache.addAll(urlsToCache).catch(err => console.log('Cache error:', err));
            })
    );
});

self.addEventListener('activate', event => {
    event.waitUntil(self.clients.claim());
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== CACHE_NAME) {
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
});

self.addEventListener('fetch', event => {
    // Abaikan semua request kecuali GET (misal POST/PUT diabaikan)
    if (event.request.method !== 'GET') {
        return; 
    }

    // Hindari intercept untuk request chrome-extension:// atau yang bukan http/https
    if (!event.request.url.startsWith('http')) {
        return;
    }

    event.respondWith(
        fetch(event.request)
            .then(response => {
                return response;
            })
            .catch(() => {
                // Kembalikan versi cache jika tersedia (fallback offline)
                return caches.match(event.request);
            })
    );
});
