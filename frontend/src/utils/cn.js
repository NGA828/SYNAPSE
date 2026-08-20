import { clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'

/**
 * Merge Tailwind class names, deduplicating conflicts (last wins).
 */
export function cn(...inputs) {
  return twMerge(clsx(inputs))
}
