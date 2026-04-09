/**
 * POS Offline — IndexedDB + Sync
 * Guarda vendas e produtos localmente quando offline.
 * Sincroniza automaticamente quando online.
 */
import { ref, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

const DB_NAME    = 'beconnect_pos'
const DB_VERSION = 2  // incrementado para adicionar pending_products
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
      // Produtos criados no POS offline aguardando sync
      if (!d.objectStoreNames.contains('pending_products')) {
        d.createObjectStore('pending_products', { keyPath: 'local_id' })
      }
      // Permissões do funcionário para uso offline
      if (!d.objectStoreNames.contains('pos_session')) {
        d.createObjectStore('pos_session', { keyPath: 'key' })
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

// ── Produtos criados offline ───────────────────────────────────────────────
export async function savePendingProduct(product) {
  await openDB()
  return new Promise((resolve, reject) => {
    const req = txStore('pending_products', 'readwrite').put(product)
    req.onsuccess = () => resolve()
    req.onerror   = (e) => reject(e.target.error)
  })
}

export async function getPendingProducts() {
  await openDB()
  return new Promise((resolve, reject) => {
    const req = txStore('pending_products').getAll()
    req.onsuccess = (e) => resolve(e.target.result)
    req.onerror   = (e) => reject(e.target.error)
  })
}

export async function deletePendingProduct(local_id) {
  await openDB()
  return new Promise((resolve, reject) => {
    const req = txStore('pending_products', 'readwrite').delete(local_id)
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

// ── Sessão POS (permissões + preferências offline) ─────────────────────────
export async function savePosSession(key, value) {
  await openDB()
  return new Promise((resolve, reject) => {
    const req = txStore('pos_session', 'readwrite').put({ key, value })
    req.onsuccess = () => resolve()
    req.onerror   = (e) => reject(e.target.error)
  })
}

export async function getPosSession(key) {
  await openDB()
  return new Promise((resolve, reject) => {
    const req = txStore('pos_session').get(key)
    req.onsuccess = (e) => resolve(e.target.result?.value ?? null)
    req.onerror   = (e) => reject(e.target.error)
  })
}

// ── Sincronização de produtos pendentes ───────────────────────────────────
export async function syncPendingProducts() {
  const pending = await getPendingProducts()
  if (!pending.length) return { created: 0 }

  const { data } = await axios.post('/pos/sync-products', { products: pending })

  // Actualizar a cache local com os IDs do servidor
  if (data.id_map) {
    const cached = await getCachedProducts()
    const store = (await openDB()).transaction('products_cache', 'readwrite').objectStore('products_cache')
    for (const prod of pending) {
      const serverId = data.id_map[prod.local_id]
      if (serverId) {
        // Remover a versão local (com ID negativo) e adicionar com ID real
        const localProd = cached.find(p => p.local_id === prod.local_id)
        if (localProd) {
          store.delete(localProd.id)
          store.put({ ...localProd, id: serverId, local_id: undefined })
        }
      }
    }
  }

  for (const prod of pending) {
    await deletePendingProduct(prod.local_id)
  }
  return data
}

// ── Sincronização de vendas pendentes ─────────────────────────────────────
export async function syncPendingSales() {
  const pending = await getPendingSales()
  if (!pending.length) return { synced: 0 }

  const { data } = await axios.post('/pos/sync', { sales: pending })

  for (const sale of pending) {
    await deletePendingSale(sale.local_id)
  }
  return data
}

// ── Composable Vue ─────────────────────────────────────────────────────────
export function useOfflinePos() {
  const isOnline           = ref(navigator.onLine)
  const pendingCount       = ref(0)
  const pendingProductCount= ref(0)
  const syncing            = ref(false)
  const syncMessage        = ref('')

  async function refreshPendingCount() {
    const [sales, products] = await Promise.all([getPendingSales(), getPendingProducts()])
    pendingCount.value        = sales.length
    pendingProductCount.value = products.length
  }

  async function trySyncNow() {
    if (!isOnline.value || syncing.value) return
    const [sales, products] = await Promise.all([getPendingSales(), getPendingProducts()])
    if (!sales.length && !products.length) return

    syncing.value = true
    syncMessage.value = ''
    try {
      let msg = ''
      if (products.length) {
        const r = await syncPendingProducts()
        if (r.created > 0) msg += `${r.created} produto(s) criado(s). `
      }
      if (sales.length) {
        const r = await syncPendingSales()
        if (r.synced > 0) msg += `${r.synced} venda(s) sincronizada(s).`
      }
      syncMessage.value = msg ? `✅ ${msg.trim()}` : ''
      await refreshPendingCount()
    } catch {
      syncMessage.value = '⚠️ Erro a sincronizar. Tentará de novo mais tarde.'
    } finally {
      syncing.value = false
      setTimeout(() => syncMessage.value = '', 5000)
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

  return {
    isOnline, pendingCount, pendingProductCount,
    syncing, syncMessage, trySyncNow, refreshPendingCount,
  }
}
