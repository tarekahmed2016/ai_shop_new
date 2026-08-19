import DOMPurify from 'dompurify'

const ALLOWED_TAGS = [
  'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'del',
  'span', 'ul', 'ol', 'li', 'a', 'h2', 'h3', 'h4', 'blockquote', 'hr',
  'figure', 'figcaption', 'img', 'table', 'thead', 'tbody', 'tr', 'th', 'td',
]

const ALLOWED_ATTR = [
  'href', 'target', 'rel', 'title',
  'src', 'alt', 'width', 'height',
  'class', 'style', 'colspan', 'rowspan',
]

export function sanitizeRichText(html) {
  if (!html) {
    return ''
  }

  const sanitized = DOMPurify.sanitize(html, {
    ALLOWED_TAGS,
    ALLOWED_ATTR,
  })

  if (typeof document === 'undefined') {
    return sanitized
  }

  const container = document.createElement('div')
  container.innerHTML = sanitized

  container.querySelectorAll('img').forEach((img) => {
    const src = img.getAttribute('src') || ''

    if (!isAllowedRichTextImageSrc(src)) {
      img.remove()
    }
  })

  return container.innerHTML
}

function isAllowedRichTextImageSrc(src) {
  if (!src || /^data:/i.test(src) || /^(javascript|vbscript|file):/i.test(src)) {
    return false
  }

  if (/^\/storage\/rich-text\/.+\.(?:jpe?g|png|webp)$/i.test(src)) {
    return true
  }

  try {
    const path = new URL(src, window.location.origin).pathname

    return /^\/storage\/rich-text\/.+\.(?:jpe?g|png|webp)$/i.test(path)
  } catch {
    return false
  }
}

export function plainTextFromHtml(html) {
  if (!html) {
    return ''
  }

  const sanitized = sanitizeRichText(html)

  if (typeof document === 'undefined') {
    return sanitized.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim()
  }

  const element = document.createElement('div')
  element.innerHTML = sanitized

  return (element.textContent || element.innerText || '').replace(/\s+/g, ' ').trim()
}

export function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
}
