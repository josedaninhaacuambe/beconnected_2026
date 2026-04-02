'use strict'

const Redis = require('ioredis')

// Cluster-ready: single node em dev, cluster em prod via REDIS_CLUSTER_NODES
const createClient = () => {
  const clusterNodes = process.env.REDIS_CLUSTER_NODES
  if (clusterNodes) {
    const nodes = clusterNodes.split(',').map(n => {
      const [host, port] = n.split(':')
      return { host, port: Number(port) }
    })
    return new Redis.Cluster(nodes, {
      redisOptions: { password: process.env.REDIS_PASSWORD },
      scaleReads: 'slave',
    })
  }

  return new Redis({
    host: process.env.REDIS_HOST || 'redis',
    port: Number(process.env.REDIS_PORT) || 6379,
    password: process.env.REDIS_PASSWORD || undefined,
    maxRetriesPerRequest: 3,
    enableReadyCheck: true,
    lazyConnect: false,
  })
}

const redis = createClient()

redis.on('connect',  () => process.stdout.write('[Redis] connected\n'))
redis.on('error',    (e) => process.stderr.write(`[Redis] error: ${e.message}\n`))
redis.on('reconnecting', () => process.stdout.write('[Redis] reconnecting...\n'))

module.exports = redis
