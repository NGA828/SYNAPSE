/**
 * Shared multipart upload plumbing.
 *
 * The instance default `Content-Type: application/json` must be cleared for any
 * FormData request: axios runs `JSON.stringify(formDataToJSON(data))` on
 * FormData sent with a JSON content type (axios/lib/defaults/index.js), which
 * silently drops the files. Leaving the header unset lets the transport set
 * multipart with the correct boundary.
 */
export const multipartConfig = { headers: { 'Content-Type': undefined } }

/**
 * Build a multipart body. `files` are appended under `attachments[]` to match
 * the backend's `attachments.*` validation rules.
 */
export const toFormData = (fields, files) => {
  const body = new FormData()

  for (const [key, value] of Object.entries(fields)) {
    if (value === undefined || value === null) continue
    body.append(key, value)
  }

  for (const file of files ?? []) {
    if (file) body.append('attachments[]', file)
  }

  return body
}

/**
 * Build a multipart body for a request that also carries nested arrays — a
 * quiz and its questions, for instance.
 *
 * Nested values have to be flattened into `questions[0][prompt]` keys rather
 * than appended whole, because `FormData.append` stringifies an object to
 * "[object Object]" and `URLSearchParams` gets serialised to
 * `application/x-www-form-urlencoded`, which cannot carry files at all. Flat
 * string keys survive intact, and PHP parses the bracket notation natively —
 * which is exactly the shape Laravel's `questions.*.prompt` rules expect.
 *
 * `null` is sent as an empty string so PHP casts it back to null for a
 * `nullable` rule, which also lets a teacher clear a field.
 */
export const toNestedBody = (fields, files = []) => {
  const body = new FormData()

  const walk = (value, key) => {
    if (value === null) {
      body.append(key, '')
      return
    }
    if (Array.isArray(value)) {
      value.forEach((entry, index) => walk(entry, `${key}[${index}]`))
      return
    }
    if (typeof value === 'object') {
      for (const [child, entry] of Object.entries(value)) {
        walk(entry, key ? `${key}[${child}]` : child)
      }
      return
    }
    if (value === undefined || value === '') return
    body.append(key, String(value))
  }

  walk(fields, '')

  for (const file of files ?? []) {
    if (file) body.append('attachments[]', file)
  }

  return body
}
