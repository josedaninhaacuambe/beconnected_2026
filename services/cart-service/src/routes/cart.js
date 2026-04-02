'use strict'

const express  = require('express')
const router   = express.Router()
const store    = require('../redis/cartStore')
const axios    = require('axios')

const STOCK_SERVICE_URL = process.env.STOCK_SERVICE_URL || 'http://stock-service:8081'

// ─── Helper: valida stock antes de adicionar ─────────────────────────────────
async function validateStock(productId, requestedQty, currentQty) {
  try {
    const { data } = await axios.get(`${STOCK_SERVICE_URL}/api/stock/${productId}`, {
      timeout: 3000,
      headers: { 'X-Internal-Key': process.env.INTERNAL_API_KEY || 'beconnect_internal' },
    })
    const available = data.available ?? 0
    if (requestedQty + currentQty > available) {
      return { ok: false, available, message: `Stock insuficiente. Disponível: ${available}` }
    }
    return { ok: true, available, unitPrice: data.price, storeId: data.store_id }
  } catch (e) {
    // Se o Stock Service estiver indisponível, permite optimisticamente e reconcilia no checkout
    console.warn(`[Cart] Stock service unreachable for product ${productId}: ${e.message}`)
    return { ok: true, available: null, unitPrice: null, storeId: null }
  }
}

// ─── GET /cart ───────────────────────────────────────────────────────────────
router.get('/', async (req, res) => {
  try {
    const cartId = await store.resolveCartId(req.identity)
    const cart   = await store.getCart(cartId)
    res.json(cart)
  } catch (e) {
    res.status(e.status || 500).json({ message: e.message })
  }
})

// ─── POST /cart/items — adicionar produto ────────────────────────────────────
router.post('/items', async (req, res) => {
  const { product_id, quantity = 1 } = req.body

  if (!product_id || !Number.isInteger(quantity) || quantity < 1 || quantity > 100) {
    return res.status(422).json({ message: 'product_id obrigatório e quantity entre 1–100.' })
  }

  try {
    const cartId = await store.resolveCartId(req.identity)
    const cart   = await store.getCart(cartId)
    const currentQty = cart.items.find(i => i.productId === String(product_id))?.quantity ?? 0

    // Valida stock (async, não bloqueia UI — resposta rápida garantida)
    const stockCheck = await validateStock(product_id, quantity, currentQty)
    if (!stockCheck.ok) {
      return res.status(422).json({ message: stockCheck.message, available: stockCheck.available })
    }

    const unitPrice = stockCheck.unitPrice ?? req.body.unit_price ?? 0
    const storeId   = stockCheck.storeId   ?? req.body.store_id   ?? null

    const result = await store.addItem({
      cartId,
      productId: String(product_id),
      quantity,
      unitPrice,
      storeId: String(storeId || ''),
    })

    res.status(201).json({
      message:    'Produto adicionado ao carrinho.',
      cartId:     result.cartId,
      productId:  result.productId,
      newQty:     result.newQty,
      unitPrice:  result.unitPrice,
    })
  } catch (e) {
    res.status(e.status || 500).json({ message: e.message })
  }
})

// ─── PATCH /cart/items/:productId — actualizar quantidade ────────────────────
router.patch('/items/:productId', async (req, res) => {
  const { quantity } = req.body
  const { productId } = req.params

  if (!Number.isInteger(quantity) || quantity < 0 || quantity > 100) {
    return res.status(422).json({ message: 'quantity deve ser entre 0–100.' })
  }

  try {
    const cartId = await store.resolveCartId(req.identity)
    const result = await store.updateItem({ cartId, productId, quantity })
    res.json({ message: result.removed ? 'Item removido.' : 'Carrinho actualizado.', ...result })
  } catch (e) {
    res.status(e.status || 500).json({ message: e.message })
  }
})

// ─── DELETE /cart/items/:productId — remover item ────────────────────────────
router.delete('/items/:productId', async (req, res) => {
  try {
    const cartId = await store.resolveCartId(req.identity)
    await store.removeItem({ cartId, productId: req.params.productId })
    res.json({ message: 'Item removido do carrinho.' })
  } catch (e) {
    res.status(e.status || 500).json({ message: e.message })
  }
})

// ─── DELETE /cart — limpar carrinho ─────────────────────────────────────────
router.delete('/', async (req, res) => {
  try {
    const cartId = await store.resolveCartId(req.identity)
    await store.clearCart(cartId)
    res.json({ message: 'Carrinho limpo.' })
  } catch (e) {
    res.status(e.status || 500).json({ message: e.message })
  }
})

// ─── POST /cart/checkout — snapshot + envia ao Payment Service ───────────────
router.post('/checkout', async (req, res) => {
  try {
    const cartId = await store.resolveCartId(req.identity)
    const { snapshot, snapshotKey } = await store.snapshotForCheckout(cartId)

    // Repassa ao Payment Service com idempotency key única
    const idempotencyKey = `checkout:${cartId}:${Date.now()}`

    const paymentRes = await axios.post(
      `${process.env.PAYMENT_SERVICE_URL || 'http://payment-service:8082'}/api/checkout/initiate`,
      {
        cart:           snapshot,
        userId:         req.identity.userId,
        idempotencyKey,
        paymentMethod:  req.body.payment_method || 'emola',
        phone:          req.body.phone,
      },
      {
        timeout: 10000,
        headers: { 'X-Internal-Key': process.env.INTERNAL_API_KEY || 'beconnect_internal' },
      }
    )

    res.json({
      message:        'Checkout iniciado.',
      checkoutId:     paymentRes.data.checkoutId,
      idempotencyKey,
      snapshotKey,
      status:         paymentRes.data.status,
    })
  } catch (e) {
    if (e.response) {
      return res.status(e.response.status).json(e.response.data)
    }
    res.status(e.status || 500).json({ message: e.message })
  }
})

// ─── GET /cart/health ────────────────────────────────────────────────────────
router.get('/health', async (req, res) => {
  const redis = require('../redis/client')
  try {
    await redis.ping()
    res.json({ status: 'ok', service: 'cart-service', redis: 'connected' })
  } catch {
    res.status(503).json({ status: 'degraded', redis: 'disconnected' })
  }
})

module.exports = router
