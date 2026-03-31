export function trackEvent(eventName, payload = {}) {
  if (typeof window !== 'undefined') {
    const detail = {
      event: eventName,
      payload,
      timestamp: new Date().toISOString(),
    }

    window.dispatchEvent(new CustomEvent('analytics', { detail }))
    if (window.console && window.console.info) {
      window.console.info('[Analytics]', eventName, payload)
    }
  }
}
