/**
 * Load Balancer Node.js — distribui pedidos por múltiplos workers PHP
 * Porta 8080 → workers nas portas 8091-8098
 */
const http  = require('http')
const { spawn } = require('child_process')
const net   = require('net')

const PHP     = 'C:\\laragon\\bin\\php\\php-8.3.30-Win32-vs16-x64\\php.exe'
const ARTISAN = 'C:\\laragon\\www\\Beconnect\\artisan'
const WORKERS = 8
const BASE_PORT = 8091
const LB_PORT   = 8080

const workers = []
let current = 0

// ── Verificar se porta está livre ────────────────────────────────────────────
function waitForPort(port, timeout = 15000) {
  return new Promise((resolve, reject) => {
    const start = Date.now()
    function check() {
      const sock = net.createConnection({ port, host: '127.0.0.1' })
      sock.on('connect', () => { sock.destroy(); resolve() })
      sock.on('error', () => {
        if (Date.now() - start > timeout) reject(new Error(`Port ${port} timeout`))
        else setTimeout(check, 300)
      })
    }
    check()
  })
}

// ── Arrancar workers ─────────────────────────────────────────────────────────
async function startWorkers() {
  console.log(`🚀 A iniciar ${WORKERS} workers PHP...`)
  for (let i = 0; i < WORKERS; i++) {
    const port = BASE_PORT + i
    const proc = spawn(PHP, [ARTISAN, 'serve', `--port=${port}`, '--no-interaction'], {
      cwd: 'C:\\laragon\\www\\Beconnect',
      env: { ...process.env, DB_HOST: '127.0.0.1' },
      stdio: 'ignore',
      detached: false,
    })
    proc.on('error', e => console.error(`Worker ${port} error:`, e.message))
    workers.push({ port, proc })
    console.log(`   Worker ${i + 1}/${WORKERS} → porta ${port}`)
  }

  // Aguardar todos os workers ficarem prontos
  console.log('⏳ Aguardando workers ficarem prontos...')
  await Promise.all(workers.map(w => waitForPort(w.port)))
  console.log('✅ Todos os workers prontos!\n')
}

// ── Proxy reverso com round-robin ────────────────────────────────────────────
function proxyRequest(clientReq, clientRes) {
  const worker = workers[current % workers.length]
  current++

  const options = {
    hostname: '127.0.0.1',
    port: worker.port,
    path: clientReq.url,
    method: clientReq.method,
    headers: { ...clientReq.headers, host: `127.0.0.1:${worker.port}` },
  }

  const proxy = http.request(options, (proxyRes) => {
    clientRes.writeHead(proxyRes.statusCode, proxyRes.headers)
    proxyRes.pipe(clientRes)
  })

  proxy.on('error', () => {
    clientRes.writeHead(502)
    clientRes.end('Worker unavailable')
  })

  clientReq.pipe(proxy)
}

// ── Iniciar tudo ─────────────────────────────────────────────────────────────
startWorkers().then(() => {
  const server = http.createServer(proxyRequest)
  server.listen(LB_PORT, () => {
    console.log(`⚖️  Load balancer activo em http://localhost:${LB_PORT}`)
    console.log(`📊 ${WORKERS} workers nas portas ${BASE_PORT}-${BASE_PORT + WORKERS - 1}`)
    console.log(`🔥 Pronto para o teste de stress!\n`)
  })
  server.on('error', e => console.error('LB error:', e))
}).catch(e => {
  console.error('Erro ao iniciar workers:', e.message)
  process.exit(1)
})

// Cleanup ao sair
process.on('SIGINT', () => {
  console.log('\n🛑 A parar workers...')
  workers.forEach(w => { try { w.proc.kill() } catch {} })
  process.exit(0)
})
