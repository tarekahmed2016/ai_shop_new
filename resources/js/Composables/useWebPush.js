import { computed, onMounted, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'

function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
}

function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4)
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/')
  const rawData = window.atob(base64)
  const outputArray = new Uint8Array(rawData.length)

  for (let i = 0; i < rawData.length; i += 1) {
    outputArray[i] = rawData.charCodeAt(i)
  }

  return outputArray
}

function isStandaloneDisplay() {
  if (typeof window === 'undefined') {
    return false
  }

  if (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) {
    return true
  }

  if (window.matchMedia && window.matchMedia('(display-mode: fullscreen)').matches) {
    return true
  }

  if (typeof navigator !== 'undefined' && navigator.standalone === true) {
    return true
  }

  return false
}

function isAppleHandheld() {
  if (typeof navigator === 'undefined') {
    return false
  }

  const platform = navigator.platform || ''
  const ua = navigator.userAgent || ''
  const maxTouch = navigator.maxTouchPoints || 0

  if (/iPad|iPhone|iPod/.test(ua) || /iPad|iPhone|iPod/.test(platform)) {
    return true
  }

  return platform === 'MacIntel' && maxTouch > 1
}

export function useWebPush({ isActive, configRouteName, storeRouteName }) {
  const page = usePage()
  const status = ref('checking')
  const errorMessage = ref('')
  const subscriptionCount = ref(0)
  const processing = ref(false)

  const contextActive = computed(() => Boolean(isActive(page)))
  const vapidPublicKey = computed(() => page.props.webPush?.vapid_public_key || '')
  const pushSupported = computed(() => (
    typeof window !== 'undefined'
    && 'serviceWorker' in navigator
    && 'PushManager' in window
    && 'Notification' in window
  ))
  const needsStandaloneInstall = computed(() => isAppleHandheld() && !isStandaloneDisplay())

  const registerWorker = async () => {
    if (!contextActive.value || !pushSupported.value) {
      return null
    }

    return navigator.serviceWorker.register('/sw.js', { scope: '/' })
  }

  const persistSubscription = async (subscription) => {
    const json = subscription.toJSON()
    const response = await fetch(route(storeRouteName), {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify({
        endpoint: json.endpoint,
        keys: {
          p256dh: json.keys?.p256dh,
          auth: json.keys?.auth,
        },
        contentEncoding: 'aes128gcm',
      }),
    })

    if (!response.ok) {
      throw new Error('subscription_failed')
    }
  }

  const refreshStatus = async () => {
    errorMessage.value = ''

    if (!contextActive.value) {
      status.value = 'inactive'
      return
    }

    if (needsStandaloneInstall.value) {
      status.value = 'ios_install'
      return
    }

    if (!pushSupported.value) {
      status.value = 'unsupported'
      return
    }

    const permission = Notification.permission
    const registration = await registerWorker()
    const subscription = registration ? await registration.pushManager.getSubscription() : null

    if (permission === 'granted' && subscription) {
      try {
        await persistSubscription(subscription)
      } catch {
        // Keep browser permission intact; status is refreshed from local state.
      }
    }

    try {
      const response = await fetch(route(configRouteName), {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      })
      if (response.ok) {
        const payload = await response.json()
        subscriptionCount.value = payload.subscription_count || 0
      }
    } catch {
      subscriptionCount.value = 0
    }

    if (!vapidPublicKey.value) {
      status.value = 'missing_vapid'
      return
    }

    if (permission === 'denied') {
      status.value = 'denied'
      return
    }

    if (permission === 'default') {
      status.value = 'prompt'
      return
    }

    if (permission === 'granted' && subscription) {
      status.value = 'subscribed'
      return
    }

    status.value = 'missing_subscription'
  }

  const enableNotifications = async () => {
    if (processing.value || needsStandaloneInstall.value || !pushSupported.value) {
      return
    }

    processing.value = true
    errorMessage.value = ''

    try {
      const permission = await Notification.requestPermission()
      if (permission !== 'granted') {
        status.value = permission === 'denied' ? 'denied' : 'prompt'
        return
      }

      const registration = await registerWorker()
      if (!registration) {
        status.value = 'unsupported'
        return
      }

      const existing = await registration.pushManager.getSubscription()
      const subscription = existing || await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(vapidPublicKey.value),
      })

      await persistSubscription(subscription)
      status.value = 'subscribed'
    } catch (error) {
      errorMessage.value = error?.message || 'subscription_failed'
      await refreshStatus()
    } finally {
      processing.value = false
    }
  }

  onMounted(() => {
    if (contextActive.value) {
      refreshStatus()
    }
  })

  return {
    status,
    errorMessage,
    processing,
    subscriptionCount,
    contextActive,
    needsStandaloneInstall,
    enableNotifications,
    refreshStatus,
  }
}
