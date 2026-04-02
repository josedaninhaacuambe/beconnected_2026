/**
 * POS Terminal — ecrã principal de ponto de venda.
 * Layout: Scanner (esquerda) + Carrinho (direita) + Botão de venda.
 * 100% funcional offline: vendas persistidas em IndexedDB, sincronizadas quando online.
 */
import { useState, useCallback } from 'react'
import { Scanner } from './Scanner'
import { useOfflineSync } from '../hooks/useOfflineSync'
import {
  getCart, upsertCartItem, updateCartItemQty, clearCart, savePendingSale
} from '../db/posDb'
import { useEffect } from 'react'

const PAYMENT_METHODS = [
  { id: 'emola',  label: 'eMola',   icon: '📱' },
  { id: 'mpesa',  label: 'M-Pesa',  icon: '💚' },
  { id: 'cash',   label: 'Dinheiro', icon: '💵' },
  { id: 'card',   label: 'Cartão',  icon: '💳' },
]

export function POSTerminal({ storeId, sessionId }) {
  const [cart,          setCart]          = useState([])
  const [paymentMethod, setPaymentMethod] = useState('cash')
  const [processing,    setProcessing]    = useState(false)
  const [lastSale,      setLastSale]      = useState(null)
  const [pendingCount,  setPendingCount]  = useState(0)
  const isOnline = navigator.onLine

  // Carrega carrinho do IndexedDB ao montar
  useEffect(() => {
    getCart().then(setCart)
  }, [])

  const { syncNow } = useOfflineSync(() => setPendingCount(0))

  const fmt = v => Number(v || 0).toLocaleString('pt-MZ', { minimumFractionDigits: 2 })
  const total = cart.reduce((s, i) => s + (i.price * i.quantity), 0)

  // ─── Adicionar produto via scan ───────────────────────────────────────────
  const handleProductFound = useCallback(async (product) => {
    const item = await upsertCartItem({
      productId: product.id,
      name:      product.name,
      price:     product.price,
      barcode:   product.barcode,
      quantity:  1,
    })
    const updated = await getCart()
    setCart(updated)
  }, [])

  // ─── Actualizar quantidade ────────────────────────────────────────────────
  const handleQtyChange = async (productId, qty) => {
    await updateCartItemQty(productId, qty)
    const updated = await getCart()
    setCart(updated)
  }

  // ─── Finalizar venda ──────────────────────────────────────────────────────
  const handleSell = async () => {
    if (!cart.length || processing) return
    setProcessing(true)

    const salePayload = {
      items:         cart.map(i => ({ productId: i.productId, quantity: i.quantity, unitPrice: i.price })),
      total,
      paymentMethod,
      storeId,
      sessionId,
    }

    try {
      if (isOnline) {
        // Online: envia directamente ao servidor
        const { default: axios } = await import('axios')
        const { data } = await axios.post(
          `${import.meta.env.VITE_API_BASE || 'http://localhost:8000'}/api/internal/pos/sales`,
          salePayload,
          { timeout: 10000 }
        )
        setLastSale({ ...salePayload, orderId: data.orderId, synced: true })
      } else {
        // Offline: guarda no IndexedDB
        const localId = await savePendingSale(salePayload)
        setPendingCount(c => c + 1)
        setLastSale({ ...salePayload, localId, synced: false })
      }

      await clearCart()
      setCart([])
    } catch (e) {
      // Falha online → guarda offline
      const localId = await savePendingSale(salePayload)
      setPendingCount(c => c + 1)
      setLastSale({ ...salePayload, localId, synced: false })
      await clearCart()
      setCart([])
    } finally {
      setProcessing(false)
    }
  }

  return (
    <div className="min-h-screen bg-gray-950 text-white flex flex-col">
      {/* Header */}
      <header className="bg-gray-900 border-b border-gray-800 px-6 py-3 flex items-center justify-between">
        <div className="flex items-center gap-3">
          <span className="text-2xl font-black text-yellow-400">Beconnect</span>
          <span className="text-gray-500 text-sm">POS</span>
        </div>
        <div className="flex items-center gap-3">
          {pendingCount > 0 && (
            <button onClick={syncNow} className="text-xs bg-orange-600 px-3 py-1 rounded-full">
              {pendingCount} pendente(s) — Sincronizar
            </button>
          )}
          <span className={`text-xs px-2 py-1 rounded-full ${isOnline ? 'bg-green-800 text-green-300' : 'bg-red-800 text-red-300'}`}>
            {isOnline ? '🟢 Online' : '🔴 Offline'}
          </span>
        </div>
      </header>

      <div className="flex flex-1 overflow-hidden">
        {/* Esquerda: Scanner */}
        <div className="w-1/2 p-6 border-r border-gray-800">
          <h2 className="text-lg font-bold mb-4 text-gray-300">Scan Produto</h2>
          <Scanner onProductFound={handleProductFound} />

          {/* Método de pagamento */}
          <div className="mt-6">
            <p className="text-sm text-gray-400 mb-2">Método de pagamento</p>
            <div className="grid grid-cols-2 gap-2">
              {PAYMENT_METHODS.map(m => (
                <button
                  key={m.id}
                  onClick={() => setPaymentMethod(m.id)}
                  className={`py-2 px-3 rounded-xl text-sm font-semibold border transition ${
                    paymentMethod === m.id
                      ? 'bg-yellow-500 text-gray-900 border-yellow-400'
                      : 'bg-gray-800 text-gray-300 border-gray-700 hover:bg-gray-700'
                  }`}
                >
                  {m.icon} {m.label}
                </button>
              ))}
            </div>
          </div>
        </div>

        {/* Direita: Carrinho */}
        <div className="w-1/2 flex flex-col p-6">
          <h2 className="text-lg font-bold mb-4 text-gray-300">Carrinho</h2>

          <div className="flex-1 overflow-y-auto space-y-2">
            {cart.length === 0 ? (
              <p className="text-gray-600 text-center mt-12">Scanneia um produto para começar</p>
            ) : (
              cart.map(item => (
                <div key={item.productId} className="bg-gray-800 rounded-xl p-3 flex items-center gap-3">
                  <div className="flex-1">
                    <p className="text-sm font-semibold text-white">{item.name}</p>
                    <p className="text-xs text-yellow-400">{fmt(item.price)} MT/un</p>
                  </div>
                  <div className="flex items-center gap-2">
                    <button
                      onClick={() => handleQtyChange(item.productId, item.quantity - 1)}
                      className="w-7 h-7 bg-gray-700 rounded-full text-white hover:bg-gray-600"
                    >−</button>
                    <span className="text-white font-bold w-6 text-center">{item.quantity}</span>
                    <button
                      onClick={() => handleQtyChange(item.productId, item.quantity + 1)}
                      className="w-7 h-7 bg-gray-700 rounded-full text-white hover:bg-gray-600"
                    >+</button>
                  </div>
                  <p className="text-white font-bold text-sm w-24 text-right">
                    {fmt(item.price * item.quantity)} MT
                  </p>
                </div>
              ))
            )}
          </div>

          {/* Total + venda */}
          <div className="border-t border-gray-800 pt-4 mt-4">
            <div className="flex justify-between items-center mb-4">
              <span className="text-gray-400 text-lg">Total</span>
              <span className="text-yellow-400 font-black text-2xl">{fmt(total)} MT</span>
            </div>
            <button
              onClick={handleSell}
              disabled={!cart.length || processing}
              className="w-full bg-green-600 hover:bg-green-500 disabled:bg-gray-700 disabled:text-gray-500
                         text-white font-black text-lg py-4 rounded-2xl transition"
            >
              {processing ? 'A processar...' : `✅ Vender — ${fmt(total)} MT`}
            </button>
          </div>

          {/* Confirmação da última venda */}
          {lastSale && (
            <div className={`mt-3 p-3 rounded-xl text-sm ${lastSale.synced ? 'bg-green-900/40 text-green-300' : 'bg-orange-900/40 text-orange-300'}`}>
              {lastSale.synced
                ? `✅ Venda sincronizada — Ordem #${lastSale.orderId}`
                : `⏳ Venda guardada offline (ID local: ${lastSale.localId}). Será sincronizada quando online.`}
            </div>
          )}
        </div>
      </div>
    </div>
  )
}
