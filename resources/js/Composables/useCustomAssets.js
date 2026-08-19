import { useForm } from '@inertiajs/vue3'

export function useCustomAssets(customAssets) {
  const form = useForm({
    custom_css: customAssets?.custom_css || '',
    custom_js: customAssets?.custom_js || '',
  })

  const updateCustomAssets = (options = {}) =>
    form.put(route('custom-assets.update'), {
      preserveScroll: true,
      ...options,
    })

  return {
    form,
    updateCustomAssets,
  }
}
