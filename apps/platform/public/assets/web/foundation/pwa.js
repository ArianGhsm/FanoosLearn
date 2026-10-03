/*
 * Registers the service worker (Web\Pwa) so the site can be installed as an
 * app. Loaded on every page; a browser without service workers, or one that
 * refuses (a private window), simply keeps using the site as a site.
 */
if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/pwa/sw', { scope: '/' }).catch(() => {});
    });
}
