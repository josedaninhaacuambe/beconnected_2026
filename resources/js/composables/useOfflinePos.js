/**
 * POS Offline — IndexedDB + Sync
 * Guarda vendas localmente quando offline e sincroniza quando online.
 */
import { ref, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

const DB_NAME    = 'beconnect_pos'
const DB_VERSION = 1
let db = null

// ── Abrir / inicializar IndexedDB ──────────────────────────────────────────
async function openDB() {
  if (db) return db
  return new Promise((resolve, reject) => {
    const req = indexedDB.open(DB_NAME, DB_VERSION)
    req.onupgradeneeded = (e) => {
      const d = e.target.result
      if (!d.objectStoreNames.contains('pending_sales')) {
        d.createObjectStore('pending_sales', { keyPath: 'local_id' })
      }
      if (!d.objectStoreNames.contains('products_cache')) {
        const s = d.createObjectStore('products_cache', { keyPath: 'id' })
        s.createIndex('store_id', 'store_id')
      }
    }
    req.onsuccess  = (e) => { db = e.target.result; resolve(db) }
    req.onerror    = (e) => reject(e.target.error)
  })
}

function txStore(storeName, mode = 'readonly') {
  return db.transaction(storeName, mode).objectStore(storeName)
}

// ── Vendas pendentes (offline) ─────────────────────────────────────────────
export async function savePendingSale(sale) {
  await openDB()
  return new Promise((resolve, reject) => {
    const req = txStore('pending_sales', 'readwrite').put(sale)
    req.onsuccess = () => resolve()
    req.onerror   = (e) => reject(e.target.error)
  })
}

export async function getPendingSales() {
  await openDB()
  return new Promise((resolve, reject) => {
    const req = txStore('pending_sales').getAll()
    req.onsuccess = (e) => resolve(e.target.result)
    req.onerror   = (e) => reject(e.target.error)
  })
}

export async function deletePendingSale(local_id) {
  await openDB()
  return new Promise((resolve, reject) => {
    const req = txStore('pending_sales', 'readwrite').delete(local_id)
    req.onsuccess = () => resolve()
    req.onerror   = (e) => reject(e.target.error)
  })
}

// ── Cache de produtos ──────────────────────────────────────────────────────
export async function cacheProducts(products) {
  await openDB()
  const store = txStore('products_cache', 'readwrite')
  products.forEach(p => store.put(p))
}

export async function getCachedProducts() {
  await openDB()
  return new Promise((resolve, reject) => {
    const req = txStore('products_cache').getAll()
    req.onsuccess = (e) => resolve(e.target.result)
    req.onerror   = (e) => reject(e.target.error)
  })
}

// ── Sincronização ──────────────────────────────────────────────────────────
export async function syncPendingSales() {
  const pending = await getPendingSales()
  if (!pending.length) return { synced: 0 }

  const { data } = await axios.post('/api/pos/sync', { sales: pending })

  // Apagar da fila local as que foram sincronizadas com sucesso
  for (const sale of pending) {
    await deletePendingSale(sale.local_id)
  }
  return data
}

// ── Composable Vue ─────────────────────────────────────────────────────────
export function useOfflinePos() {
  const isOnline      = ref(navigator.onLine)
  const pendingCount  = ref(0)
  const syncing       = ref(false)
  const syncMessage   = ref('')

  async function refreshPendingCount() {
    const pending = await getPendingSales()
    pendingCount.value = pending.length
  }

  async function trySyncNow() {
    if (!isOnline.value || syncing.value) return
    const pending = await getPendingSales()
    if (!pending.length) return

    syncing.value = true
    syncMessage.value = ''
    try {
      const result = await syncPendingSales()
      syncMessage.value = `✅ ${result.synced} venda(s) sincronizada(s).`
      await refreshPendingCount()
    } catch {
      syncMessage.value = '⚠️ Erro a sincronizar. Tentará de novo mais tarde.'
    } finally {
      syncing.value = false
      setTimeout(() => syncMessage.value = '', 4000)
    }
  }

  function onOnline()  { isOnline.value = true;  trySyncNow() }
  function onOffline() { isOnline.value = false }

  onMounted(async () => {
    window.addEventListener('online',  onOnline)
    window.addEventListener('offline', onOffline)
    await openDB()
    await refreshPendingCount()
    if (isOnline.value) trySyncNow()
  })

  onUnmounted(() => {
    window.removeEventListener('online',  onOnline)
    window.removeEventListener('offline', onOffline)
  })

  return { isOnline, pendingCount, syncing, syncMessage, trySyncNow, refreshPendingCount }
}
