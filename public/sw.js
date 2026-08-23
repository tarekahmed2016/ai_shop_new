self.addEventListener('install', (event) => {
  self.skipWaiting()
})

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim())
})

self.addEventListener('push', (event) => {
  event.waitUntil(handlePush(event))
})

self.addEventListener('notificationclick', (event) => {
  event.notification.close()
  event.waitUntil(openDestination(event))
})

const DEFAULTS = {
  matched_request: {
    title_ar: 'طلب جديد مناسب لنشاطك',
    title_en: 'New request matching your activity',
    body_ar: 'وصل طلب جديد ضمن أحد أنشطتك. اضغط لعرض التفاصيل.',
    body_en: 'A new request matches one of your activities. Tap to view details.',
    fallbackPath: '/merchant',
  },
  customer_offer_received: {
    title_ar: 'وصلك عرض جديد',
    title_en: 'New offer received',
    body_ar: 'وصل عرض جديد على طلبك. اضغط لعرض التفاصيل.',
    body_en: 'A new offer was submitted for your request. Tap to view details.',
    fallbackPath: '/customer',
  },
}

async function handlePush(event) {
  let payload = {}

  try {
    payload = event.data ? event.data.json() : {}
  } catch {
    try {
      payload = { body: event.data ? event.data.text() : '' }
    } catch {
      payload = {}
    }
  }

  const data = payload.data && typeof payload.data === 'object' ? payload.data : payload
  const type = data.type || payload.type || 'matched_request'
  const defaults = DEFAULTS[type] || DEFAULTS.matched_request
  const language = (self.navigator.language || '').toLowerCase()
  const useArabic = language.startsWith('ar')
  const title = useArabic
    ? (data.title_ar || data.title || payload.title_ar || payload.title || defaults.title_ar)
    : (data.title_en || payload.title_en || defaults.title_en)
  const body = useArabic
    ? (data.body_ar || data.body || payload.body_ar || payload.body || defaults.body_ar)
    : (data.body_en || payload.body_en || defaults.body_en)
  const tag = data.tag || payload.tag || type
  const destinationUrl = safeSameOriginUrl(
    data.destination_url || payload.destination_url || defaults.fallbackPath,
    defaults.fallbackPath,
  )

  await self.registration.showNotification(title, {
    body,
    tag,
    silent: false,
    icon: '/icons/pwa-192.png',
    badge: '/icons/pwa-192.png',
    data: {
      type,
      request_public_id: data.request_public_id || null,
      merchant_public_id: data.merchant_public_id || null,
      offer_public_id: data.offer_public_id || null,
      destination_url: destinationUrl,
    },
  })
}

async function openDestination(event) {
  const destinationUrl = safeSameOriginUrl(
    event.notification?.data?.destination_url || '/',
    '/',
  )
  const windowClients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true })

  for (const client of windowClients) {
    if ('focus' in client) {
      await client.focus()
      if ('navigate' in client && client.url !== destinationUrl) {
        try {
          await client.navigate(destinationUrl)
        } catch {
          return self.clients.openWindow(destinationUrl)
        }
      }
      return client
    }
  }

  if (self.clients.openWindow) {
    return self.clients.openWindow(destinationUrl)
  }

  return null
}

function safeSameOriginUrl(value, fallbackPath = '/') {
  try {
    const url = new URL(value, self.location.origin)
    if (url.origin !== self.location.origin) {
      return new URL(fallbackPath, self.location.origin).href
    }
    return url.href
  } catch {
    return new URL(fallbackPath, self.location.origin).href
  }
}
