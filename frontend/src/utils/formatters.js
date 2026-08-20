export function formatInitials(name = '') {
  const initials = String(name)
    .trim()
    .split(/\s+/)
    .map((part) => part[0])
    .filter(Boolean)
    .slice(0, 2)
    .join('')
    .toUpperCase()

  return initials || '?'
}

export function formatNumber(value, locale = 'en-US') {
  const num = Number(value)

  if (value === null || value === undefined || value === '' || Number.isNaN(num)) {
    return '—'
  }

  return new Intl.NumberFormat(locale).format(num)
}

export function formatDecimal(value, digits = 1) {
  const num = Number(value)

  if (value === null || value === undefined || value === '' || Number.isNaN(num)) {
    return '—'
  }

  return num.toFixed(digits)
}

export function formatDate(value, options) {
  if (!value) return '—'

  const date = value instanceof Date ? value : new Date(value)
  if (Number.isNaN(date.getTime())) return '—'

  const defaults = { year: 'numeric', month: 'short', day: 'numeric' }
  return new Intl.DateTimeFormat('en-US', options ?? defaults).format(date)
}
