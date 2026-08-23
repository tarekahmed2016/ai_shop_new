import { useWebPush } from './useWebPush.js'

export function useCustomerPush() {
  const push = useWebPush({
    isActive: (page) => Boolean(page.props.customerContext),
    configRouteName: 'customer.push-subscriptions.config',
    storeRouteName: 'customer.push-subscriptions.store',
  })

  return {
    ...push,
    customerActive: push.contextActive,
  }
}
