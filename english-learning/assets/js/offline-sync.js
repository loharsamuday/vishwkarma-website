const dbName = 'StudyAppDB';
const storeName = 'offlineActions';

function openDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(dbName, 1);
        request.onupgradeneeded = (e) => {
            const db = e.target.result;
            if (!db.objectStoreNames.contains(storeName)) {
                db.createObjectStore(storeName, { keyPath: 'id', autoIncrement: true });
            }
        };
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

async function saveOfflineAction(url, formDataObj) {
    const db = await openDB();
    const tx = db.transaction(storeName, 'readwrite');
    const store = tx.objectStore(storeName);
    store.add({ url, data: formDataObj, timestamp: Date.now() });
    return tx.complete;
}

// Intercept all forms if offline
document.addEventListener('submit', async (e) => {
    if (!navigator.onLine) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        
        // Convert formData to simple object (handles multiple inputs)
        const dataObj = {};
        formData.forEach((value, key) => { dataObj[key] = value; });
        
        // Save action
        await saveOfflineAction(form.action || window.location.href, dataObj);
        
        // Register sync
        if ('serviceWorker' in navigator && 'SyncManager' in window) {
            try {
                const sw = await navigator.serviceWorker.ready;
                await sw.sync.register('sync-offline-actions');
                console.log('Background sync registered');
            } catch (err) {
                console.error('Sync registration failed:', err);
            }
        }
        
        alert("You are offline. Your action has been saved locally and will sync automatically when the internet returns.");
        
        // Close modals if any
        const modals = document.querySelectorAll('.modal.show');
        modals.forEach(m => {
            const bootstrapModal = bootstrap.Modal.getInstance(m);
            if(bootstrapModal) bootstrapModal.hide();
        });
    }
});
