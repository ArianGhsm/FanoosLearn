const APP_VERSION = "20260907-195131";
const STATIC_CACHE = "dent1402-static-" + APP_VERSION;
const PAGE_CACHE = "dent1402-pages-" + APP_VERSION;
const MAX_PAGE_CACHE_ENTRIES = 40;
const CACHED_NAVIGATION_GRACE_MS = 400;
const FIRST_NAVIGATION_TIMEOUT_MS = 8000;
const STATIC_ASSET_INSTALL_TIMEOUT_MS = 6000;
const PWA_RUNTIME_PATH = "/assets/site/scripts/pwa.js";
const AUTH_RUNTIME_PATH = "/assets/site/scripts/auth.js";
const CANONICAL_RUNTIME_PATHS = [
  PWA_RUNTIME_PATH,
  AUTH_RUNTIME_PATH,
  "/assets/site/scripts/shell.js",
  "/assets/site/styles/core.css",
  "/assets/site/styles/theme.css"
];

const STATIC_ASSETS = [
  "/offline.html",
  "/manifest.webmanifest?v=" + APP_VERSION,
  "/assets/site/styles/core.css?v=" + APP_VERSION,
  "/assets/site/styles/theme.css?v=" + APP_VERSION,
  "/assets/site/scripts/theme.js?v=" + APP_VERSION,
  PWA_RUNTIME_PATH + "?v=" + APP_VERSION,
  AUTH_RUNTIME_PATH + "?v=" + APP_VERSION,
  "/assets/site/scripts/shell.js?v=" + APP_VERSION,
  "/assets/images/logo.png?v=" + APP_VERSION,
  "/assets/images/favicon.png?v=" + APP_VERSION,
  "/assets/icons/icon-192.png?v=" + APP_VERSION,
  "/assets/icons/icon-512.png?v=" + APP_VERSION,
  "/assets/icons/icon-maskable-192.png?v=" + APP_VERSION,
  "/assets/icons/icon-maskable-512.png?v=" + APP_VERSION,
  "/assets/icons/apple-touch-icon.png?v=" + APP_VERSION,
  "/fonts/AbarHigh-Regular.woff2",
  "/fonts/AbarHigh-SemiBold.woff2",
  "/fonts/AbarHigh-Bold.woff2",
  "/fonts/AbarHigh-ExtraBold.woff2",
  "/fonts/AbarHigh-Black.woff2",
  "/fonts/Sahel-Black.ttf",
  "/fonts/YekanBakh-VF.woff2",
  "/fonts/YekanBakh-VF.woff"
];

const DYNAMIC_BYPASS = [
  "/payment/start/",
  "/app-version.json",
  "/api/admin_api.php",
  "/chat/chat_api.php",
  "/grades/grades_api.php",
  "/api/content_tools_api.php",
  "/api/exams_api.php",
  "/api/forms_api.php",
  "/api/navid_api.php",
  "/api/payments_api.php",
  "/chat/data/",
  "/messages.json",
  "/state.json",
  "/users.csv",
  "/grades.csv"
];

self.addEventListener("install", (event) => {
  event.waitUntil((async () => {
    const staticCache = await caches.open(STATIC_CACHE);
    // A flaky font/image request must not prevent the new worker from
    // installing and leave users on an older, incompatible shell.
    await Promise.all(STATIC_ASSETS.map(async (asset) => {
      try {
        await Promise.race([
          staticCache.add(asset),
          delay(STATIC_ASSET_INSTALL_TIMEOUT_MS)
        ]);
      } catch (_assetError) {
        // Runtime stale-while-revalidate fills any missing optional asset.
      }
    }));
    self.skipWaiting();
  })());
});

self.addEventListener("activate", (event) => {
  event.waitUntil((async () => {
    if (self.registration.navigationPreload) {
      try {
        await self.registration.navigationPreload.enable();
      } catch (_preloadError) {
        // Navigation still works through the ordinary network request.
      }
    }
    const keys = await caches.keys();
    const keepCaches = [STATIC_CACHE, PAGE_CACHE];
    await Promise.all(keys.map((key) => {
      if (keepCaches.indexOf(key) === -1) {
        return caches.delete(key);
      }
      return Promise.resolve();
    }));
    await self.clients.claim();
  })());
});

self.addEventListener("message", (event) => {
  if (!event.data) {
    return;
  }
  if (event.data.type === "SKIP_WAITING") {
    self.skipWaiting();
    return;
  }
  if (event.data.type === "WARM_PAGE" && event.data.url) {
    event.waitUntil(warmPage(event.data.url));
  }
});

self.addEventListener("push", (event) => {
  let data = {};
  if (event.data) {
    try {
      data = event.data.json();
    } catch (_jsonError) {
      try {
        data = { title: "اعلان جدید", body: event.data.text() };
      } catch (_textError) {
        data = {};
      }
    }
  }

  const title = data && data.title ? String(data.title) : "اعلان جدید";
  const url = data && data.url ? String(data.url) : "/account/#notifications";
  const tag = data && data.tag ? String(data.tag) : undefined;
  const options = {
    body: data && data.body ? String(data.body) : "",
    dir: "rtl",
    lang: "fa",
    tag: tag,
    renotify: !!tag,
    icon: "/assets/icons/icon-192.png?v=" + APP_VERSION,
    badge: "/assets/icons/icon-192.png?v=" + APP_VERSION,
    data: { url: url }
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener("notificationclick", (event) => {
  event.notification.close();
  const targetUrl = event.notification.data && event.notification.data.url
    ? event.notification.data.url
    : "/account/#notifications";

  event.waitUntil((async () => {
    const windowClients = await self.clients.matchAll({ type: "window", includeUncontrolled: true });
    for (const client of windowClients) {
      try {
        const clientUrl = new URL(client.url);
        if (clientUrl.origin === self.location.origin && "focus" in client) {
          await client.focus();
          if ("navigate" in client) {
            try {
              await client.navigate(targetUrl);
            } catch (_navigateError) {
              // Navigation can fail on some browsers; focus is enough.
            }
          }
          return;
        }
      } catch (_clientError) {
        // Ignore malformed client URLs.
      }
    }
    if (self.clients.openWindow) {
      await self.clients.openWindow(targetUrl);
    }
  })());
});

function shouldBypass(url, request) {
  if (request.method !== "GET") {
    return true;
  }

  if (url.origin !== self.location.origin) {
    return false;
  }

  return DYNAMIC_BYPASS.some((segment) => url.pathname.includes(segment));
}

async function staleWhileRevalidate(request) {
  const cache = await caches.open(STATIC_CACHE);
  const cached = await cache.match(request);
  const networkPromise = fetch(request).then((response) => {
    if (response && response.ok) {
      cache.put(request, response.clone());
    }
    return response;
  }).catch(() => cached);
  return cached || networkPromise;
}

// Legacy HTML pages carry old query tokens for shared assets. Always answer
// them with the runtime bundled by this worker so an immutable browser cache
// cannot revive an obsolete update banner or navigation implementation.
async function currentCanonicalRuntime(request, pathname) {
  const cache = await caches.open(STATIC_CACHE);
  const canonicalUrl = pathname + "?v=" + APP_VERSION;
  const canonical = await cache.match(canonicalUrl);
  if (canonical) {
    return canonical;
  }

  const response = await fetch(request, { cache: "no-store" });
  if (response && response.ok) {
    await cache.put(canonicalUrl, response.clone());
  }
  return response;
}

async function cacheFirstAsset(request) {
  const cache = await caches.open(STATIC_CACHE);
  const cached = await cache.match(request);
  if (cached) {
    return cached;
  }

  try {
    const response = await fetch(request);
    if (response && response.ok) {
      await cache.put(request, response.clone());
    }
    return response;
  } catch (error) {
    throw error;
  }
}

async function trimPageCache(cache) {
  const requests = await cache.keys();
  if (requests.length <= MAX_PAGE_CACHE_ENTRIES) {
    return;
  }
  const removeCount = requests.length - MAX_PAGE_CACHE_ENTRIES;
  for (let i = 0; i < removeCount; i++) {
    await cache.delete(requests[i]);
  }
}

async function warmPage(rawUrl) {
  try {
    const url = new URL(rawUrl, self.location.origin);
    if (url.origin !== self.location.origin) {
      return;
    }

    const request = new Request(url.href, {
      method: "GET",
      credentials: "same-origin",
      headers: { Accept: "text/html" }
    });
    if (shouldBypass(url, request)) {
      return;
    }

    const cache = await caches.open(PAGE_CACHE);
    if (await cache.match(request)) {
      return;
    }

    const response = await fetch(request, { cache: "no-store" });
    if (response && response.ok && response.type === "basic") {
      await cache.put(request, response.clone());
      await trimPageCache(cache);
    }
  } catch (_warmError) {
    // Warming is opportunistic; ordinary navigation remains the fallback.
  }
}

// Navigation starts on the network. A previously healthy page can appear after
// a short grace period on a stalled connection while revalidation continues;
// protected/API responses remain excluded through DYNAMIC_BYPASS.
function delay(ms, value) {
  return new Promise((resolve) => setTimeout(() => resolve(value), ms));
}

async function networkFirstPage(request, preloadResponse, event) {
  const cache = await caches.open(PAGE_CACHE);
  const cached = await cache.match(request);
  const networkTask = (async () => {
    const preloaded = preloadResponse ? await preloadResponse : null;
    const response = preloaded || await fetch(request, { cache: "no-store" });
    if (cached && (!response || !response.ok)) {
      return cached;
    }
    if (response && response.ok && response.type === "basic") {
      await cache.put(request, response.clone());
      await trimPageCache(cache);
    }
    return response;
  })();

  if (event) {
    event.waitUntil(networkTask.catch(() => undefined));
  }

  try {
    if (cached) {
      // Fast networks still get the fresh document. On a stalled connection,
      // show the last healthy page and let the network refresh finish behind it.
      return await Promise.race([
        networkTask,
        delay(CACHED_NAVIGATION_GRACE_MS, cached)
      ]);
    }
    return await Promise.race([
      networkTask,
      delay(FIRST_NAVIGATION_TIMEOUT_MS, null)
    ]) || (await caches.match("/offline.html"));
  } catch (_networkError) {
    return cached || caches.match("/offline.html");
  }
}

self.addEventListener("fetch", (event) => {
  const request = event.request;
  const url = new URL(request.url);

  if (shouldBypass(url, request)) {
    return;
  }

  if (request.mode === "navigate" && url.origin === self.location.origin) {
    event.respondWith(networkFirstPage(request, event.preloadResponse, event));
    return;
  }

  if (url.origin === self.location.origin && CANONICAL_RUNTIME_PATHS.includes(url.pathname)) {
    event.respondWith(currentCanonicalRuntime(request, url.pathname));
    return;
  }

  if (url.origin === self.location.origin &&
      (request.destination === "style" ||
       request.destination === "script")) {
    // The deploy stamp rotates STATIC_CACHE and skipWaiting activates it
    // immediately. Serve the current cached asset without blocking the UI,
    // while refreshing it in the background for the next interaction.
    event.respondWith(staleWhileRevalidate(request));
    return;
  }

  if (url.origin === self.location.origin &&
      (request.destination === "font" ||
       request.destination === "image")) {
    event.respondWith(staleWhileRevalidate(request));
  }
});
