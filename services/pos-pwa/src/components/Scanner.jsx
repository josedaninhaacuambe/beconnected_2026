/**
 * Componente Scanner — câmera + busca por código de barras / QR code.
 * Usa html5-qrcode para leitura da câmera.
 * Fallback: campo de texto para entrada manual.
 */
import { useEffect, useRef, useState } from 'react'
import { Html5Qrcode } from 'html5-qrcode'
import { getProductByBarcode, searchProducts } from '../db/posDb'

export function Scanner({ onProductFound }) {
  const scannerRef = useRef(null)
  const [scanning,    setScanning]    = useState(false)
  const [manualInput, setManualInput] = useState('')
  const [error,       setError]       = useState(null)
  const [results,     setResults]     = useState([])

  // ─── Câmera ───────────────────────────────────────────────────────────────
  useEffect(() => {
    const scanner = new Html5Qrcode('qr-reader')
    scannerRef.current = scanner

    return () => {
      if (scanner.isScanning) scanner.stop().catch(() => {})
    }
  }, [])

  const startScan = async () => {
    setError(null)
    try {
      await scannerRef.current.start(
        { facingMode: 'environment' },
        { fps: 15, qrbox: { width: 280, height: 180 } },
        async (decodedText) => {
          setScanning(false)
          await scannerRef.current.stop()
          await handleBarcode(decodedText)
        },
        undefined
      )
      setScanning(true)
    } catch (e) {
      setError('Câmera não disponível. Usa a busca manual.')
    }
  }

  const stopScan = async () => {
    if (scannerRef.current?.isScanning) {
      await scannerRef.current.stop()
    }
    setScanning(false)
  }

  // ─── Busca por código ─────────────────────────────────────────────────────
  const handleBarcode = async (code) => {
    const product = await getProductByBarcode(code.trim())
    if (product) {
      onProductFound(product)
    } else {
      setError(`Produto não encontrado para código: ${code}`)
    }
  }

  // ─── Busca manual ─────────────────────────────────────────────────────────
  const handleManualSearch = async (query) => {
    setManualInput(query)
    if (query.length < 2) { setResults([]); return }

    // Verifica primeiro se é um barcode exacto
    const byBarcode = await getProductByBarcode(query)
    if (byBarcode) { onProductFound(byBarcode); setManualInput(''); return }

    // Busca por nome / ID
    const found = await searchProducts(query)
    setResults(found)
  }

  return (
    <div className="scanner-wrapper">
      {/* Câmera */}
      <div id="qr-reader" className="w-full rounded-xl overflow-hidden bg-black min-h-[180px]" />

      <div className="flex gap-2 mt-3">
        {!scanning ? (
          <button
            onClick={startScan}
            className="flex-1 bg-blue-600 hover:bg-blue-500 text-white font-bold py-2 px-4 rounded-xl"
          >
            📷 Iniciar Scan
          </button>
        ) : (
          <button
            onClick={stopScan}
            className="flex-1 bg-red-600 hover:bg-red-500 text-white font-bold py-2 px-4 rounded-xl"
          >
            ⏹ Parar Scan
          </button>
        )}
      </div>

      {/* Busca manual */}
      <div className="mt-4">
        <input
          type="text"
          placeholder="Buscar por nome ou código de barras..."
          value={manualInput}
          onChange={e => handleManualSearch(e.target.value)}
          className="w-full bg-gray-800 text-white border border-gray-600 rounded-xl px-4 py-2 text-sm focus:outline-none focus:border-blue-500"
        />
        {results.length > 0 && (
          <ul className="mt-2 bg-gray-800 rounded-xl border border-gray-600 max-h-48 overflow-y-auto">
            {results.map(p => (
              <li
                key={p.id}
                onClick={() => { onProductFound(p); setResults([]); setManualInput('') }}
                className="flex items-center justify-between px-4 py-2 hover:bg-gray-700 cursor-pointer text-sm text-white"
              >
                <span>{p.name}</span>
                <span className="text-yellow-400 font-bold">{Number(p.price).toLocaleString('pt-MZ')} MT</span>
              </li>
            ))}
          </ul>
        )}
      </div>

      {error && <p className="mt-2 text-red-400 text-xs">{error}</p>}
    </div>
  )
}
