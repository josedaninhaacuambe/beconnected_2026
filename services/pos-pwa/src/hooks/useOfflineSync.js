/**
 * Hook de sincronização offline ↔ online.
 * Quando a ligação volta, envia automaticamente todas as vendas pendentes.
 */
import { useEffect, useRef, useCallback } from 'react'
import axios from 'axios'
import { getPendingSales, markSaleSynced, markSaleFailed } from '../db/posDb'

const API_BASE = import.meta.env.VITE_API_BASE || 'http://localhost:8000'

export function useOfflineSync(onSyncComplete) {
  const isSyncing = useRef(false)

  const syncPendingSales = useCallback(async () => {
    if (isSyncing.current) return
    isSyncing.current = true

    try {
      const pending = await getPendingSales()
      if (!pending.length) return

      console.log(`[POS Sync] ${pending.length} sale(s) to sync`)

      for (const sale of pending) {
        if (sale.retries >= 5) continue  // abandona após 5 tentativas

        try {
          const { data } = await axios.post(
            `${API_BASE}/api/internal/pos/sales`,
            {
              items:          sale.items,
              total:          sale.total,
              payment_method: sale.paymentMethod,
              store_id:       sale.storeId,
              pos_session_id: sale.sessionId,
              sold_at:        sale.created_at,
            },
            { timeout: 15000 }
          )

          await markSaleSynced(sale.localId, data.orderId)
          console.log(`[POS Sync] Sale ${sale.localId} → order ${data.orderId}`)
        } catch (e) {
          await markSaleFailed(sale.localId, e.message)
          console.warn(`[POS Sync] Sale ${sale.localId} failed:`, e.message)
        }
      }

      onSyncComplete?.()
    } finally {
      isSyncing.current = false
    }
  }, [onSyncComplete])

  useEffect(() => {
    // Tenta sincronizar imediatamente e quando a ligação volta
    syncPendingSales()

    const handleOnline = () => {
      console.log('[POS] Back online — syncing...')
      syncPendingSales()
    }

    window.addEventListener('online', handleOnline)

    // Polling a cada 2 minutos enquanto online
    const interval = setInterval(() => {
      if (navigator.onLine) syncPendingSales()
    }, 120_000)

    return () => {
      window.removeEventListener('online', handleOnline)
      clearInterval(interval)
    }
  }, [syncPendingSales])

  return {
    isOnline: navigator.onLine,
    syncNow:  syncPendingSales,
  }
}
