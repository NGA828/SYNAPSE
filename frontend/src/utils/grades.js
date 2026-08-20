/**
 * Grade helpers — pure functions, mirroring the backend Grade model.
 */

export function toScore(value) {
  if (value === null || value === undefined || value === '') return null
  const num = Number(value)
  return Number.isNaN(num) ? null : num
}

/**
 * Average of the entered components (0–20 scale). Empty input → null.
 */
export function computeAverage(test1, test2, exam) {
  const scores = [toScore(test1), toScore(test2), toScore(exam)].filter((value) => value !== null)
  if (scores.length === 0) return null
  return Math.round((scores.reduce((sum, value) => sum + value, 0) / scores.length) * 100) / 100
}

export function averageVariant(value) {
  const num = Number(value)
  if (num >= 16) return 'success'
  if (num >= 12) return 'info'
  return 'warning'
}
