/**
 * In-memory dataset backing the development mock adapter.
 *
 * Mirrors the rows produced by `backend/database/seeders/DatabaseSeeder.php`
 * (plans, super admin, three isolated schools) so the multi-tenant SPA can be
 * demoed without the Laravel backend running.
 */

let nextId = 1000

export const nextMockId = () => ++nextId

const day = (offset) => {
  const date = new Date()
  date.setDate(date.getDate() + offset)
  return date.toISOString().slice(0, 10)
}

export const db = {
  plans: [
    { id: 1, name: 'Starter', slug: 'starter', description: 'For small schools getting started.', price: 15000, billing_interval: 'monthly', currency: 'XAF', max_students: 500, max_teachers: 20, max_classes: 30, features: ['basic_academics', 'report_cards', 'notifications'], status: 'active' },
    { id: 2, name: 'Professional', slug: 'professional', description: 'For growing schools with full document management.', price: 25000, billing_interval: 'monthly', currency: 'XAF', max_students: 2000, max_teachers: 100, max_classes: 100, features: ['basic_academics', 'report_cards', 'document_management', 'notifications', 'custom_branding'], status: 'active' },
    { id: 3, name: 'Enterprise', slug: 'enterprise', description: 'Custom limits and advanced analytics.', price: 60000, billing_interval: 'monthly', currency: 'XAF', max_students: null, max_teachers: null, max_classes: null, features: ['basic_academics', 'report_cards', 'document_management', 'notifications', 'custom_branding', 'advanced_analytics'], status: 'active' },
  ],
  schools: [
    { id: 1, name: 'SYNAPSE Default School', slug: 'default', code: null, email: null, phone: null, address: null, status: 'trial', timezone: 'Africa/Douala', primary_color: null, subscription_plan_id: null, subscription_status: 'none', subscription_started_at: null, subscription_expires_at: null },
    { id: 2, name: 'AICS Cameroon', slug: 'aics', code: 'AICS', email: 'contact@aics.cm', phone: '+237 600 000 001', address: 'Yaoundé, Centre', status: 'active', timezone: 'Africa/Douala', primary_color: '#4f46e5', subscription_plan_id: 2, subscription_status: 'active', subscription_started_at: day(-30), subscription_expires_at: day(335) },
    { id: 3, name: 'Saint Albert Comprehensive High School', slug: 'saintalbert', code: 'SACHS', email: 'info@saintalbert.edu', phone: '+237 600 000 002', address: 'Douala, Littoral', status: 'trial', timezone: 'Africa/Douala', primary_color: '#0d9488', subscription_plan_id: 1, subscription_status: 'trial', subscription_started_at: day(0), subscription_expires_at: day(14) },
    { id: 4, name: 'Demo International School', slug: 'demo', code: 'DEMO', email: null, phone: null, address: null, status: 'expired', timezone: 'Africa/Douala', primary_color: null, subscription_plan_id: 1, subscription_status: 'expired', subscription_started_at: day(-60), subscription_expires_at: day(-30) },
  ],
  subscriptions: [
    { id: 1, school_id: 2, plan_id: 2, status: 'active', start_date: day(-30), end_date: day(335), billing_interval: 'monthly', amount: 25000, currency: 'XAF' },
    { id: 2, school_id: 3, plan_id: 1, status: 'trial', start_date: day(0), end_date: day(14), billing_interval: 'monthly', amount: 15000, currency: 'XAF' },
    { id: 3, school_id: 4, plan_id: 1, status: 'expired', start_date: day(-60), end_date: day(-30), billing_interval: 'monthly', amount: 15000, currency: 'XAF' },
  ],
  payments: [],
  settings: [],
  auditLogs: [],

  users: [
    { id: 1, school_id: null, name: 'Platform Super Admin', email: 'superadmin@synapse.test', password: 'password123', role: 'super_admin' },
    { id: 2, school_id: 2, name: 'Mrs. Chen', email: 'admin@synapse.test', password: 'password123', role: 'admin' },
    { id: 3, school_id: 2, name: 'Mr. David', email: 'teacher@synapse.test', password: 'password123', role: 'teacher' },
    { id: 4, school_id: 2, name: 'Mrs. Sarah', email: 'sarah@synapse.test', password: 'password123', role: 'teacher' },
    { id: 5, school_id: 2, name: 'Mr. Felix', email: 'felix@synapse.test', password: 'password123', role: 'teacher' },
    { id: 6, school_id: 2, name: 'John Doe', email: 'student@synapse.test', password: 'password123', role: 'student' },
    { id: 7, school_id: 2, name: 'Mary Smith', email: 'mary@synapse.test', password: 'password123', role: 'student' },
    { id: 8, school_id: 2, name: 'Peter Paul', email: 'peter@synapse.test', password: 'password123', role: 'student' },
    { id: 9, school_id: 3, name: 'Mrs. Ngo Bassa', email: 'admin.saintalbert@synapse.test', password: 'password123', role: 'admin' },
    { id: 10, school_id: 3, name: 'Mr. Emeka', email: 'teacher.saintalbert@synapse.test', password: 'password123', role: 'teacher' },
    { id: 11, school_id: 3, name: 'Mary Bih', email: 'student.saintalbert@synapse.test', password: 'password123', role: 'student' },
    { id: 12, school_id: 4, name: 'Mr. Demo Admin', email: 'admin.demo@synapse.test', password: 'password123', role: 'admin' },
  ],
  teachers: [
    { id: 1, school_id: 2, user_id: 3, staff_no: 'TCH-001' },
    { id: 2, school_id: 2, user_id: 4, staff_no: 'TCH-002' },
    { id: 3, school_id: 2, user_id: 5, staff_no: 'TCH-003' },
    { id: 4, school_id: 3, user_id: 10, staff_no: 'TCH-101' },
  ],
  students: [
    { id: 1, school_id: 2, user_id: 6, matricule: 'ST2026045' },
    { id: 2, school_id: 2, user_id: 7, matricule: 'ST2026031' },
    { id: 3, school_id: 2, user_id: 8, matricule: 'ST2026028' },
    { id: 4, school_id: 3, user_id: 11, matricule: 'SA-2026-001' },
  ],
  classes: [
    { id: 1, school_id: 2, name: 'Level 1A' },
    { id: 2, school_id: 2, name: 'Level 1B' },
    { id: 3, school_id: 2, name: 'Level 2A' },
    { id: 4, school_id: 2, name: 'Level 2B' },
    { id: 5, school_id: 2, name: 'Level 3A' },
    { id: 6, school_id: 3, name: 'Form 3' },
  ],
  subjects: [
    { id: 1, school_id: 2, name: 'English', code: 'ENG' },
    { id: 2, school_id: 2, name: 'Mathematics', code: 'MAT' },
    { id: 3, school_id: 2, name: 'Physics', code: 'PHY' },
    { id: 4, school_id: 2, name: 'Computer Science', code: 'CSC' },
    { id: 5, school_id: 2, name: 'Database', code: 'DB' },
    { id: 6, school_id: 2, name: 'Networking', code: 'NET' },
    { id: 7, school_id: 2, name: 'History', code: 'HIS' },
    { id: 8, school_id: 2, name: 'Programming', code: 'PRG' },
    { id: 9, school_id: 3, name: 'Biology', code: 'BIO' },
    { id: 10, school_id: 3, name: 'Chemistry', code: 'CHE' },
  ],
  academicYears: [
    { id: 1, school_id: 2, name: '2025/2026', start_date: '2025-09-01', end_date: '2026-06-30', is_current: false },
    { id: 2, school_id: 2, name: '2026/2027', start_date: '2026-09-01', end_date: '2027-06-30', is_current: true },
    { id: 3, school_id: 3, name: '2026/2027', start_date: '2026-09-01', end_date: '2027-06-30', is_current: true },
  ],
  semesters: [
    { id: 1, school_id: 2, academic_year_id: 2, name: 'Semester 1', sequence: 1, start_date: '2026-09-01', end_date: '2027-01-31', is_current: true },
    { id: 2, school_id: 2, academic_year_id: 2, name: 'Semester 2', sequence: 2, start_date: '2027-02-01', end_date: '2027-06-30', is_current: false },
  ],
  gradeComponents: [
    { id: 1, school_id: 2, subject_id: null, name: 'Assignments', weight: 30, sequence: 1 },
    { id: 2, school_id: 2, subject_id: null, name: 'Quizzes', weight: 20, sequence: 2 },
    { id: 3, school_id: 2, subject_id: null, name: 'Midterm', weight: 20, sequence: 3 },
    { id: 4, school_id: 2, subject_id: null, name: 'Exam', weight: 30, sequence: 4 },
  ],
  gradeScores: [
    { id: 1, school_id: 2, grade_id: 1, component_id: 1, score: 16 },
    { id: 2, school_id: 2, grade_id: 1, component_id: 2, score: 15 },
    { id: 3, school_id: 2, grade_id: 1, component_id: 3, score: 16 },
    { id: 4, school_id: 2, grade_id: 1, component_id: 4, score: 17 },
  ],
  enrollments: [
    { id: 1, school_id: 2, student_id: 1, class_id: 3, academic_year_id: 1 },
    { id: 2, school_id: 2, student_id: 1, class_id: 5, academic_year_id: 2 },
    { id: 3, school_id: 2, student_id: 2, class_id: 5, academic_year_id: 2 },
    { id: 4, school_id: 2, student_id: 3, class_id: 5, academic_year_id: 2 },
    { id: 5, school_id: 3, student_id: 4, class_id: 6, academic_year_id: 3 },
  ],
  teachingAssignments: [
    { id: 1, school_id: 2, teacher_id: 1, subject_id: 1, class_id: 5, academic_year_id: 2 },
    { id: 2, school_id: 2, teacher_id: 1, subject_id: 1, class_id: 3, academic_year_id: 2 },
    { id: 3, school_id: 2, teacher_id: 1, subject_id: 7, class_id: 5, academic_year_id: 2 },
    { id: 4, school_id: 2, teacher_id: 2, subject_id: 2, class_id: 5, academic_year_id: 2 },
    { id: 5, school_id: 2, teacher_id: 2, subject_id: 2, class_id: 3, academic_year_id: 2 },
    { id: 6, school_id: 2, teacher_id: 2, subject_id: 2, class_id: 2, academic_year_id: 2 },
    { id: 7, school_id: 2, teacher_id: 3, subject_id: 5, class_id: 5, academic_year_id: 2 },
    { id: 8, school_id: 2, teacher_id: 3, subject_id: 6, class_id: 5, academic_year_id: 2 },
    { id: 9, school_id: 3, teacher_id: 4, subject_id: 9, class_id: 6, academic_year_id: 3 },
  ],
  grades: [
    { id: 1, school_id: 2, student_id: 1, subject_id: 1, class_id: 5, academic_year_id: 2, semester_id: 1, teacher_id: 1, test1: 15, test2: 16, exam: 17 },
    { id: 2, school_id: 2, student_id: 1, subject_id: 2, class_id: 5, academic_year_id: 2, semester_id: 1, teacher_id: 2, test1: 14, test2: 16, exam: 16 },
    { id: 3, school_id: 2, student_id: 1, subject_id: 5, class_id: 5, academic_year_id: 2, semester_id: 1, teacher_id: 3, test1: 16, test2: 17, exam: 18 },
    { id: 4, school_id: 2, student_id: 1, subject_id: 6, class_id: 5, academic_year_id: 2, semester_id: 1, teacher_id: 3, test1: 13, test2: 15, exam: 15 },
    { id: 5, school_id: 2, student_id: 2, subject_id: 1, class_id: 5, academic_year_id: 2, semester_id: 1, teacher_id: 1, test1: 14, test2: 15, exam: 16 },
    { id: 6, school_id: 2, student_id: 3, subject_id: 1, class_id: 5, academic_year_id: 2, semester_id: 1, teacher_id: 1, test1: 12, test2: 14, exam: 15 },
    { id: 7, school_id: 3, student_id: 4, subject_id: 9, class_id: 6, academic_year_id: 3, semester_id: null, teacher_id: 4, test1: 16, test2: 17, exam: 18 },
  ],
  timetableEntries: [
    { id: 1, school_id: 2, class_id: 5, academic_year_id: 2, subject_id: 1, day: 1, start: '08:00', end: '10:00' },
    { id: 2, school_id: 2, class_id: 5, academic_year_id: 2, subject_id: 6, day: 1, start: '10:00', end: '12:00' },
    { id: 3, school_id: 2, class_id: 5, academic_year_id: 2, subject_id: 2, day: 1, start: '14:00', end: '16:00' },
    { id: 4, school_id: 2, class_id: 5, academic_year_id: 2, subject_id: 5, day: 2, start: '08:00', end: '10:00' },
    { id: 5, school_id: 2, class_id: 5, academic_year_id: 2, subject_id: 1, day: 2, start: '10:00', end: '12:00' },
    { id: 6, school_id: 2, class_id: 5, academic_year_id: 2, subject_id: 8, day: 2, start: '14:00', end: '16:00' },
    { id: 7, school_id: 2, class_id: 5, academic_year_id: 2, subject_id: 2, day: 3, start: '08:00', end: '10:00' },
    { id: 8, school_id: 2, class_id: 5, academic_year_id: 2, subject_id: 8, day: 3, start: '10:00', end: '12:00' },
    { id: 9, school_id: 2, class_id: 5, academic_year_id: 2, subject_id: 1, day: 3, start: '14:00', end: '16:00' },
  ],
  exams: [
    { id: 1, school_id: 2, academic_year_id: 2, semester_id: 1, subject_id: 1, class_id: 5, date: '2026-09-14', start: '08:00', end: '10:00', room: 'Hall A' },
    { id: 2, school_id: 2, academic_year_id: 2, semester_id: 1, subject_id: 2, class_id: 5, date: '2026-09-15', start: '08:00', end: '10:00', room: 'Hall A' },
    { id: 3, school_id: 2, academic_year_id: 2, semester_id: 1, subject_id: 5, class_id: 5, date: '2026-09-16', start: '10:00', end: '12:00', room: 'Lab 2' },
  ],
  requests: [
    { id: 1, school_id: 2, student_id: 1, reference: 'REQ-1045', type: 'Certificate of Enrollment', reason: 'University application', status: 'submitted', admin_note: null, created_at: '2026-08-19T10:00:00' },
    { id: 2, school_id: 2, student_id: 1, reference: 'REQ-1030', type: 'Transcript Request', reason: 'Scholarship application', status: 'ready', admin_note: null, created_at: '2026-07-30T09:00:00' },
    { id: 3, school_id: 2, student_id: 2, reference: 'REQ-1044', type: 'Certificate of Enrollment', reason: 'Internship application', status: 'under_review', admin_note: null, created_at: '2026-08-18T14:00:00' },
    { id: 4, school_id: 2, student_id: 3, reference: 'REQ-1043', type: 'Certificate of Enrollment', reason: 'Visa application', status: 'approved', admin_note: null, created_at: '2026-08-17T11:00:00' },
  ],
  documents: [
    { id: 1, school_id: 2, request_id: 2, student_id: 1, title: 'Transcript Request', file_name: 'transcript-req-1030.pdf', mime_type: 'application/pdf', size: 1840, created_at: '2026-08-10T09:00:00' },
  ],
  announcements: [
    { id: 1, school_id: 2, title: 'Exam Timetable Published', body: 'The first semester examination timetable has been published.', audience: 'all', published_at: '2026-08-19T09:00:00', author: { name: 'Mrs. Chen' } },
    { id: 2, school_id: 2, title: 'Welcome to 2026/2027', body: 'Welcome back! Classes begin on Monday.', audience: 'students', published_at: '2026-09-01T08:00:00', author: { name: 'Mrs. Chen' } },
    { id: 3, school_id: 3, title: 'Welcome to Saint Albert', body: 'Term begins next week. Check your timetable.', audience: 'all', published_at: '2026-09-01T08:00:00', author: { name: 'Mrs. Ngo Bassa' } },
  ],
  notifications: [
    { id: 1, school_id: 2, user_id: 6, type: 'document_ready', title: 'Your transcript is ready', message: 'Your Transcript Request (REQ-1030) is ready to download.', data: { request_id: 2 }, read_at: null },
    { id: 2, school_id: 2, user_id: 6, type: 'request_submitted', title: 'Request submitted', message: 'Your request REQ-1045 has been submitted.', data: { request_id: 1 }, read_at: null },
    { id: 3, school_id: 2, user_id: 2, type: 'request_created', title: 'New request', message: 'John Doe submitted a "Certificate of Enrollment" request.', data: { request_id: 1 }, read_at: null },
  ],
  attendances: [],
}

// Seed a few days of demo attendance for AICS Level 3A students.
{
  const isoDay = (offset) => {
    const date = new Date()
    date.setDate(date.getDate() + offset)
    return date.toISOString().slice(0, 10)
  }
  const rows = [
    [1, 0, 'present'], [2, 0, 'present'], [3, 0, 'absent'],
    [1, -1, 'late'], [2, -1, 'present'], [3, -1, 'present'],
    [1, -2, 'present'], [2, -2, 'excused'], [3, -2, 'present'],
  ]
  rows.forEach(([studentId, offset, status], index) => {
    db.attendances.push({
      id: index + 1,
      school_id: 2,
      class_id: 5,
      student_id: studentId,
      academic_year_id: 2,
      teacher_id: 1,
      date: isoDay(offset),
      status,
      remark: null,
    })
  })
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

export const byId = (list, id) => list.find((item) => item.id === Number(id))
export const userName = (id) => db.users.find((user) => user.id === Number(id))?.name ?? null
export const schoolOf = (user) => db.schools.find((school) => school.id === Number(user?.school_id)) ?? null
export const teacherForUser = (userId, schoolId) =>
  db.teachers.find((teacher) => teacher.user_id === Number(userId) && teacher.school_id === Number(schoolId))
export const studentForUser = (userId, schoolId) =>
  db.students.find((student) => student.user_id === Number(userId) && student.school_id === Number(schoolId))

export const currentYearFor = (schoolId) =>
  db.academicYears.find((year) => year.school_id === Number(schoolId) && year.is_current) ??
  db.academicYears.find((year) => year.school_id === Number(schoolId))

export const planOf = (school) => byId(db.plans, school?.subscription_plan_id) ?? null

export const latestSubscription = (school) =>
  db.subscriptions
    .filter((subscription) => subscription.school_id === school?.id)
    .sort((a, b) => b.id - a.id)[0] ?? null

export const isSchoolActive = (school) => {
  const subscription = latestSubscription(school)
  return subscription && ['active', 'trial'].includes(subscription.status)
}

export const averageOf = (grade) => {
  const scores = [grade.test1, grade.test2, grade.exam].filter((value) => value !== null && value !== undefined)
  if (scores.length === 0) return null
  return Math.round((scores.reduce((sum, value) => sum + value, 0) / scores.length) * 100) / 100
}

/**
 * Weighted average from component scores (falls back to legacy mean).
 */
export const weightedAverageOf = (grade) => {
  const componentRows = db.gradeScores.filter((score) => score.grade_id === grade.id && score.score != null)
  if (componentRows.length > 0) {
    const weighted = componentRows.map((row) => {
      const component = byId(db.gradeComponents, row.component_id)
      return { score: row.score, weight: component?.weight ?? 0 }
    })
    const totalWeight = weighted.reduce((sum, row) => sum + row.weight, 0)
    if (totalWeight > 0) {
      const numerator = weighted.reduce((sum, row) => sum + row.score * row.weight, 0)
      return Math.round((numerator / totalWeight) * 100) / 100
    }
  }
  return averageOf(grade)
}

/**
 * Effective grade components for a subject (subject-specific first, then
 * school defaults).
 */
export const effectiveComponents = (schoolId, subjectId) => {
  const subjectSpecific = db.gradeComponents.filter(
    (component) => component.school_id === Number(schoolId) && component.subject_id === Number(subjectId),
  )
  if (subjectSpecific.length > 0) return subjectSpecific
  return db.gradeComponents.filter(
    (component) => component.school_id === Number(schoolId) && component.subject_id == null,
  )
}

export const semestersFor = (schoolId, yearId) =>
  db.semesters
    .filter((semester) => semester.school_id === Number(schoolId) && semester.academic_year_id === Number(yearId))
    .sort((a, b) => a.sequence - b.sequence)

export const currentSemesterFor = (schoolId, yearId) =>
  semestersFor(schoolId, yearId).find((semester) => semester.is_current) ?? semestersFor(schoolId, yearId)[0]

export const serializeExam = (exam) => ({
  id: exam.id,
  subject: byId(db.subjects, exam.subject_id),
  class: byId(db.classes, exam.class_id),
  semester: byId(db.semesters, exam.semester_id),
  academic_year: byId(db.academicYears, exam.academic_year_id),
  date: exam.date,
  start: exam.start,
  end: exam.end,
  room: exam.room,
})

export const classOfStudent = (studentId, schoolId) => {
  const year = currentYearFor(schoolId)
  const enrollment = db.enrollments.find(
    (entry) => entry.student_id === Number(studentId) && entry.academic_year_id === year?.id,
  )
  return enrollment ? byId(db.classes, enrollment.class_id) : null
}

export const serializeGrade = (grade) => {
  const subject = byId(db.subjects, grade.subject_id)
  return {
    subject: subject?.name,
    subject_code: subject?.code,
    semester_id: grade.semester_id ?? null,
    test1: grade.test1,
    test2: grade.test2,
    exam: grade.exam,
    components: db.gradeScores
      .filter((score) => score.grade_id === grade.id)
      .map((score) => {
        const component = byId(db.gradeComponents, score.component_id)
        return { name: component?.name, weight: component?.weight, score: score.score }
      }),
    average: weightedAverageOf(grade),
  }
}

export const serializeTimetableEntry = (entry) => ({
  id: entry.id,
  day: entry.day,
  start: entry.start,
  end: entry.end,
  subject: { id: entry.subject_id, name: byId(db.subjects, entry.subject_id)?.name },
})

export const serializeDocument = (document) => {
  const request = db.requests.find((item) => item.id === document.request_id)
  return {
    id: document.id,
    title: document.title,
    file_name: document.file_name,
    mime_type: document.mime_type,
    size: document.size,
    created_at: document.created_at ?? null,
    request: request ? { reference: request.reference } : null,
  }
}

export const getSetting = (schoolId, key, fallback = null) => {
  const row = db.settings.find((setting) => setting.school_id === Number(schoolId) && setting.key === key)
  return row ? row.value : fallback
}

export const setSetting = (schoolId, key, value) => {
  const existing = db.settings.find((setting) => setting.school_id === Number(schoolId) && setting.key === key)
  if (existing) existing.value = value
  else db.settings.push({ id: nextMockId(), school_id: Number(schoolId), key, value })
}
