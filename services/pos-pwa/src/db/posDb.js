/**
 * Beconnect POS — IndexedDB layer (via idb)
 *
 * Stores:
 *   products       — catálogo offline (sincronizado periodicamente)
 *   pending_sales  — vendas feitas offline, sincronizadas quando online
 *   cart           — carrinho actual (persiste entre sessões)
 *   sync_log       — auditoria de sincronizações
 */
import { openDB } from 'idb'

const DB_NAME    = 'beconnect-pos'
const DB_VERSION = 1

let _db = null

export async function getDb() {
  if (_db) return _db

  _db = await openDB(DB_NAME, DB_VERSION, {
    upgrade(db) {
      // Catálogo de produtos
      if (!db.objectStoreNames.contains('products')) {
        const products = db.createObjectStore('products', { keyPath: 'id' })
        products.createIndex('barcode',   'barcode',   { unique: false })
        products.createIndex('store_id',  'store_id',  { unique: false })
        products.createIndex('name',      'name',      { unique: false })
      }

      // Vendas pendentes (offline)
      if (!db.objectStoreNames.contains('pending_sales')) {
        const sales = db.createObjectStore('pending_sales', { keyPath: 'localId', autoIncrement: true })
        sales.createIndex('status',     'status',     { unique: false })
        sales.createIndex('created_at', 'created_at', { unique: false })
      }

      // Carrinho activo
      if (!db.objectStoreNames.contains('cart')) {
        db.createObjectStore('cart', { keyPath: 'productId' })
      }

      // Log de sincronização
      if (!db.objectStoreNames.contains('sync_log')) {
        const log = db.createObjectStore('sync_log', { keyPath: 'id', autoIncrement: true })
        log.createIndex('ts', 'ts', { unique: false })
      }
    },
  })

  return _db
}

// ─── Products ─────────────────────────────────────────────────────────────────
export async function upsertProducts(products) {
  const db = await getDb()
  const tx = db.transaction('products', 'readwrite')
  await Promise.all([
    ...products.map(p => tx.store.put(p)),
    tx.done,
  ])
}

export async function getProductByBarcode(barcode) {
  const db  = await getDb()
  const idx = db.transaction('products').store.index('barcode')
  return idx.get(barcode)
}

export async function getProductById(id) {
  const db = await getDb()
  return db.get('products', id)
}

export async function searchProducts(query) {
  const db       = await getDb()
  const all      = await db.getAll('products')
  const q        = query.toLowerCase()
  return all.filter(p =>
    p.name?.toLowerCase().includes(q) ||
    p.barcode?.includes(q) ||
    String(p.id).includes(q)
  ).slice(0, 20)
}

// ─── Cart ─────────────────────────────────────────────────────────────────────
export async function getCart() {
  const db = await getDb()
  return db.getAll('cart')
}

export async function upsertCartItem(item) {
  const db   = await getDb()
  const tx   = db.transaction('cart', 'readwrite')
  const existing = await tx.store.get(item.productId)
  if (existing) {
    item.quantity = (existing.quantity || 0) + (item.quantity || 1)
  }
  await tx.store.put(item)
  await tx.done
  return item
}

export async function updateCartItemQty(productId, quantity) {
  const db = await getDb()
  if (quantity <= 0) return db.delete('cart', productId)
  const item = await db.get('cart', productId)
  if (!item) return
  item.quantity = quantity
  return db.put('cart', item)
}

export async function clearCart() {
  const db = await getDb()
  return db.clear('cart')
}

// ─── Pending Sales ────────────────────────────────────────────────────────────
export async function savePendingSale(sale) {
  const db = await getDb()
  return db.add('pending_sales', {
    ...sale,
    status:     'pending',
    created_at: new Date().toISOString(),
    retries:    0,
  })
}

export async function getPendingSales() {
  const db  = await getDb()
  const idx = db.transaction('pending_sales').store.index('status')
  return idx.getAll('pending')
}

export async function markSaleSynced(localId, serverId) {
  const db   = await getDb()
  const sale = await db.get('pending_sales', localId)
  if (!sale) return
  sale.status    = 'synced'
  sale.server_id = serverId
  sale.synced_at = new Date().toISOString()
  return db.put('pending_sales', sale)
}

export async function markSaleFailed(localId, error) {
  const db   = await getDb()
  const sale = await db.get('pending_sales', localId)
  if (!sale) return
  sale.status  = 'failed'
  sale.error   = error
  sale.retries = (sale.retries || 0) + 1
  return db.put('pending_sales', sale)
}
