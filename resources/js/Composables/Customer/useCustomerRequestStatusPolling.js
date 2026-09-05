import { onBeforeUnmount, ref } from 'vue'

/**
 * @param {() => string} getRequestPublicId
 * @param {object} options
 * @param {number} options.timeoutMs
 * @param {number} options.fallbackIntervalMs
 * @param {number} [options.retryIntervalMs]
 * @param {(status: object) => void} [options.onUpdate]
 * @param {(status: object) => void} [options.onSettled]
 * @param {(status: object|null) => void} [options.onTimeout]
 */
export function useCustomerRequestStatusPolling(getRequestPublicId, options = {}) {
    const timeoutMs = Number(options.timeoutMs)
    const fallbackIntervalMs = Number(options.fallbackIntervalMs)
    const retryIntervalMs = Number(options.retryIntervalMs ?? fallbackIntervalMs)

    const status = ref(null)
    const polling = ref(false)
    const error = ref(null)

    let timer = null
    let stopped = false
    let startedAt = 0

    const clearTimer = () => {
        if (timer) {
            clearTimeout(timer)
            timer = null
        }
    }

    const schedule = (ms) => {
        if (stopped) return
        clearTimer()
        timer = setTimeout(tick, Math.max(250, ms))
    }

    async function tick() {
        if (stopped) return

        const publicId = getRequestPublicId()
        if (!publicId) {
            polling.value = false

            return
        }

        try {
            const response = await fetch(route('customer.requests.classification-status', publicId), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            })

            if (!response.ok) {
                if (response.status === 404) {
                    const fallback = {
                        request_public_id: publicId,
                        status: null,
                        ai_stage: null,
                        poll: false,
                        message: null,
                        classification: null,
                        duplicate_of_request_public_id: null,
                        missing: true,
                    }
                    error.value = null
                    status.value = fallback
                    polling.value = false
                    if (typeof options.onUpdate === 'function') {
                        options.onUpdate(fallback)
                    }
                    if (typeof options.onSettled === 'function') {
                        options.onSettled(fallback)
                    }

                    return
                }

                throw new Error(`status_http_${response.status}`)
            }

            const data = await response.json()
            error.value = null
            status.value = data

            if (typeof options.onUpdate === 'function') {
                options.onUpdate(data)
            }

            if (!data.poll) {
                polling.value = false
                if (typeof options.onSettled === 'function') {
                    options.onSettled(data)
                }

                return
            }

            if (Date.now() - startedAt >= timeoutMs) {
                polling.value = false
                if (typeof options.onTimeout === 'function') {
                    options.onTimeout(data)
                }

                return
            }

            schedule(data.poll_interval_ms || fallbackIntervalMs)
        } catch {
            error.value = 'status_fetch_failed'

            if (Date.now() - startedAt >= timeoutMs) {
                polling.value = false
                if (typeof options.onTimeout === 'function') {
                    options.onTimeout(status.value)
                }

                return
            }

            schedule(retryIntervalMs)
        }
    }

    function start(initialStatus = null) {
        if (initialStatus) {
            status.value = initialStatus
        }

        if (polling.value || stopped) {
            return
        }

        stopped = false
        startedAt = Date.now()
        polling.value = true
        tick()
    }

    function stop() {
        stopped = true
        polling.value = false
        clearTimer()
    }

    onBeforeUnmount(stop)

    return { status, polling, error, start, stop }
}
