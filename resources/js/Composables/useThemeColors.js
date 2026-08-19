import { useForm } from '@inertiajs/vue3'
import { PUBLIC_THEME_DEFAULTS } from './usePublicTheme.js'

const defaultFormValues = (themeColors) =>
  Object.fromEntries(
    Object.keys(PUBLIC_THEME_DEFAULTS).map((key) => [
      key,
      themeColors?.[key] || PUBLIC_THEME_DEFAULTS[key],
    ]),
  )

export function useThemeColors(themeColors) {
  const form = useForm(defaultFormValues(themeColors))

  const updateThemeColors = (options = {}) =>
    form.put(route('theme-colors.update'), {
      preserveScroll: true,
      ...options,
    })

  return {
    form,
    updateThemeColors,
  }
}
