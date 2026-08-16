export function formatDate(value) {
  if (!value) return ''
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return value
  return d.toLocaleDateString('it-IT')
}

export function formatDateTime(value) {
  if (!value) return ''
  // "2026-08-16 16:40:55" -> "2026-08-16T16:40:55" per un parsing affidabile su tutti i browser
  const d = new Date(value.includes('T') ? value : value.replace(' ', 'T'))
  if (Number.isNaN(d.getTime())) return value
  return d.toLocaleString('it-IT', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}
