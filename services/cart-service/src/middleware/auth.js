'use strict'

/**
 * Middleware de autenticação leve para o Cart Service.
 * Valida o JWT emitido pelo Laravel (shared secret) ou aceita sessionId via cookie.
 *
 * Em produção, o API Gateway (nginx) verifica o JWT antes de rotear aqui,
 * portanto este middleware é uma segunda linha de defesa.
 */

const jwt = require('jsonwebtoken')

const JWT_SECRET = process.env.LARAVEL_JWT_SECRET || process.env.APP_KEY || 'beconnect_dev_secret'

function extractIdentity(req) {
  // 1. JWT no header Authorization
  const authHeader = req.headers['authorization']
  if (authHeader && authHeader.startsWith('Bearer ')) {
    const token = authHeader.slice(7)
    try {
      const payload = jwt.verify(token, JWT_SECRET)
      return { userId: String(payload.sub), sessionId: null }
    } catch {
      // token inválido — cai para sessão anónima
    }
  }

  // 2. Sessão anónima via header X-Session-Id (enviado pelo nginx/Laravel)
  const sessionId = req.headers['x-session-id'] || req.cookies?.cart_session
  if (sessionId) return { userId: null, sessionId }

  // 3. IP como último recurso (utilizadores sem sessão, sem cookie)
  const ip = req.ip || req.socket?.remoteAddress || 'anonymous'
  return { userId: null, sessionId: `ip:${ip}` }
}

module.exports = function auth(req, res, next) {
  req.identity = extractIdentity(req)
  next()
}
