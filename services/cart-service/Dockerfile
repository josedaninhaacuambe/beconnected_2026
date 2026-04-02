# ─── Build stage ──────────────────────────────────────────────────────────────
FROM node:20-alpine AS builder
WORKDIR /app
COPY package.json ./
RUN npm install --omit=dev --legacy-peer-deps

# ─── Runtime stage (imagem mínima) ────────────────────────────────────────────
FROM node:20-alpine
RUN addgroup -S beconnect && adduser -S beconnect -G beconnect
WORKDIR /app
COPY --from=builder /app/node_modules ./node_modules
COPY src ./src
COPY package.json ./

# Não corre como root
USER beconnect

EXPOSE 3001
HEALTHCHECK --interval=15s --timeout=5s --retries=3 \
  CMD wget -qO- http://localhost:3001/health || exit 1

CMD ["node", "src/server.js"]
