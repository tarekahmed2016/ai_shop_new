/**
 * Resolve a bilingual database field for the active locale with cross-language fallback.
 *
 * @param {Record<string, unknown>|null|undefined} item
 * @param {string} field Base field name without locale suffix (e.g. "name", "description")
 * @param {string} locale Active locale code ("ar" or "en")
 * @returns {string}
 */
export function resolveBilingualField(item, field, locale) {
  if (!item) {
    return ''
  }

  const primaryKey = `${field}_${locale}`
  const fallbackLocale = locale === 'ar' ? 'en' : 'ar'
  const fallbackKey = `${field}_${fallbackLocale}`

  return item[primaryKey] || item[fallbackKey] || ''
}

/**
 * Resolve the admin table/sort field name for the active dashboard locale.
 *
 * @param {string} field Base field name without locale suffix
 * @param {string} locale Active locale code ("ar" or "en")
 * @returns {string}
 */
export function bilingualFieldKey(field, locale) {
  return `${field}_${locale}`
}
