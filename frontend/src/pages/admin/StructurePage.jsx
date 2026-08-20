import { useState } from 'react'
import { BookMarked, CalendarDays, CalendarRange, GraduationCap, School, Scale } from 'lucide-react'
import { PageContainer } from '../../components/layout/PageContainer.jsx'
import { PageHeader } from '../../components/ui/PageHeader.jsx'
import { AcademicYearManager } from '../../components/admin/AcademicYearManager.jsx'
import { ClassManager } from '../../components/admin/ClassManager.jsx'
import { SubjectManager } from '../../components/admin/SubjectManager.jsx'
import { AssignmentManager } from '../../components/admin/AssignmentManager.jsx'
import { SemesterManager } from '../../components/admin/SemesterManager.jsx'
import { GradeComponentManager } from '../../components/admin/GradeComponentManager.jsx'
import { cn } from '../../utils/cn.js'

const TABS = [
  { id: 'years', label: 'Academic years', icon: CalendarDays, component: AcademicYearManager },
  { id: 'semesters', label: 'Semesters', icon: CalendarRange, component: SemesterManager },
  { id: 'classes', label: 'Classes', icon: School, component: ClassManager },
  { id: 'subjects', label: 'Subjects', icon: BookMarked, component: SubjectManager },
  { id: 'assignments', label: 'Teaching assignments', icon: GraduationCap, component: AssignmentManager },
  { id: 'grading', label: 'Grading', icon: Scale, component: GradeComponentManager },
]

export default function StructurePage() {
  const [tab, setTab] = useState('years')
  const ActiveManager = TABS.find((item) => item.id === tab)?.component ?? AcademicYearManager

  return (
    <PageContainer>
      <div className="space-y-6">
        <PageHeader
          title="Academic structure"
          description="Manage years, classes, subjects and teaching assignments."
        />

        <div className="scrollbar-thin flex gap-1 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-1.5">
          {TABS.map((item) => {
            const Icon = item.icon
            const active = tab === item.id
            return (
              <button
                key={item.id}
                type="button"
                onClick={() => setTab(item.id)}
                className={cn(
                  'flex shrink-0 items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium transition',
                  active ? 'bg-brand-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100',
                )}
              >
                <Icon className="size-4" aria-hidden="true" />
                {item.label}
              </button>
            )
          })}
        </div>

        <ActiveManager />
      </div>
    </PageContainer>
  )
}
