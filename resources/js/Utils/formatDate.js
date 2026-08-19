/**
 * Parse date string to Date object
 * @param {string|Date} dateStr - Date string or Date object
 * @returns {Date}
 */
export const parseDate = (dateStr) => {
    if (!dateStr) return new Date()

    // If it's already a Date object
    if (dateStr instanceof Date) return dateStr

    // If YYYY-MM-DD format
    if (typeof dateStr === 'string' && /^\d{4}-\d{2}-\d{2}/.test(dateStr)) {
        const [year, month, day] = dateStr.split('T')[0].split('-')
        return new Date(parseInt(year), parseInt(month) - 1, parseInt(day))
    }

    // Try to parse as date
    try {
        return new Date(dateStr)
    } catch {
        return new Date()
    }
}

/**
 * Format Date object to YYYY-MM-DD for backend
 * @param {Date|string} date - Date object or date string
 * @returns {string}
 */
export const formatDateForBackend = (date) => {
    if (!date) return ''
    if (!(date instanceof Date)) return date

    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
}

/**
 * Format date string to DD/MM/YYYY for display
 * @param {string|Date} dateString - Date string or Date object
 * @returns {string}
 */
export const formatDate = (dateString) => {
    if (!dateString) return ''

    try {
        // Parse YYYY-MM-DD format directly to avoid timezone issues
        if (typeof dateString === 'string' && /^\d{4}-\d{2}-\d{2}/.test(dateString)) {
            const parts = dateString.split('T')[0].split('-')
            return `${parts[2]}/${parts[1]}/${parts[0]}`
        }

        // Fallback to Date parsing for other formats
        const date = new Date(dateString)
        if (isNaN(date.getTime())) return ''

        const day = String(date.getDate()).padStart(2, '0')
        const month = String(date.getMonth() + 1).padStart(2, '0')
        const year = date.getFullYear()
        return `${day}/${month}/${year}`
    } catch {
        return ''
    }
}

/**
 * Format date-time string to DD/MM/YYYY HH:mm for display
 * @param {string|Date} dateString - Date string or Date object
 * @returns {string}
 */
export const formatDateTime = (dateString) => {
    if (!dateString) return ''

    try {
        if (typeof dateString === 'string') {
            const match = dateString.match(/^(\d{4})-(\d{2})-(\d{2})[T\s](\d{2}):(\d{2})/)

            if (match) {
                return `${match[3]}/${match[2]}/${match[1]} ${match[4]}:${match[5]}`
            }
        }

        const date = new Date(dateString)
        if (isNaN(date.getTime())) return ''

        const day = String(date.getDate()).padStart(2, '0')
        const month = String(date.getMonth() + 1).padStart(2, '0')
        const year = date.getFullYear()
        const hours = String(date.getHours()).padStart(2, '0')
        const minutes = String(date.getMinutes()).padStart(2, '0')

        return `${day}/${month}/${year} ${hours}:${minutes}`
    } catch {
        return ''
    }
}
