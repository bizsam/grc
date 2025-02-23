javascript
const CACHE_NAME = 'classifieds-cache-v1';
const urlsToCache = [
    '/',
    '/index.php',
    '/home.php',
    '/browse_ads.php',
    '/news.php',
    '/pages.php',
    '/contact.php',
    '/categories.php',
    '/listing.php',
    '/messages.php',
    '/dashboard.php',
    '/admin_dashboard.php',
    '/post_ad.php',
    '/login.php',
    '/register.php',
    '/get_location.php',
    '/update_premium.php',
    '/update_listing_premium.php',
    '/pay_for_premium.php',
    '/navbar.html',
    '/footer.html',
    '/manifest.json',
    '/icon-192x192.png',
    '/icon-512x512.png',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
    'https://www.paypal.com/sdk/js?client-id=YOUR_SANDBOX_CLIENT_ID¤cy=USD'
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(urlsToCache))
    );
});

self.addEventListener('fetch', event => {
    event.respondWith(
        caches.match(event.request)
            .then(response => response || fetch(event.request))
    );
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cache => {
                    if (cache !== CACHE_NAME) {
                        return caches.delete(cache);
                    }
                })
            );
        })
    );
});
