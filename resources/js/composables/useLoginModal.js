import { reactive } from 'vue'

const state = reactive({
  visible: false,
  afterLogin: null,
})

export function useLoginModal() {
  function open(options = {}) {
    state.afterLogin = options.afterLogin ?? null
    state.visible = true
  }

  function close() {
    state.visible = false
    state.afterLogin = null
  }

  function onSuccess() {
    const cb = state.afterLogin
    close()
    if (cb) cb()
  }

  return { state, open, close, onSuccess }
}
