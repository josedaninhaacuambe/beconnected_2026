import { ref, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

export function useAdminAlerts() {
  const alertCount = ref(0)
  const alerts = ref({ expiring_visibility: 0, unresolved_orders: 0 })
  let interval = null

  async function refresh() {
    try {
      const { data } = await axios.get('/admin/alerts/count')
      alertCount.value = data.total
      alerts.value = data
    } catch {}
  }

  onMounted(() => {
    refresh()
    interval = setInterval(refresh, 60000)
  })
  onUnmounted(() => clearInterval(interval))

  return { alertCount, alerts, refresh }
}
