/**
 * POS Offline — IndexedDB + Sync
 * Guarda vendas, produtos, movimentos de stock e operações de equipa
 * localmente quando offline. Sincroniza automaticamente quando online.
 *
 * v4: adiciona stores para cache de categorias, relatórios, fecho de
 *     caixa, histórico de stock, lista de funcionários e operações de
 *     equipa pendentes (offline).
 * v5: manage_products_cache — cache da listagem de gestão de produtos
 *     (/pos/products/manage) isolada por loja.
 */
import { ref, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

const DB_NAME    = 'beconnect_pos'
const DB_VERSION = 5
let db = null

// ── Abrir / inicializar IndexedDB ──────────────────────────────────────────
async function openDB() {
  if (db) return db
  return new Promise((resolve, reject) => {
    const req = indexedDB.open(DB_NAME, DB_VERSION)
    req.onupgradeneeded = (e) => {
      const d = e.target.result

      // ── v1 ───────────────────────────────────────────────────────────────
      if (!d.objectStoreNames.contains('pending_sales')) {
        d.createObjectStore('pending_sales', { keyPath: 'local_id' })
      }
      if (!d.objectStoreNames.contains('products_cache')) {
        const s = d.createObjectStore('products_cache', { keyPath: 'id' })
        s.createIndex('store_id', 'store_id')
      }
      if (!d.objectStoreNames.contains('pending_products')) {
        d.createObjectStore('pending_products', { keyPath: 'local_id' })
      }
      if (!d.objectStoreNames.contains('pos_session')) {
        d.createObjectStore('pos_session', { keyPath: 'key' })
      }

      // ── v3 ───────────────────────────────────────────────────────────────
      if (!d.objectStoreNames.contains('pending_stock_movements')) {
        d.createObjectStore('pending_stock_movements', { keyPath: 'local_id' })
      }

      // ── v4 ───────────────────────────────────────────────────────────────
      // Cache de categorias (keyPath: 'key' — valor único 'all')
      if (!d.objectStoreNames.contains('categories_cache')) {
        d.createObjectStore('categories_cache', { keyPath: 'key' })
      }
      // Cache de fecho de caixa por data (keyPath: 'key' — ex: '2026-04-14')
      if (!d.objectStoreNames.contains('daily_cash_cache')) {
        d.createObjectStore('daily_cash_cache', { keyPath: 'key' })
      }
      // Cache de relatórios por range (keyPath: 'key' — ex: '2026-04-01|2026-04-14')
      if (!d.objectStoreNames.contains('reports_cache')) {
        d.createObjectStore('reports_cache', { keyPath: 'key' })
      }
      // Cache do histórico de movimentos de stock
      if (!d.objectStoreNames.contains('stock_history_cache')) {
        d.createObjectStore('stock_history_cache', { keyPath: 'key' })
      }
      // Cache da lista de funcionários
      if (!d.objectStoreNames.contains('employees_cache')) {
        d.createObjectStore('employees_cache', { keyPath: 'key' })
      }
      // Operações de equipa feitas offline (add, edit, remove, reset pw)
      if (!d.objectStoreNames.contains('pending_employee_ops')) {
        d.createObjectStore('pending_employee_ops', { keyPath: 'local_id' })
      }

      // ── v5 ───────────────────────────────────────────────────────────────
      // Cache da gestão de produtos por loja (keyPath: 'key' — ex: 'store_1')
      if (!d.objectStoreNames.contains('manage_products_cache')) {
        d.createObjectStore('manage_products_cache', { keyPath: 'key' })
      }
    }
    req.onsuccess  = (e) => {
      db = e.target.result
      // Fechar a ligação se outra aba pedir upgrade — evita bloquear futuras versões
      db.onversionchange = () => { db.close(); db = null }
      resolve(db)
    }
    req.onerror   = (e) => reject(e.target.error)
    // Outra aba tem a DB aberta em versão mais antiga — fechar e resolver sem DB
    req.onblocked = () => {
      console.warn('IndexedDB bloqueada por outra aba — a continuar sem cache local')
      resolve(null)
    }
  })
}

// Abre DB tolerante: retorna null se falhar, em vez de lançar excepção
async function openDBSafe() {
  try { return await openDB() } catch { return null }
}

function txStore(storeName, mode = 'readonly') {
  if (!db) throw new Error('IndexedDB não disponível')
  return db.transaction(storeName, mode).objectStore(storeName)
}

// ── Helper genérico: guardar + ler um registo por chave ────────────────────
async function cacheSet(storeName, key, value) {
  const d = await openDBSafe()
  if (!d) return
  return new Promise((resolve, reject) => {
    const req = d.transaction(storeName, 'readwrite').objectStore(storeName).put({ key, value, saved_at: Date.now() })
    req.onsuccess = () => resolve()
    req.onerror   = (e) => reject(e.target.error)
  })
}

async function cacheGet(storeName, key) {
  const d = await openDBSafe()
  if (!d) return null
  return new Promise((resolve, reject) => {
    const req = d.transaction(storeName).objectStore(storeName).get(key)
    req.onsuccess = (e) => resolve(e.target.result ?? null)   // { key, value, saved_at }
    req.onerror   = (e) => reject(e.target.error)
  })
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

// ── Movimentos de stock offline ────────────────────────────────────────────
export async function savePendingMovement(movement) {
  await openDB()
  return new Promise((resolve, reject) => {
    const req = txStore('pending_stock_movements', 'readwrite').put(movement)
    req.onsuccess = () => resolve()
    req.onerror   = (e) => reject(e.target.error)
  })
}

export async function getPendingMovements() {
  await openDB()
  return new Promise((resolve, reject) => {
    const req = txStore('pending_stock_movements').getAll()
    req.onsuccess = (e) => resolve(e.target.result)
    req.onerror   = (e) => reject(e.target.error)
  })
}

export async function deletePendingMovement(local_id) {
  await openDB()
  return new Promise((resolve, reject) => {
    const req = txStore('pending_stock_movements', 'readwrite').delete(local_id)
    req.onsuccess = () => resolve()
    req.onerror   = (e) => reject(e.target.error)
  })
}

// Actualiza um produto individual na cache (após movimento offline)
export async function updateCachedProduct(product) {
  await openDB()
  return new Promise((resolve, reject) => {
    const req = txStore('products_cache', 'readwrite').put(product)
    req.onsuccess = () => resolve()
    req.onerror   = (e) => reject(e.target.error)
  })
}

// ── Cache de produtos (terminal) — isolada por loja ───────────────────────
export async function cacheProducts(products, storeId) {
  const d = await openDBSafe()
  if (!d) return
  return new Promise((resolve, reject) => {
    const tx    = d.transaction('products_cache', 'readwrite')
    const store = tx.objectStore('products_cache')
    // Stampamos cada produto com store_id para que getCachedProducts() possa filtrar
    products.forEach(p => store.put(JSON.parse(JSON.stringify({ ...p, store_id: storeId }))))
    tx.oncomplete = () => resolve()
    tx.onerror    = (ev) => reject(ev.target.error)
    tx.onabort    = (ev) => reject(ev.target.error)
  })
}

export async function getCachedProducts(storeId) {
  if (!storeId) return []
  const d = await openDBSafe()
  if (!d) return []
  return new Promise((resolve) => {
    // Filtra pelo índice store_id para não misturar produtos de lojas diferentes
    const idx = d.transaction('products_cache').objectStore('products_cache').index('store_id')
    const req = idx.getAll(IDBKeyRange.only(storeId))
    req.onsuccess = (e) => resolve(e.target.result)
    req.onerror   = () => resolve([])
  })
}

// ── Cache de categorias ────────────────────────────────────────────────────
export async function cacheCategories(cats) {
  return cacheSet('categories_cache', 'all', cats)
}
export async function getCachedCategories() {
  return cacheGet('categories_cache', 'all')   // { key, value: [...], saved_at }
}

// ── Cache de fecho de caixa (por data) ────────────────────────────────────
export async function cacheDailyCash(date, data) {
  return cacheSet('daily_cash_cache', date, data)
}
export async function getCachedDailyCash(date) {
  return cacheGet('daily_cash_cache', date)
}

// ── Cache de relatórios (por range from|to) ────────────────────────────────
export async function cacheReports(from, to, data) {
  return cacheSet('reports_cache', `${from}|${to}`, data)
}
export async function getCachedReports(from, to) {
  return cacheGet('reports_cache', `${from}|${to}`)
}

// ── Cache do histórico de stock ────────────────────────────────────────────
export async function cacheStockHistory(movements) {
  return cacheSet('stock_history_cache', 'all', movements)
}
export async function getCachedStockHistory() {
  return cacheGet('stock_history_cache', 'all')
}

// ── Cache de funcionários — isolada por loja ───────────────────────────────
export async function cacheEmployees(employees, storeId) {
  return cacheSet('employees_cache', `store_${storeId}`, employees)
}
export async function getCachedEmployees(storeId) {
  return cacheGet('employees_cache', `store_${storeId}`)
}

// ── Cache de gestão de produtos — isolada por loja ────────────────────────
export async function cacheManageProducts(products, storeId) {
  return cacheSet('manage_products_cache', `store_${storeId}`, products)
}
export async function getCachedManageProducts(storeId) {
  if (!storeId) return null
  return cacheGet('manage_products_cache', `store_${storeId}`)
}

// ── Operações de equipa pendentes offline ──────────────────────────────────
export async function savePendingEmployeeOp(op) {
  await openDB()
  return new Promise((resolve, reject) => {
    const req = txStore('pending_employee_ops', 'readwrite').put(op)
    req.onsuccess = () => resolve()
    req.onerror   = (e) => reject(e.target.error)
  })
}

export async function getPendingEmployeeOps() {
  await openDB()
  return new Promise((resolve, reject) => {
    const req = txStore('pending_employee_ops').getAll()
    req.onsuccess = (e) => resolve(e.target.result)
    req.onerror   = (e) => reject(e.target.error)
  })
}

export async function deletePendingEmployeeOp(local_id) {
  await openDB()
  return new Promise((resolve, reject) => {
    const req = txStore('pending_employee_ops', 'readwrite').delete(local_id)
    req.onsuccess = () => resolve()
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

  if (data.id_map) {
    const cached = await getCachedProducts()
    const store  = (await openDB()).transaction('products_cache', 'readwrite').objectStore('products_cache')
    for (const prod of pending) {
      const serverId  = data.id_map[prod.local_id]
      if (serverId) {
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

// ── Sincronização de movimentos de stock pendentes ────────────────────────
export async function syncPendingMovements() {
  const pending = await getPendingMovements()
  if (!pending.length) return { synced: 0 }

  let synced = 0
  const errors = []
  for (const mov of pending) {
    try {
      await axios.post('/pos/stock/movement', {
        product_id: mov.product_id,
        type:       mov.type,
        quantity:   mov.quantity,
        reason:     mov.reason,
      })
      await deletePendingMovement(mov.local_id)
      synced++
    } catch {
      errors.push(mov.local_id)
    }
  }
  return { synced, errors }
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

// ── Sincronização de operações de equipa pendentes ─────────────────────────
export async function syncPendingEmployeeOps() {
  const pending = await getPendingEmployeeOps()
  if (!pending.length) return { synced: 0 }

  let synced = 0
  for (const op of pending) {
    try {
      if (op.op_type === 'create_account') {
        await axios.post('/pos/employees/create-account', op.payload)
      } else if (op.op_type === 'add') {
        await axios.post('/pos/employees', op.payload)
      } else if (op.op_type === 'update') {
        await axios.put(`/pos/employees/${op.payload.id}`, op.payload)
      } else if (op.op_type === 'remove') {
        await axios.delete(`/pos/employees/${op.payload.id}`)
      } else if (op.op_type === 'reset_password') {
        await axios.put(`/pos/employees/${op.payload.id}/reset-password`, { password: op.payload.password })
      }
      await deletePendingEmployeeOp(op.local_id)
      synced++
    } catch {
      // mantém na fila, tentará novamente
    }
  }
  return { synced }
}

// ── Pré-carga de todos os dados POS para IndexedDB ─────────────────────────
// Chamado no mount do PosLayout e quando a ligação volta.
// Garante que TODOS os ecrãs do POS funcionam offline mesmo sem ter sido
// visitados anteriormente nesta sessão.
export async function prefetchPosData(storeId) {
  if (!navigator.onLine || !storeId) return
  try {
    const [productsRes, manageProductsRes, employeesRes] = await Promise.allSettled([
      axios.get('/pos/products'),
      axios.get('/pos/products/manage'),
      axios.get('/pos/employees'),
    ])
    if (productsRes.status === 'fulfilled' && productsRes.value.data?.length) {
      await cacheProducts(productsRes.value.data, storeId)
    }
    if (manageProductsRes.status === 'fulfilled' && Array.isArray(manageProductsRes.value.data)) {
      await cacheManageProducts(manageProductsRes.value.data, storeId)
    }
    if (employeesRes.status === 'fulfilled' && Array.isArray(employeesRes.value.data)) {
      await cacheEmployees(employeesRes.value.data, storeId)
    }
  } catch {
    // falha silenciosa — cache existente mantém-se
  }
}

// ── Helper: formatar timestamp de cache ────────────────────────────────────
export function fmtCacheAge(savedAt) {
  if (!savedAt) return ''
  const diff = Math.floor((Date.now() - savedAt) / 1000)
  if (diff < 60)   return 'há menos de 1 min'
  if (diff < 3600) return `há ${Math.floor(diff / 60)} min`
  return `há ${Math.floor(diff / 3600)}h`
}

// ── Composable Vue ─────────────────────────────────────────────────────────
export function useOfflinePos() {
  const isOnline            = ref(navigator.onLine)
  const pendingCount        = ref(0)
  const pendingProductCount = ref(0)
  const pendingEmployeeCount= ref(0)
  const syncing             = ref(false)
  const syncMessage         = ref('')

  async function refreshPendingCount() {
    const [sales, products, movements, empOps] = await Promise.all([
      getPendingSales(), getPendingProducts(), getPendingMovements(), getPendingEmployeeOps(),
    ])
    pendingCount.value         = sales.length + movements.length
    pendingProductCount.value  = products.length
    pendingEmployeeCount.value = empOps.length
  }

  async function trySyncNow() {
    if (!isOnline.value || syncing.value) return
    const [sales, products, movements, empOps] = await Promise.all([
      getPendingSales(), getPendingProducts(), getPendingMovements(), getPendingEmployeeOps(),
    ])
    if (!sales.length && !products.length && !movements.length && !empOps.length) return

    syncing.value     = true
    syncMessage.value = ''
    try {
      let msg = ''
      if (products.length) {
        const r = await syncPendingProducts()
        if (r.created > 0) msg += `${r.created} produto(s) criado(s). `
      }
      if (movements.length) {
        const r = await syncPendingMovements()
        if (r.synced > 0) msg += `${r.synced} movimento(s) de stock sincronizado(s). `
      }
      if (sales.length) {
        const r = await syncPendingSales()
        if (r.synced > 0) msg += `${r.synced} venda(s) sincronizada(s). `
      }
      if (empOps.length) {
        const r = await syncPendingEmployeeOps()
        if (r.synced > 0) msg += `${r.synced} operação(ões) de equipa sincronizada(s).`
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
    isOnline, pendingCount, pendingProductCount, pendingEmployeeCount,
    syncing, syncMessage, trySyncNow, refreshPendingCount,
  }
}
