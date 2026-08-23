import { useWebPush } from './useWebPush.js'

export function useMerchantPush() {
  const push = useWebPush({
    isActive: (page) => Boolean(page.props.merchantContext),
    configRouteName: 'merchant.push-subscriptions.config',
    storeRouteName: 'merchant.push-subscriptions.store',
  })

  return {
    ...push,
    merchantActive: push.contextActive,
  }
}
