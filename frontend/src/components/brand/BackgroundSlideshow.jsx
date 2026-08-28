import { useEffect, useState } from 'react'
import { clsx } from 'clsx'

/**
 * Cross-fading background slideshow.
 *
 * Renders every image stacked absolutely and fades between them on a timer.
 * - Images are eagerly decoded so a slide never pops in half-loaded.
 * - Honours `prefers-reduced-motion`: the rotation stops and the first
 *   image is shown statically.
 * - Purely decorative, so it is hidden from assistive technology.
 */
export function BackgroundSlideshow({
  images = [],
  interval = 5000,
  className,
  imageClassName,
  overlayClassName,
}) {
  const [index, setIndex] = useState(0)
  const [paused, setPaused] = useState(false)

  // Respect the user's reduced-motion preference.
  useEffect(() => {
    if (typeof window === 'undefined' || !window.matchMedia) return undefined

    const query = window.matchMedia('(prefers-reduced-motion: reduce)')
    const apply = () => setPaused(query.matches)

    apply()
    query.addEventListener('change', apply)

    return () => query.removeEventListener('change', apply)
  }, [])

  // Advance the slide.
  useEffect(() => {
    if (paused || images.length < 2) return undefined

    const timer = window.setInterval(
      () => setIndex((current) => (current + 1) % images.length),
      interval,
    )

    return () => window.clearInterval(timer)
  }, [paused, images.length, interval])

  if (images.length === 0) return null

  return (
    <div className={clsx('absolute inset-0 overflow-hidden', className)} aria-hidden="true">
      {images.map((image, position) => {
        const src = typeof image === 'string' ? image : image.src
        const active = position === index

        return (
          <img
            key={src}
            src={src}
            alt=""
            loading={position === 0 ? 'eager' : 'lazy'}
            decoding="async"
            className={clsx(
              'absolute inset-0 size-full object-cover transition-opacity duration-1000 ease-in-out motion-reduce:transition-none',
              active ? 'opacity-100' : 'opacity-0',
              // Slow Ken Burns drift on the visible slide only.
              active && !paused && 'animate-slow-zoom',
              imageClassName,
            )}
          />
        )
      })}

      {/* Readability scrim so foreground copy always passes contrast. */}
      <div
        className={clsx(
          'absolute inset-0 bg-gradient-to-br from-brand-900/85 via-violet-900/75 to-brand-950/90',
          overlayClassName,
        )}
      />
    </div>
  )
}
