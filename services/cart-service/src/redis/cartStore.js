'use strict'

/**
 * CartStore — todas as operações do carrinho vivem no Redis.
 *
 * Estrutura de chaves:
 *   cart:{cartId}:items        → Hash  { productId: quantity }
 *   cart:{cartId}:prices       → Hash  { productId: unitPrice }
 *   cart:{cartId}:meta         → Hash  { userId, storeId, createdAt, updatedAt }
 *   cart:user:{userId}         → String → cartId   (lookup rápido)
 *   cart:session:{sessionId}   → String → cartId
 *   cart:{cartId}:lock         → String → "1" (distributed lock via SET NX PX)
 *   cart:events                → Stream (Redis Streams) → auditoria e downstream
 *
 * TTL padrão do carrinho: 7 dias (604800s)
 */

const redis  = require('./client')
const { v4: uuidv4 } = require('uuid')

const CART_TTL_SEC = 7 * 24 * 3600   // 7 dias
const LOCK_TTL_MS  = 5000            // 5s — lock distribuído por operação

// ─── TTL helper ──────────────────────────────────────────────────────────────
async function touchTTL(cartId) {
  const pipeline = redis.pipeline()
  pipeline.expire(`cart:${cartId}:items`,  CART_TTL_SEC)
  pipeline.expire(`cart:${cartId}:prices`, CART_TTL_SEC)
  pipeline.expire(`cart:${cartId}:meta`,   CART_TTL_SEC)
  await pipeline.exec()
}

// ─── Lock distribuído (SET NX PX) ────────────────────────────────────────────
async function acquireLock(cartId, productId) {
  const key   = `cart:${cartId}:lock:${productId}`
  const token = uuidv4()
  const ok    = await redis.set(key, token, 'PX', LOCK_TTL_MS, 'NX')
  return ok === 'OK' ? { key, token } : null
}

async function releaseLock({ key, token }) {
  // Lua script garante atomicidade: só liberta se o token for o mesmo
  const script = `
    if redis.call("GET", KEYS[1]) == ARGV[1] then
      return redis.call("DEL", KEYS[1])
    else
      return 0
    end
  `
  await redis.eval(script, 1, key, token)
}

// ─── Resolve / cria cartId ────────────────────────────────────────────────────
async function resolveCartId({ userId, sessionId }) {
  const lookupKey = userId
    ? `cart:user:${userId}`
    : `cart:session:${sessionId}`

  let cartId = await redis.get(lookupKey)
  if (cartId) return cartId

  cartId = uuidv4()
  const now = new Date().toISOString()

  const pipeline = redis.pipeline()
  pipeline.set(lookupKey, cartId, 'EX', CART_TTL_SEC)
  pipeline.hset(`cart:${cartId}:meta`,
    'userId', userId || '',
    'sessionId', sessionId || '',
    'createdAt', now,
    'updatedAt', now
  )
  pipeline.expire(`cart:${cartId}:meta`, CART_TTL_SEC)
  await pipeline.exec()

  return cartId
}

// ─── ADD / UPDATE item ────────────────────────────────────────────────────────
async function addItem({ cartId, productId, quantity, unitPrice, storeId }) {
  const lock = await acquireLock(cartId, productId)
  if (!lock) throw Object.assign(new Error('Carrinho ocupado. Tente novamente.'), { status: 429 })

  try {
    // Soma atómica via HINCRBY (garante ausência de race condition)
    const pipeline = redis.pipeline()
    pipeline.hincrby(`cart:${cartId}:items`,  productId, quantity)
    pipeline.hset(`cart:${cartId}:prices`, productId, unitPrice)
    // storeId associado ao item (suporte multi-loja no mesmo carrinho)
    pipeline.hset(`cart:${cartId}:stores`, productId, storeId)
    pipeline.hset(`cart:${cartId}:meta`, 'updatedAt', new Date().toISOString())
    const results = await pipeline.exec()

    const newQty = results[0][1]  // resultado do HINCRBY

    await touchTTL(cartId)

    // Publica evento no Redis Stream para consumo downstream (Stock, Analytics)
    await redis.xadd('cart:events', '*',
      'event',     'item.added',
      'cartId',    cartId,
      'productId', productId,
      'quantity',  quantity,
      'unitPrice', unitPrice,
      'storeId',   storeId,
      'newQty',    newQty,
      'ts',        Date.now()
    )

    return { cartId, productId, newQty, unitPrice }
  } finally {
    await releaseLock(lock)
  }
}

// ─── REMOVE item ──────────────────────────────────────────────────────────────
async function removeItem({ cartId, productId }) {
  const lock = await acquireLock(cartId, productId)
  if (!lock) throw Object.assign(new Error('Carrinho ocupado. Tente novamente.'), { status: 429 })

  try {
    const pipeline = redis.pipeline()
    pipeline.hdel(`cart:${cartId}:items`,  productId)
    pipeline.hdel(`cart:${cartId}:prices`, productId)
    pipeline.hdel(`cart:${cartId}:stores`, productId)
    pipeline.hset(`cart:${cartId}:meta`, 'updatedAt', new Date().toISOString())
    await pipeline.exec()

    await redis.xadd('cart:events', '*',
      'event',     'item.removed',
      'cartId',    cartId,
      'productId', productId,
      'ts',        Date.now()
    )
  } finally {
    await releaseLock(lock)
  }
}

// ─── UPDATE quantity ──────────────────────────────────────────────────────────
async function updateItem({ cartId, productId, quantity }) {
  const lock = await acquireLock(cartId, productId)
  if (!lock) throw Object.assign(new Error('Carrinho ocupado. Tente novamente.'), { status: 429 })

  try {
    if (quantity <= 0) {
      await removeItem({ cartId, productId })
      return { removed: true }
    }
    await redis.hset(`cart:${cartId}:items`, productId, quantity)
    await redis.hset(`cart:${cartId}:meta`, 'updatedAt', new Date().toISOString())
    await touchTTL(cartId)
    return { cartId, productId, newQty: quantity }
  } finally {
    await releaseLock(lock)
  }
}

// ─── GET cart (devolve itens enriquecidos) ────────────────────────────────────
async function getCart(cartId) {
  const [rawItems, rawPrices, rawStores, meta] = await Promise.all([
    redis.hgetall(`cart:${cartId}:items`),
    redis.hgetall(`cart:${cartId}:prices`),
    redis.hgetall(`cart:${cartId}:stores`),
    redis.hgetall(`cart:${cartId}:meta`),
  ])

  const items = Object.entries(rawItems || {}).map(([productId, qty]) => {
    const unitPrice = parseFloat(rawPrices?.[productId] || 0)
    const quantity  = parseInt(qty, 10)
    return {
      productId,
      quantity,
      unitPrice,
      storeId:  rawStores?.[productId] || null,
      subtotal: +(unitPrice * quantity).toFixed(2),
    }
  })

  const subtotal = items.reduce((s, i) => s + i.subtotal, 0)

  return {
    cartId,
    meta:  meta || {},
    items,
    totalItems: items.reduce((s, i) => s + i.quantity, 0),
    subtotal:   +subtotal.toFixed(2),
  }
}

// ─── CLEAR cart ───────────────────────────────────────────────────────────────
async function clearCart(cartId) {
  const pipeline = redis.pipeline()
  pipeline.del(`cart:${cartId}:items`)
  pipeline.del(`cart:${cartId}:prices`)
  pipeline.del(`cart:${cartId}:stores`)
  pipeline.hset(`cart:${cartId}:meta`, 'updatedAt', new Date().toISOString())
  await pipeline.exec()

  await redis.xadd('cart:events', '*',
    'event',  'cart.cleared',
    'cartId', cartId,
    'ts',     Date.now()
  )
}

// ─── CHECKOUT snapshot (chamado pelo Payment Service via HTTP) ─────────────────
async function snapshotForCheckout(cartId) {
  const cart = await getCart(cartId)
  if (!cart.items.length) throw Object.assign(new Error('Carrinho vazio.'), { status: 422 })

  // Guarda snapshot imutável para referência do checkout
  const snapshotKey = `cart:${cartId}:checkout:${Date.now()}`
  await redis.set(snapshotKey, JSON.stringify(cart), 'EX', 3600)

  return { snapshot: cart, snapshotKey }
}

module.exports = {
  resolveCartId,
  addItem,
  removeItem,
  updateItem,
  getCart,
  clearCart,
  snapshotForCheckout,
}
