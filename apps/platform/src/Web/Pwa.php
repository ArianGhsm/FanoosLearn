<?php

declare(strict_types=1);

namespace Fanoos\Platform\Web;

/**
 * نصب فانوس: the web app manifest and the service worker that make the site
 * installable as an app on a phone's home screen.
 *
 * Both are served by the front controller at extension-less paths
 * (/pwa/manifest, /pwa/sw) so no web-server rule for .js or .json files can
 * catch them. The worker is deliberately small: versioned /assets/ files
 * (immutable, see AssetVersioner) are cached on first use, a page request
 * that cannot reach the server gets a short offline page, and nothing else
 * -- never the API, never a page -- is stored, so no student data and no
 * question ever sits in the cache.
 */
final class Pwa
{
    public const MANIFEST_PATH = '/pwa/manifest';
    public const WORKER_PATH = '/pwa/sw';
    private const CACHE = 'fanoos-assets-v1';

    /** @return array{status:int,headers:list<string>,body:string} */
    public static function manifest(): array
    {
        $manifest = [
            'name' => 'فانوس — آمادگی آزمون دستیاری دندانپزشکی',
            'short_name' => 'فانوس',
            'description' => 'بانک سؤال آزمون دستیاری دندانپزشکی با پاسخ تشریحی و رفرنس.',
            'lang' => 'fa',
            'dir' => 'rtl',
            'start_url' => '/app',
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#151412',
            'theme_color' => '#151412',
            'icons' => [
                ['src' => '/assets/web/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/assets/web/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/assets/web/icons/icon-maskable-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable'],
                ['src' => '/assets/web/icons/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => [
                ['name' => 'بانک سؤال', 'url' => '/app/bank'],
                ['name' => 'آزمون‌ها', 'url' => '/app/exams'],
                ['name' => 'پیشرفت من', 'url' => '/app/progress'],
            ],
        ];

        return [
            'status' => 200,
            'headers' => ['Content-Type: application/manifest+json; charset=utf-8'],
            'body' => json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ];
    }

    /** @return array{status:int,headers:list<string>,body:string} */
    public static function worker(): array
    {
        $cache = self::CACHE;
        $offline = json_encode(
            '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>فانوس</title><body style="font-family:system-ui,sans-serif;background:#151412;color:#f2efe8;display:grid;place-items:center;min-height:100vh;margin:0;text-align:center">'
            . '<div><p style="font-size:1.25rem">اتصال اینترنت برقرار نیست.</p><p style="opacity:.7">وقتی وصل شدی، صفحه را دوباره باز کن.</p></div></body></html>',
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
        $body = <<<JS
const CACHE = '{$cache}';
const OFFLINE = {$offline};
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        for (const key of await caches.keys()) if (key !== CACHE) await caches.delete(key);
        await self.clients.claim();
    })());
});
self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;
    if (url.pathname.startsWith('/assets/') && url.searchParams.has('v')) {
        event.respondWith((async () => {
            const cache = await caches.open(CACHE);
            const hit = await cache.match(request);
            if (hit) return hit;
            const response = await fetch(request);
            if (response.ok) cache.put(request, response.clone());
            return response;
        })());
        return;
    }
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => new Response(OFFLINE, { headers: { 'Content-Type': 'text/html; charset=utf-8' } })));
    }
});
JS;

        return [
            'status' => 200,
            'headers' => ['Content-Type: application/javascript; charset=utf-8', 'Service-Worker-Allowed: /'],
            'body' => $body,
        ];
    }
}
