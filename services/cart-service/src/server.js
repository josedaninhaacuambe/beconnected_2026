'use strict'

require('dotenv').config()

const express     = require('express')
const helmet      = require('helmet')
const cors        = require('cors')
const rateLimit   = require('express-rate-limit')
const pino        = require('pino')
const pinoHttp    = require('pino-http')
const auth        = require('./middleware/auth')
const cartRoutes  = require('./routes/cart')

const logger = pino({ level: process.env.LOG_LEVEL || 'info' })
const app    = express()

// ─── Segurança ────────────────────────────────────────────────────────────────
app.set('trust proxy', 1)
app.use(helmet())
app.use(cors({
  origin:      (process.env.ALLOWED_ORIGINS || 'http://localhost:8000').split(','),
  credentials: true,
}))

// ─── Rate limiting — 200 req/s por IP (protege Redis de abuso) ────────────────
app.use(rateLimit({
  windowMs:         1000,      // 1 segundo
  max:              200,
  standardHeaders:  true,
  legacyHeaders:    false,
  message:          { message: 'Demasiadas requisições. Aguarda um momento.' },
}))

// ─── Parse + logging ─────────────────────────────────────────────────────────
app.use(express.json({ limit: '64kb' }))
app.use(pinoHttp({ logger }))

// ─── Rotas ────────────────────────────────────────────────────────────────────
app.use('/api/cart', auth, cartRoutes)

// Health check raiz
app.get('/health', (_, res) => res.json({ status: 'ok', service: 'cart-service' }))

// ─── Error handler global ─────────────────────────────────────────────────────
app.use((err, req, res, _next) => {
  logger.error({ err }, 'Unhandled error')
  res.status(err.status || 500).json({ message: err.message || 'Erro interno.' })
})

// ─── Graceful shutdown ────────────────────────────────────────────────────────
const server = app.listen(process.env.PORT || 3001, () => {
  logger.info(`[Cart Service] listening on port ${process.env.PORT || 3001}`)
})

const shutdown = async (signal) => {
  logger.info(`[Cart Service] ${signal} received — shutting down gracefully`)
  server.close(async () => {
    const redis = require('./redis/client')
    await redis.quit()
    process.exit(0)
  })
  setTimeout(() => process.exit(1), 10000)
}

process.on('SIGTERM', () => shutdown('SIGTERM'))
process.on('SIGINT',  () => shutdown('SIGINT'))
