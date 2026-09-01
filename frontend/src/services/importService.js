import apiClient from './apiClient.js'

/**
 * Bulk import (students / teachers) — school admin.
 *
 * Two steps, on purpose. `previewImport` writes nothing: it reports which column
 * maps to which field, which class each pupil resolves to, and which rows would
 * fail. The administrator confirms it, and `importRows` sends the same mapping
 * back so nothing is re-interpreted between the two calls.
 */

/**
 * Run an import.
 *
 * @param {'students'|'teachers'} type
 * @param {Array<Record<string, any>>} rows  Keyed by canonical field names, or by
 *   the file's own headers when `mapping` is supplied.
 * @param {Record<string, string>} [mapping]  field => the header that supplies it
 */
export async function importRows(type, rows, mapping) {
  const payload = mapping ? { type, rows, mapping } : { type, rows }
  const { data } = await apiClient.post('/admin/import', payload)
  return data
}

export async function previewImport(payload) {
  const { data } = await apiClient.post('/admin/import/preview', payload)
  return data.data
}

/**
 * Split raw CSV text into rows keyed by the file's own header row.
 *
 * The header text is kept exactly as written, because deciding what a column
 * means is the server's job. Lowercasing headers here — as this file used to —
 * is what made a French export unreadable, and it duplicates a server rule on
 * the client, which is the drift that caused the Phase 6.1 defect.
 *
 * @returns {{ headers: string[], rows: Array<Record<string, string>> }}
 */
export function parseCsvText(text) {
  const lines = String(text ?? '').split(/\r\n|\r|\n/).filter((line) => line.trim() !== '')
  if (lines.length < 2) return { headers: [], rows: [] }

  const splitLine = (line) => {
    const cells = []
    let current = ''
    let quoted = false

    for (let i = 0; i < line.length; i += 1) {
      const char = line[i]
      if (char === '"') {
        if (quoted && line[i + 1] === '"') {
          current += '"'
          i += 1
        } else {
          quoted = !quoted
        }
      } else if (char === ',' && !quoted) {
        cells.push(current)
        current = ''
      } else {
        current += char
      }
    }

    cells.push(current)
    return cells.map((cell) => cell.trim())
  }

  const headers = splitLine(lines[0])

  const rows = lines.slice(1).map((line) => {
    const cells = splitLine(line)
    const row = {}
    headers.forEach((header, index) => {
      row[header] = cells[index] ?? ''
    })
    return row
  })

  return { headers, rows }
}
