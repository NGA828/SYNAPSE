import { useEffect, useRef, useState } from 'react'
import { cn } from '../../utils/cn.js'

const VARIANT_CLASS = {
  up: '',
  left: 'reveal-left',
  right: 'reveal-right',
  scale: 'reveal-scale',
}

/**
 * Scroll-reveal wrapper. Elements animate in (fade + translate) the first
 * time they enter the viewport, honouring prefers-reduced-motion.
 */
export function Reveal({ as: Tag = 'div', variant = 'up', delay = 0, className, children, ...props }) {
  const ref = useRef(null)
  // Without IntersectionObserver support, show content immediately.
  const [visible, setVisible] = useState(() => typeof IntersectionObserver === 'undefined')

  useEffect(() => {
    const node = ref.current
    if (!node || typeof IntersectionObserver === 'undefined') return undefined

    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          setVisible(true)
          observer.disconnect()
        }
      },
      { threshold: 0.12, rootMargin: '0px 0px -48px 0px' },
    )

    observer.observe(node)
    return () => observer.disconnect()
  }, [])

  return (
    <Tag
      ref={ref}
      className={cn('reveal', VARIANT_CLASS[variant], visible && 'is-visible', className)}
      style={{ '--reveal-delay': `${delay}ms` }}
      {...props}
    >
      {children}
    </Tag>
  )
}

/**
 * 3D mouse-tracking tilt card. Adds perspective + rotateX/rotateY that
 * follows the pointer, then springs back on leave.
 */
export function TiltCard({ children, className, max = 9, ...props }) {
  const ref = useRef(null)

  const handleMouseMove = (event) => {
    const node = ref.current
    if (!node) return

    const rect = node.getBoundingClientRect()
    const x = (event.clientX - rect.left) / rect.width - 0.5
    const y = (event.clientY - rect.top) / rect.height - 0.5

    node.style.transform =
      `perspective(1200px) rotateX(${(-y * max).toFixed(2)}deg) rotateY(${(x * max).toFixed(2)}deg) scale3d(1.015, 1.015, 1.015)`
  }

  const handleMouseLeave = () => {
    const node = ref.current
    if (!node) return
    node.style.transform = 'perspective(1200px) rotateX(0deg) rotateY(0deg) scale3d(1, 1, 1)'
  }

  return (
    <div
      ref={ref}
      onMouseMove={handleMouseMove}
      onMouseLeave={handleMouseLeave}
      className={cn(
        'transition-transform duration-200 ease-out will-change-transform [transform-style:preserve-3d]',
        className,
      )}
      {...props}
    >
      {children}
    </div>
  )
}
