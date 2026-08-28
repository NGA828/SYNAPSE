import { NavLink } from 'react-router-dom'
import {
  Award,
  BookMarked,
  BookOpen,
  CalendarCheck2,
  CalendarClock,
  CalendarDays,
  ClipboardList,
  CreditCard,
  FileText,
  GraduationCap,
  LayoutDashboard,
  Megaphone,
  School,
  Settings,
  Upload,
  Users,
} from 'lucide-react'
import { useTenant } from '../../hooks/useTenant.js'
import { useFeature } from '../../hooks/useFeature.js'
import { LogoMark } from '../brand/Logo.jsx'
import { Badge } from '../ui/Badge.jsx'
import { cn } from '../../utils/cn.js'

function useNavForRole(role) {
  const hasReportCards = useFeature('report_cards')
  const hasDocuments = useFeature('document_management')

  const student = [
    { label: 'Dashboard', icon: LayoutDashboard, to: '/student', end: true },
    { label: 'My Grades', icon: BookOpen, to: '/student/grades' },
    ...(hasReportCards ? [{ label: 'Report Card', icon: Award, to: '/student/report-card' }] : []),
    { label: 'Timetable', icon: CalendarDays, to: '/student/timetable' },
    { label: 'Requests', icon: ClipboardList, to: '/student/requests' },
    ...(hasDocuments ? [{ label: 'Documents', icon: FileText, to: '/student/documents' }] : []),
    { label: 'Attendance', icon: CalendarCheck2, to: '/student/attendance' },
    { label: 'Transcript', icon: GraduationCap, to: '/student/transcript' },
    { label: 'Exams', icon: CalendarClock, to: '/student/exams' },
    { label: 'Announcements', icon: Megaphone, to: '/student/announcements' },
  ]

  const teacher = [
    { label: 'Dashboard', icon: LayoutDashboard, to: '/teacher', end: true },
    { label: 'My Assignments', icon: BookMarked, to: '/teacher/assignments' },
    { label: 'My Schedule', icon: CalendarDays, to: '/teacher/timetable' },
    { label: 'Grade Entry', icon: BookOpen, to: '/teacher/grades' },
    { label: 'Attendance', icon: CalendarCheck2, to: '/teacher/attendance' },
    { label: 'Exams', icon: CalendarClock, to: '/teacher/exams' },
    { label: 'Announcements', icon: Megaphone, to: '/teacher/announcements' },
  ]

  const admin = [
    { label: 'Dashboard', icon: LayoutDashboard, to: '/admin', end: true },
    { label: 'Academic Structure', icon: School, to: '/admin/structure' },
    { label: 'Students', icon: Users, to: '/admin/students' },
    { label: 'Teachers', icon: GraduationCap, to: '/admin/teachers' },
    { label: 'Timetable', icon: CalendarDays, to: '/admin/timetable' },
    { label: 'Attendance', icon: CalendarCheck2, to: '/admin/attendance' },
    { label: 'Exams', icon: CalendarClock, to: '/admin/exams' },
    { label: 'Import', icon: Upload, to: '/admin/import' },
    { label: 'Requests', icon: ClipboardList, to: '/admin/requests' },
    { label: 'Announcements', icon: Megaphone, to: '/admin/announcements' },
    { label: 'Billing', icon: CreditCard, to: '/admin/billing' },
    { label: 'Settings', icon: Settings, to: '/admin/settings' },
  ]

  return { student, teacher, admin }[role] ?? []
}

function NavItem({ item, onNavigate }) {
  if (item.soon) {
    return (
      <span
        className="flex cursor-not-allowed items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-400"
        title="Coming soon"
      >
        <item.icon className="size-[18px]" aria-hidden="true" />
        <span className="flex-1">{item.label}</span>
        <Badge variant="neutral">Soon</Badge>
      </span>
    )
  }

  return (
    <NavLink
      to={item.to}
      end={item.end}
      onClick={onNavigate}
      className={({ isActive }) =>
        cn(
          'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition',
          isActive
            ? 'bg-brand-50 text-brand-700'
            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
        )
      }
    >
      <item.icon className="size-[18px]" aria-hidden="true" />
      {item.label}
    </NavLink>
  )
}

export function Sidebar({ role, onNavigate, className }) {
  const { school } = useTenant()
  const items = useNavForRole(role)

  return (
    <aside className={cn('flex h-full w-64 flex-col border-r border-slate-200 bg-white', className)}>
      <div className="flex min-h-16 items-center gap-2.5 border-b border-slate-100 px-5">
        {school?.logo ? (
          <img
            src={school.logo}
            alt={`${school.name} logo`}
            className="size-9 shrink-0 rounded-lg border border-slate-200 object-cover"
          />
        ) : (
          <LogoMark className="size-9 shrink-0" />
        )}
        <div className="min-w-0">
          <p className="truncate text-sm font-bold tracking-tight text-slate-900">
            {school?.name ?? 'SYNAPSE'}
          </p>
          <p className="text-[11px] text-slate-400">SYNAPSE platform</p>
        </div>
      </div>
      <nav className="flex-1 space-y-1 overflow-y-auto px-3 py-4">
        {items.map((item) => (
          <NavItem key={item.label} item={item} onNavigate={onNavigate} />
        ))}
      </nav>
      <div className="border-t border-slate-100 px-5 py-4">
        <p className="text-xs text-slate-400">SYNAPSE · Multi-tenant</p>
      </div>
    </aside>
  )
}
