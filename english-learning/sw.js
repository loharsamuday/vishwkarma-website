const CACHE_NAME = 'study-dashboard-cache-v1';

// Install event
self.addEventListener('install', event => {
  self.skipWaiting();
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
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
  self.clients.claim();
});

// Fetch event - Network first, falling back to cache
self.addEventListener('fetch', event => {
  if (event.request.method !== 'GET') return;
  
  event.respondWith(
    fetch(event.request)
      .then(response => {
        // Check if we received a valid response
        if (!response || response.status !== 200) {
          return response;
        }
        
        // Only cache http/https requests
        if(event.request.url.startsWith('http')) {
            let responseToCache = response.clone();
            caches.open(CACHE_NAME).then(cache => {
                cache.put(event.request, responseToCache);
            });
        }
          
        return response;
      })
      .catch(() => {
        // If network fails (offline), return from cache
        return caches.match(event.request);
      })
  );
});

// --- BACKGROUND SYNC LOGIC ---

function openDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('StudyAppDB', 1);
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

self.addEventListener('sync', event => {
    if (event.tag === 'sync-offline-actions') {
        event.waitUntil(syncOfflineActions());
    }
});

async function syncOfflineActions() {
    try {
        const db = await openDB();
        const tx = db.transaction('offlineActions', 'readonly');
        const store = tx.objectStore('offlineActions');
        
        const request = store.getAll();
        
        request.onsuccess = async () => {
            const actions = request.result;
            for (let action of actions) {
                const bodyParams = new URLSearchParams();
                for (const key in action.data) {
                    bodyParams.append(key, action.data[key]);
                }

                try {
                    const response = await fetch(action.url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: bodyParams.toString()
                    });
                    
                    if (response.ok || response.status === 302) {
                        // Delete successfully synced action
                        const delTx = db.transaction('offlineActions', 'readwrite');
                        delTx.objectStore('offlineActions').delete(action.id);
                    }
                } catch(err) {
                    console.error('Failed to sync action:', err);
                }
            }
            
            // Trigger a push notification to tell user sync is complete
            if (actions.length > 0 && self.registration && self.registration.showNotification) {
                 self.registration.showNotification("Offline Sync Complete", {
                     body: actions.length + " saved actions were just synced with the server!",
                     icon: 'assets/images/logo.png'
                 });
            }
        };
    } catch (e) {
        console.error('IndexedDB Error in SW:', e);
    }
}
