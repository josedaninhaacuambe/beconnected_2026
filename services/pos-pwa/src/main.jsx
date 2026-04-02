import React from 'react'
import ReactDOM from 'react-dom/client'
import { POSTerminal } from './components/POSTerminal'
import './index.css'

// Service Worker registration (Workbox via vite-plugin-pwa)
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js').catch(err => {
      console.warn('[POS SW] Registration failed:', err)
    })
  })
}

ReactDOM.createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <POSTerminal
      storeId={window.__POS_STORE_ID__ || '1'}
      sessionId={window.__POS_SESSION_ID__ || crypto.randomUUID()}
    />
  </React.StrictMode>
)
