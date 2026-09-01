/**
 * Development-only mock adapter (enabled via VITE_USE_MOCK=true).
 *
 * Simulates the multi-tenant Laravel API: tenant isolation, subscription
 * enforcement and plan limits mirror the backend behaviour so the SPA can be
 * demoed end-to-end without PHP/MySQL.
 */

import {
  byId,
  classOfStudent,
  currentSemesterFor,
  currentYearFor,
  db,
  effectiveComponents,
  getSetting,
  isSchoolActive,
  latestSubscription,
  nextMockId,
  planOf,
  schoolOf,
  semestersFor,
  serializeDocument,
  GRADING,
  AT_RISK,
  serializeExam,
  serializeConversation,
  serializeMessage,
  serializeEvent,
  serializeUserBrief,
  eventVisibleToRole,
  serializeGrade,
  serializeAttachment,
  serializeHomework,
  serializeLesson,
  serializeQuiz,
  serializeQuizAttempt,
  quizClosed,
  serializeSubmission,
  serializeTimetableEntry,
  setSetting,
  storeFile,
  readFile,
  HOMEWORK_TYPE,
  SUBMISSION_TYPE,
  LESSON_TYPE,
  QUIZ_TYPE,
  DOCUMENT_TYPES,
  studentForUser,
  teacherForUser,
  userName,
  weightedAverageOf,
} from './mockDb.js'

const tokens = new Map()

const delay = (ms = 250) => new Promise((resolve) => setTimeout(resolve, ms))

const publicUser = (user) => {
  const safe = { ...user }
  delete safe.password
  return safe
}

const ok = (config, data = {}, status = 200) => ({ data, status, statusText: 'OK', headers: {}, config })

const fail = (status, message, errors = {}) => {
  const error = new Error(message)
  error.isAxiosError = true
  error.response = { status, statusText: 'Error', headers: {}, config: null, data: { message, errors } }
  return error
}

const failSubscription = () => {
  const error = new Error('subscription')
  error.isAxiosError = true
  error.response = {
    status: 403,
    statusText: 'Error',
    headers: {},
    config: null,
    data: { message: 'Your subscription has expired. Please renew your plan to continue using SYNAPSE.', code: 'subscription_required' },
  }
  return error
}

/**
 * Mirrors App\Http\Concerns\HandlesPagination: bounded page size, LIKE
 * search across the given fields, and the Laravel `{data, meta, links}`
 * envelope so the SPA behaves identically with and without the real API.
 */
/** Reads `a.b.c` off a row, so callers can search nested payloads. */
const valueAt = (row, path) =>
  path.split('.').reduce((current, key) => (current == null ? undefined : current[key]), row)

const paginate = (config, rows, searchable = []) => {
  const params = config.params ?? {}
  const term = String(params.search ?? '').trim().toLowerCase()

  const filtered = term
    ? rows.filter((row) =>
        searchable.some((field) => String(valueAt(row, field) ?? '').toLowerCase().includes(term)),
      )
    : rows

  const perPage = Math.min(Math.max(Number(params.per_page) || 15, 1), 100)
  const lastPage = Math.max(Math.ceil(filtered.length / perPage), 1)
  const page = Math.min(Math.max(Number(params.page) || 1, 1), lastPage)
  const start = (page - 1) * perPage
  const data = filtered.slice(start, start + perPage)

  return {
    data,
    links: { first: '?page=1', last: `?page=${lastPage}`, prev: null, next: null },
    meta: {
      current_page: page,
      from: filtered.length ? start + 1 : null,
      last_page: lastPage,
      per_page: perPage,
      to: filtered.length ? start + data.length : null,
      total: filtered.length,
    },
  }
}

/**
 * Builds a genuinely valid single-page PDF (correct header, xref table and
 * trailer) so "Download PDF" produces a file that really opens in mock mode.
 */
const mockPdf = (lines) => {
  const encoder = new TextEncoder()
  const byteLength = (text) => encoder.encode(text).length

  // WinAnsi-safe: fold the few typographic characters used by the UI to ASCII.
  const clean = (text) =>
    String(text)
      .replace(/[\u2013\u2014]/g, '-')
      .replace(/[\u2018\u2019]/g, "'")
      .replace(/[\u201C\u201D]/g, '"')
      .replace(/[^\x20-\x7E]/g, '?')
      .replace(/([\\()])/g, '\\$1')

  const body = lines
    .map((line, index) => `BT /F1 ${index === 0 ? 16 : 11} Tf 60 ${760 - index * 22} Td (${clean(line)}) Tj ET`)
    .join('\n')

  const objects = [
    '<< /Type /Catalog /Pages 2 0 R >>',
    '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
    '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
    `<< /Length ${byteLength(body)} >>\nstream\n${body}\nendstream`,
    '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
  ]

  let pdf = '%PDF-1.4\n'
  const offsets = []

  objects.forEach((object, index) => {
    offsets.push(byteLength(pdf))
    pdf += `${index + 1} 0 obj\n${object}\nendobj\n`
  })

  const xrefOffset = byteLength(pdf)

  pdf += `xref\n0 ${objects.length + 1}\n0000000000 65535 f \n`
  pdf += offsets.map((offset) => `${String(offset).padStart(10, '0')} 00000 n \n`).join('')
  pdf += `trailer\n<< /Size ${objects.length + 1} /Root 1 0 R >>\nstartxref\n${xrefOffset}\n%%EOF`

  return new Blob([pdf], { type: 'application/pdf' })
}

const pdfResponse = (config, filename, lines) => ({
  data: mockPdf(lines),
  status: 200,
  statusText: 'OK',
  headers: { 'content-type': 'application/pdf', 'content-disposition': `attachment; filename="${filename}"` },
  config,
})

const resetTokens = new Map()

const readBody = (config) => {
  try {
    return typeof config.data === 'string' ? JSON.parse(config.data) : (config.data ?? {})
  } catch {
    return {}
  }
}

const tokenFrom = (config) => {
  const header = config.headers?.Authorization ?? config.headers?.authorization ?? ''
  const [scheme, value] = String(header).split(' ')
  return scheme?.toLowerCase() === 'bearer' ? value : null
}

const authUser = (config) => {
  const user = db.users.find((candidate) => candidate.id === tokens.get(tokenFrom(config)))
  if (!user) throw fail(401, 'Unauthenticated.')
  return user
}

const requireRole = (config, role) => {
  const user = authUser(config)
  if (user.role !== role) throw fail(403, 'You do not have permission to access this resource.')
  return user
}

const requireTenant = (config) => {
  const user = authUser(config)
  const school = schoolOf(user)
  if (!school) throw fail(403, 'No school is associated with your account.')
  return { user, school }
}

/**
 * Admin guard. The Laravel admin route group applies role:admin but not the
 * subscription middleware, so this resolves the tenant without demanding an
 * active subscription — matching the real routes.
 */
const requireAdmin = (config) => {
  requireRole(config, 'admin')
  return requireTenant(config)
}

const requireActiveTenant = (config) => {
  const { user, school } = requireTenant(config)
  if (user.role !== 'super_admin' && !isSchoolActive(school)) throw failSubscription()
  return { user, school }
}

const validate = (body, rules) => {
  const errors = {}
  for (const field of rules) {
    if (body[field] === undefined || body[field] === null || body[field] === '') {
      errors[field] = [`The ${field.replace('_', ' ')} field is required.`]
    }
  }
  return errors
}

// ---------------------------------------------------------------------------
// Serializers (school-scoped)
// ---------------------------------------------------------------------------

const serializeStudent = (student) => ({
  id: student.id,
  name: userName(student.user_id),
  email: db.users.find((user) => user.id === student.user_id)?.email ?? null,
  matricule: student.matricule,
  class: classOfStudent(student.id, student.school_id),
  academic_year: currentYearFor(student.school_id),
})

const serializeTeacher = (teacher) => ({
  id: teacher.id,
  name: userName(teacher.user_id),
  email: db.users.find((user) => user.id === teacher.user_id)?.email ?? null,
  staff_no: teacher.staff_no,
})

const serializeAssignment = (assignment) => ({
  id: assignment.id,
  teacher: { id: assignment.teacher_id, name: userName(db.teachers.find((t) => t.id === assignment.teacher_id)?.user_id) },
  subject: byId(db.subjects, assignment.subject_id),
  class: byId(db.classes, assignment.class_id),
  academic_year: byId(db.academicYears, assignment.academic_year_id),
})

const studentsCountFor = (classId, schoolId) => {
  const year = currentYearFor(schoolId)
  return db.enrollments.filter((entry) => entry.class_id === Number(classId) && entry.academic_year_id === year?.id).length
}

const teacherAssignments = (teacher) => {
  const year = currentYearFor(teacher.school_id)
  return db.teachingAssignments
    .filter((assignment) => assignment.teacher_id === teacher.id && assignment.academic_year_id === year?.id)
    .map((assignment) => ({
      id: assignment.id,
      subject: byId(db.subjects, assignment.subject_id),
      class: byId(db.classes, assignment.class_id),
      academic_year: byId(db.academicYears, assignment.academic_year_id),
      students_count: studentsCountFor(assignment.class_id, teacher.school_id),
    }))
}

const studentGradesFor = (student, semesterId) => {
  const year = currentYearFor(student.school_id)
  const rows = db.grades
    .filter(
      (grade) =>
        grade.student_id === student.id &&
        grade.academic_year_id === year?.id &&
        (!semesterId || grade.semester_id === Number(semesterId)),
    )
    .map(serializeGrade)
  const averages = rows.map((row) => row.average).filter((value) => value !== null)
  const average = averages.length
    ? Math.round((averages.reduce((sum, value) => sum + value, 0) / averages.length) * 100) / 100
    : null
  return { rows, average }
}

const gradebookStudents = (classId, subjectId, schoolId, semesterId) => {
  const year = currentYearFor(schoolId)
  const components = effectiveComponents(schoolId, subjectId)
  return db.enrollments
    .filter((entry) => entry.class_id === Number(classId) && entry.academic_year_id === year?.id)
    .map((entry) => {
      const student = byId(db.students, entry.student_id)
      const grade = db.grades.find(
        (item) =>
          item.student_id === student.id &&
          item.subject_id === Number(subjectId) &&
          item.class_id === Number(classId) &&
          item.academic_year_id === year?.id &&
          (!semesterId || item.semester_id === Number(semesterId)),
      )
      const scores = {}
      for (const component of components) {
        const score = db.gradeScores.find(
          (row) => row.grade_id === grade?.id && row.component_id === component.id,
        )
        scores[component.id] = score?.score ?? null
      }
      return {
        id: student.id,
        name: userName(student.user_id),
        matricule: student.matricule,
        test1: grade?.test1 ?? null,
        test2: grade?.test2 ?? null,
        exam: grade?.exam ?? null,
        scores,
        average: grade ? weightedAverageOf(grade) : null,
      }
    })
    .sort((a, b) => (a.name ?? '').localeCompare(b.name ?? ''))
}

const assertAssigned = (teacher, classId, subjectId) => {
  const year = currentYearFor(teacher.school_id)
  const assigned = db.teachingAssignments.some(
    (assignment) =>
      assignment.teacher_id === teacher.id &&
      assignment.class_id === Number(classId) &&
      assignment.subject_id === Number(subjectId) &&
      assignment.academic_year_id === year?.id,
  )
  if (!assigned) throw fail(403, 'You are not assigned to teach this subject in this class.')
}

const timetableFor = (classId, schoolId) => {
  const year = currentYearFor(schoolId)
  return db.timetableEntries
    .filter((entry) => entry.class_id === Number(classId) && entry.academic_year_id === year?.id)
    .sort((a, b) => a.day - b.day || a.start.localeCompare(b.start))
    .map(serializeTimetableEntry)
}

const announcementsForUser = (user) => {
  const visible = user.role === 'admin' ? ['all', 'students', 'teachers'] : user.role === 'teacher' ? ['all', 'teachers'] : ['all', 'students']
  return db.announcements
    .filter((announcement) => announcement.school_id === user.school_id && visible.includes(announcement.audience))
    .sort((a, b) => b.id - a.id)
}

const notificationsForUser = (user) =>
  db.notifications.filter((notification) => notification.user_id === user.id).sort((a, b) => b.id - a.id)

/*
| Document-request triage. Mirrors App\Services\DocumentTypeService: classify
| the stored text, then decide whether a template may answer it at all. An
| unrecognised type is reported as needing a person rather than being issued a
| generic certificate.
*/
const DOCUMENT_KEYWORDS = [
  ['Recommendation Letter', ['recommendation', 'reference letter', 'referee', 'testimonial']],
  ['Transcript Request', ['transcript', 'academic record', 'attestation', 'statement of result']],
  ['Transfer Certificate', ['transfer']],
  ['School Leaving Certificate', ['leaving', 'graduat', 'completion of studies']],
  ['Certificate of Good Conduct', ['conduct', 'good standing', 'discipline', 'character']],
  ['Certificate of Enrollment', ['enrol', 'attendance certificate', 'proof of stud', 'registered at']],
]

const classifyDocumentType = (text) => {
  if (!text || !String(text).trim()) return null
  const trimmed = String(text).trim()
  const exact = DOCUMENT_TYPES.find((type) => type.label.toLowerCase() === trimmed.toLowerCase())
  if (exact) return exact.label
  const needle = trimmed.toLowerCase()
  for (const [label, keywords] of DOCUMENT_KEYWORDS) {
    if (keywords.some((keyword) => needle.includes(keyword))) return label
  }
  return needle.includes('other') ? 'Other' : null
}

const documentTriage = (request) => {
  const label = classifyDocumentType(request.type)
  const entry = DOCUMENT_TYPES.find((type) => type.label === label)
  const auto = Boolean(entry?.auto_generatable)

  let reason = null
  if (!entry) {
    reason = 'The requested document is not one the system can issue. A member of staff needs to read the reason and respond directly.'
  } else if (!auto) {
    reason = label === 'Recommendation Letter'
      ? 'A recommendation letter has to be written and signed by someone who teaches the student.'
      : 'This request is unspecified, so a member of staff needs to establish what is needed.'
  }

  return {
    stored_type: request.type,
    type: label,
    slug: entry?.slug ?? 'unrecognised',
    label,
    classified: Boolean(entry),
    exact: Boolean(entry) && String(entry.label).toLowerCase() === String(request.type).trim().toLowerCase(),
    auto_generatable: auto,
    needs_human: !auto,
    reason,
  }
}

const serializeStudentRequest = (request) => ({
  id: request.id,
  reference: request.reference,
  type: request.type,
  reason: request.reason,
  status: request.status,
  admin_note: request.admin_note,
  created_at: request.created_at,
  triage: documentTriage(request),
  documents: db.documents.filter((item) => item.request_id === request.id).map(serializeDocument),
})

const serializeAdminRequest = (request) => {
  const student = byId(db.students, request.student_id)
  return {
    ...serializeStudentRequest(request),
    student: { id: student.id, matricule: student.matricule, user: { name: userName(student.user_id) } },
  }
}

const usageFor = (school) => {
  const plan = planOf(school)
  return {
    students: db.students.filter((student) => student.school_id === school.id).length,
    teachers: db.teachers.filter((teacher) => teacher.school_id === school.id).length,
    classes: db.classes.filter((klass) => klass.school_id === school.id).length,
    limits: {
      students: plan?.max_students ?? null,
      teachers: plan?.max_teachers ?? null,
      classes: plan?.max_classes ?? null,
    },
  }
}

const assertCanCreate = (school, entity) => {
  const usage = usageFor(school)
  const limit = usage.limits[entity]
  if (limit !== null && limit !== undefined && usage[entity] >= limit) {
    throw fail(422, 'The given data was invalid.', {
      subscription: [`You have reached the ${entity} limit of your current plan. Upgrade your subscription to add more.`],
    })
  }
}

const serializeSchool = (school) => ({
  ...school,
  subscription_plan: planOf(school),
  users_count: db.users.filter((user) => user.school_id === school.id).length,
})

const billingPayload = (school) => ({
  plan: planOf(school),
  subscription: latestSubscription(school),
  status: latestSubscription(school)?.status ?? 'none',
  usage: usageFor(school),
  features: planOf(school)?.features ?? [],
  payments: db.payments
    .filter((payment) => payment.school_id === school.id)
    .sort((a, b) => b.id - a.id),
  available_plans: db.plans.filter((plan) => plan.status === 'active'),
  currency: 'XAF',
})

const platformStats = () => {
  const byStatus = (status) => db.schools.filter((school) => school.status === status).length
  const subs = db.subscriptions
  return {
    schools: {
      total: db.schools.length,
      active: byStatus('active'),
      trial: byStatus('trial'),
      suspended: byStatus('suspended'),
      expired: byStatus('expired'),
    },
    users: {
      total: db.users.filter((user) => user.role !== 'super_admin').length,
      students: db.users.filter((user) => user.role === 'student').length,
      teachers: db.users.filter((user) => user.role === 'teacher').length,
      admins: db.users.filter((user) => user.role === 'admin').length,
    },
    subscriptions: {
      active: subs.filter((subscription) => subscription.status === 'active').length,
      trial: subs.filter((subscription) => subscription.status === 'trial').length,
      expired: subs.filter((subscription) => subscription.status === 'expired').length,
      suspended: subs.filter((subscription) => subscription.status === 'suspended').length,
    },
    revenue: {
      mrr: Math.round(subs.filter((subscription) => ['active', 'trial'].includes(subscription.status)).reduce((sum, subscription) => sum + subscription.amount, 0)),
      currency: 'XAF',
    },
  }
}

const recordPayment = (school, amount, method = 'mock') => {
  const payment = {
    id: nextMockId(),
    school_id: school.id,
    subscription_id: latestSubscription(school)?.id ?? null,
    provider: 'mock',
    method,
    amount,
    currency: 'XAF',
    status: 'succeeded',
    reference: `MOCK-${Math.random().toString(36).slice(2, 8).toUpperCase()}`,
    sandbox: true,
    paid_at: new Date().toISOString(),
  }
  db.payments.push(payment)
  return payment
}

const activateSubscription = (school, plan, status = 'active') => {
  const previous = latestSubscription(school)
  const start = previous && new Date(previous.end_date) > new Date() ? previous.end_date : new Date().toISOString().slice(0, 10)
  const end = new Date(start)
  end.setMonth(end.getMonth() + 1)
  const subscription = {
    id: nextMockId(),
    school_id: school.id,
    plan_id: plan.id,
    status,
    start_date: start,
    end_date: end.toISOString().slice(0, 10),
    billing_interval: plan.billing_interval,
    amount: plan.price,
    currency: plan.currency,
  }
  db.subscriptions.push(subscription)
  school.subscription_plan_id = plan.id
  school.subscription_status = status
  school.subscription_expires_at = subscription.end_date
  school.status = status === 'trial' ? 'trial' : 'active'
  return subscription
}

// ---------------------------------------------------------------------------
// Handlers
// ---------------------------------------------------------------------------

function login(config) {
  const body = readBody(config)
  const user = db.users.find((candidate) => candidate.email === body.email)
  if (!user || user.password !== body.password) {
    throw fail(422, 'The given data was invalid.', { email: ['The provided credentials are incorrect.'] })
  }
  const token = `mock-token-${user.id}-${Math.random().toString(36).slice(2)}`
  tokens.set(token, user.id)
  user.last_login_at = new Date().toISOString()
  return ok(config, {
    token,
    user: publicUser(user),
    must_change_password: Boolean(user.must_change_password),
  })
}

function logout(config) {
  const token = tokenFrom(config)
  if (token) tokens.delete(token)
  return ok(config, { message: 'Logged out successfully.' })
}

function me(config) {
  return ok(config, { user: publicUser(authUser(config)) })
}

function tenantShow(config) {
  const user = authUser(config)
  if (user.role === 'super_admin') {
    return ok(config, { school: null, plan: null, subscription: null, status: 'platform', features: ['basic_academics', 'report_cards', 'document_management', 'notifications', 'custom_branding', 'advanced_analytics'], usage: null })
  }
  const school = schoolOf(user)
  if (!school) throw fail(403, 'No school is associated with your account.')
  return ok(config, {
    school,
    plan: planOf(school),
    subscription: latestSubscription(school),
    status: latestSubscription(school)?.status ?? 'none',
    features: planOf(school)?.features ?? [],
    usage: usageFor(school),
  })
}

function publicSchool(config, match) {
  const school = db.schools.find((item) => item.slug === match[1])
  if (!school) throw fail(404, 'Not found.')
  return ok(config, { school: { name: school.name, slug: school.slug, logo: school.logo ?? null, primary_color: school.primary_color, timezone: school.timezone } })
}

function onboardingPlans(config) {
  return ok(config, { data: db.plans.filter((plan) => plan.status === 'active').sort((a, b) => a.price - b.price) })
}

function onboardingRegister(config) {
  const body = readBody(config)
  const errors = { ...validate(body.school ?? {}, ['name', 'slug']), ...validate(body.admin ?? {}, ['name', 'email', 'password']) }
  if (!body.plan_id) errors.plan_id = ['The plan id field is required.']
  if (Object.keys(errors).length) throw fail(422, 'The given data was invalid.', errors)

  const school = {
    id: nextMockId(),
    name: body.school.name,
    slug: body.school.slug,
    code: null,
    email: body.school.email ?? null,
    phone: body.school.phone ?? null,
    address: body.school.address ?? null,
    logo: body.school.logo ?? null,
    status: 'trial',
    timezone: 'Africa/Douala',
    primary_color: null,
    subscription_plan_id: null,
    subscription_status: 'trial',
    subscription_started_at: new Date().toISOString().slice(0, 10),
    subscription_expires_at: null,
  }
  db.schools.push(school)

  const admin = {
    id: nextMockId(),
    school_id: school.id,
    name: body.admin.name,
    email: body.admin.email,
    password: body.admin.password,
    role: 'admin',
  }
  db.users.push(admin)

  const plan = byId(db.plans, body.plan_id)
  activateSubscription(school, plan, 'trial')

  return ok(config, { school, message: 'Your school has been created. Sign in with your administrator account to continue.', admin_email: admin.email }, 201)
}

// ---- shared ----

function listNotifications(config) {
  const user = authUser(config)
  const notifications = notificationsForUser(user)
  return ok(config, { data: notifications, unread_count: notifications.filter((notification) => !notification.read_at).length })
}

function readNotification(config, match) {
  const user = authUser(config)
  const notification = db.notifications.find((item) => item.id === Number(match[1]))
  if (!notification || notification.user_id !== user.id) throw fail(403, 'This notification does not belong to you.')
  notification.read_at = new Date().toISOString()
  return ok(config, { data: notification })
}

function readAllNotifications(config) {
  const user = authUser(config)
  db.notifications.forEach((notification) => {
    if (notification.user_id === user.id && !notification.read_at) notification.read_at = new Date().toISOString()
  })
  return ok(config, { message: 'All notifications marked as read.' })
}

function listAnnouncements(config) {
  const user = authUser(config)
  return ok(config, { data: announcementsForUser(user) })
}

// ---- student ----

function studentDashboard(config) {
  const { user, school } = requireActiveTenant(config)
  const student = studentForUser(user.id, school.id)
  if (!student) throw fail(403, 'No student profile is attached to this account.')
  const { rows, average } = studentGradesFor(student)
  const announcements = announcementsForUser(user).slice(0, 3)
  return ok(config, {
    student: { id: student.id, user_id: user.id, matricule: student.matricule },
    class: classOfStudent(student.id, school.id),
    academic_year: currentYearFor(school.id),
    summary: {
      average,
      subjects: rows.length,
      pending_requests: db.requests.filter((request) => request.student_id === student.id && ['submitted', 'under_review', 'approved'].includes(request.status)).length,
      announcements: announcements.length,
    },
    grades: rows,
    timetable: timetableFor(classOfStudent(student.id, school.id)?.id, school.id),
    announcements,
  })
}

function studentGrades(config) {
  const { user, school } = requireActiveTenant(config)
  const student = studentForUser(user.id, school.id)
  const semesterId = config.params?.semester_id
  const { rows, average } = studentGradesFor(student, semesterId)
  const year = currentYearFor(school.id)
  const semester = semesterId ? byId(db.semesters, semesterId) : null
  return ok(config, {
    class: classOfStudent(student.id, school.id),
    academic_year: year,
    semester,
    semesters: semestersFor(school.id, year?.id),
    grades: rows,
    average,
  })
}

function studentReportCard(config) {
  const { user, school } = requireActiveTenant(config)
  if (!(planOf(school)?.features ?? []).includes('report_cards')) throw fail(403, 'This feature is not available on your current plan.')
  const student = studentForUser(user.id, school.id)
  const semesterId = config.params?.semester_id
  const klass = classOfStudent(student.id, school.id)
  const { rows, average } = studentGradesFor(student, semesterId)
  let rank = null
  let classSize = 0
  if (klass) {
    const year = currentYearFor(school.id)
    const classmates = db.enrollments
      .filter((entry) => entry.class_id === klass.id && entry.academic_year_id === year?.id)
      .map((entry) => byId(db.students, entry.student_id))
    classSize = classmates.length
    const ranked = classmates
      .map((classmate) => ({ id: classmate.id, average: studentGradesFor(classmate, semesterId).average }))
      .filter((item) => item.average !== null)
      .sort((a, b) => b.average - a.average)
    const position = ranked.findIndex((item) => item.id === student.id)
    rank = position >= 0 ? position + 1 : null
  }
  return ok(config, {
    student: { id: student.id, user_id: user.id, matricule: student.matricule },
    class: klass,
    academic_year: currentYearFor(school.id),
    semester: semesterId ? byId(db.semesters, semesterId) : null,
    semesters: semestersFor(school.id, currentYearFor(school.id)?.id),
    grades: rows,
    average,
    rank,
    class_size: classSize,
  })
}

function studentTranscript(config) {
  const { user, school } = requireActiveTenant(config)
  const student = studentForUser(user.id, school.id)
  if (!student) throw fail(403, 'No student profile is attached to this account.')

  const enrollments = db.enrollments
    .filter((entry) => entry.student_id === student.id)
    .sort((a, b) => b.academic_year_id - a.academic_year_id)

  const years = enrollments.map((enrollment) => {
    const grades = db.grades
      .filter((grade) => grade.student_id === student.id && grade.academic_year_id === enrollment.academic_year_id)
      .map(serializeGrade)
    const averages = grades.map((grade) => grade.average).filter((value) => value != null)
    const average = averages.length ? Math.round((averages.reduce((sum, v) => sum + v, 0) / averages.length) * 100) / 100 : null
    return {
      academic_year: byId(db.academicYears, enrollment.academic_year_id),
      class: byId(db.classes, enrollment.class_id),
      grades,
      average,
    }
  })

  const allAverages = years.map((year) => year.average).filter((value) => value != null)
  const cumulative = allAverages.length
    ? Math.round((allAverages.reduce((sum, v) => sum + v, 0) / allAverages.length) * 100) / 100
    : null

  return ok(config, { student: { id: student.id, user_id: user.id, matricule: student.matricule }, years, cumulative })
}

function studentExams(config) {
  const { user, school } = requireActiveTenant(config)
  const student = studentForUser(user.id, school.id)
  if (!student) throw fail(403, 'No student profile is attached to this account.')
  const klass = classOfStudent(student.id, school.id)
  const year = currentYearFor(school.id)
  const exams = db.exams
    .filter((exam) => exam.school_id === school.id && exam.class_id === klass?.id && exam.academic_year_id === year?.id)
    .sort((a, b) => a.date.localeCompare(b.date) || a.start.localeCompare(b.start))
    .map(serializeExam)
  return ok(config, { class: klass, academic_year: year, exams })
}

function studentTimetable(config) {
  const { user, school } = requireActiveTenant(config)
  const student = studentForUser(user.id, school.id)
  const klass = classOfStudent(student.id, school.id)
  return ok(config, { class: klass, academic_year: currentYearFor(school.id), entries: klass ? timetableFor(klass.id, school.id) : [] })
}

function studentListRequests(config) {
  const { user, school } = requireActiveTenant(config)
  const student = studentForUser(user.id, school.id)
  return ok(config, { data: db.requests.filter((request) => request.student_id === student.id).sort((a, b) => b.id - a.id).map(serializeStudentRequest) })
}

function studentCreateRequest(config) {
  const { user, school } = requireActiveTenant(config)
  const student = studentForUser(user.id, school.id)
  const body = readBody(config)
  const errors = validate(body, ['type'])
  if (Object.keys(errors).length) throw fail(422, 'The given data was invalid.', errors)
  // Closed list, matching Rule::in(DocumentRequest::TYPES) on the server.
  if (!DOCUMENT_TYPES.some((type) => type.label === body.type)) {
    throw fail(422, 'The given data was invalid.', {
      type: ['The selected type is invalid.'],
    })
  }
  const request = {
    id: nextMockId(),
    school_id: school.id,
    student_id: student.id,
    reference: `REQ-${nextMockId() + 500}`,
    type: body.type,
    reason: body.reason ?? null,
    status: 'submitted',
    admin_note: null,
    created_at: new Date().toISOString(),
  }
  db.requests.push(request)
  db.users.filter((candidate) => candidate.school_id === school.id && candidate.role === 'admin').forEach((admin) => {
    db.notifications.push({ id: nextMockId(), school_id: school.id, user_id: admin.id, type: 'request_created', title: 'New request', message: `${user.name} submitted a "${request.type}" request.`, data: { request_id: request.id }, read_at: null })
  })
  return ok(config, { data: serializeStudentRequest(request) }, 201)
}

/*
| Report-card comments. Mirrors DeterministicCommentWriter: the same clause
| order and the same rule that a writer may describe a number but never invent
| one. There is no provider here — the mock has no network — so `ai_available`
| is always false and drafts are always `generated`.
*/
const commentNumber = (value) => {
  const text = Number(value).toFixed(2)
  return text.replace(/\.?0+$/, '')
}

const commentOrdinal = (rank) => {
  const mod100 = rank % 100
  if (mod100 >= 11 && mod100 <= 13) return `${rank}th`
  // Parens matter: `+` binds tighter than `??`, so without them the fallback
  // never fires and rank 4 renders as "4undefined".
  const suffix = { 1: 'st', 2: 'nd', 3: 'rd' }[rank % 10] ?? 'th'
  return `${rank}${suffix}`
}

const writeReportCardComment = (studentId) => {
  const student = byId(db.students, studentId)
  const year = currentYearFor(student.school_id)
  if (!student || !year) return 'No marks have been recorded for this period.'

  const rows = db.grades
    .filter((grade) => Number(grade.student_id) === Number(studentId) && Number(grade.academic_year_id) === Number(year.id))
    .map((grade) => ({
      name: byId(db.subjects, grade.subject_id)?.name ?? 'Subject',
      average: weightedAverageOf(grade),
    }))
    .filter((row) => row.average !== null && row.average !== undefined)

  if (rows.length === 0) return 'No marks have been recorded for this period.'

  const scale = GRADING.scale
  const pass = GRADING.pass_mark
  const average = rows.reduce((sum, row) => sum + row.average, 0) / rows.length
  const mention = GRADING.mentions.find((entry) => average >= entry.min)?.label ?? '—'

  const parts = [`Overall average of ${commentNumber(average)} out of ${commentNumber(scale)} — ${mention}.`]

  const enrolled = db.enrollments.filter(
    (enrollment) => Number(enrollment.student_id) === Number(studentId)
      && Number(enrollment.academic_year_id) === Number(year.id),
  )
  if (enrolled.length > 0) {
    const classmates = db.enrollments.filter(
      (other) => Number(other.class_id) === Number(enrolled[0].class_id)
        && Number(other.academic_year_id) === Number(year.id),
    )
    if (classmates.length > 1) {
      const ranked = classmates
        .map((other) => {
          const values = db.grades
            .filter((grade) => Number(grade.student_id) === Number(other.student_id)
              && Number(grade.academic_year_id) === Number(year.id))
            .map((grade) => weightedAverageOf(grade))
            .filter((value) => value !== null && value !== undefined)
          return { id: other.student_id, average: values.length ? values.reduce((a, b) => a + b, 0) / values.length : null }
        })
        .filter((row) => row.average !== null)
        .sort((a, b) => b.average - a.average)
      const position = ranked.findIndex((row) => Number(row.id) === Number(studentId)) + 1
      if (position > 0) {
        parts.push(`Ranked ${commentOrdinal(position)} of ${classmates.length} in the class.`)
      }
    }
  }

  const sorted = [...rows].sort((a, b) => b.average - a.average)
  const strongest = sorted[0]
  const weakest = sorted[sorted.length - 1]
  if (rows.length > 1 && strongest.name !== weakest.name) {
    parts.push(`Strongest result in ${strongest.name} (${commentNumber(strongest.average)}).`)
  }

  const failing = [...rows].filter((row) => row.average < pass).sort((a, b) => a.average - b.average)
  if (failing.length > 0) {
    const named = failing.slice(0, 3).map((row) => `${row.name} (${commentNumber(row.average)})`)
    parts.push(`Below the ${commentNumber(pass)} pass mark in ${named.join(' and ')}.`)
  }

  if (average >= pass * 1.6) parts.push('Excellent work; the task now is to hold this level.')
  else if (average >= pass * 1.2) parts.push('Solid results, and consistent effort would lift the average further.')
  else if (average >= pass) parts.push('Satisfactory overall, with more regular revision needed in the weaker subjects.')
  else parts.push('Additional support is recommended, starting with the subjects named above.')

  return parts.join(' ')
}

const serializeComment = (comment) => ({
  id: comment.id,
  body: comment.body,
  source: comment.source,
  is_locked: Boolean(comment.is_locked),
  subject_id: comment.subject_id ?? null,
  semester_id: comment.semester_id ?? null,
  written_by: comment.written_by ?? null,
  updated_at: comment.created_at,
})

const findComment = (studentId, subjectId = null) => db.reportCardComments.find(
  (comment) => Number(comment.student_id) === Number(studentId)
    && (comment.subject_id ?? null) === subjectId,
)

function teacherShowComment(config, match) {
  const { user, school } = requireTenant(config)
  // Laravel mounts this under the role:teacher group, so an administrator gets
  // a 403 here even though they run the school.
  if (user.role !== 'teacher') throw fail(403, 'Only teachers can access this resource.')
  const student = scopedFind(db.students, Number(match[1]), school.id)
  if (!student) throw fail(404, 'Not found.')
  if (!academicSeesStudent(user, student)) throw fail(403, 'That student is not in one of your classes.')

  const saved = findComment(student.id)

  return ok(config, {
    data: {
      comment: saved ? serializeComment(saved) : null,
      effective: saved?.is_locked ? saved.body : writeReportCardComment(student.id),
      ai_available: false,
    },
  })
}

function teacherDraftComment(config, match) {
  const { user, school } = requireTenant(config)
  // Laravel mounts this under the role:teacher group, so an administrator gets
  // a 403 here even though they run the school.
  if (user.role !== 'teacher') throw fail(403, 'Only teachers can access this resource.')
  const student = scopedFind(db.students, Number(match[1]), school.id)
  if (!student) throw fail(404, 'Not found.')
  if (!academicSeesStudent(user, student)) throw fail(403, 'That student is not in one of your classes.')

  // Drafting persists nothing, exactly as the Laravel endpoint does.
  return ok(config, {
    data: { body: writeReportCardComment(student.id), source: 'generated', ai_available: false },
  })
}

function teacherSaveComment(config, match) {
  const { user, school } = requireTenant(config)
  // Laravel mounts this under the role:teacher group, so an administrator gets
  // a 403 here even though they run the school.
  if (user.role !== 'teacher') throw fail(403, 'Only teachers can access this resource.')
  const student = scopedFind(db.students, Number(match[1]), school.id)
  if (!student) throw fail(404, 'Not found.')
  if (!academicSeesStudent(user, student)) throw fail(403, 'That student is not in one of your classes.')

  const body = readBody(config)
  const errors = validate(body, ['body'])
  if (Object.keys(errors).length) throw fail(422, 'The given data was invalid.', errors)
  if (!String(body.body).trim()) throw fail(422, 'A comment cannot be empty.', { body: ['A comment cannot be empty.'] })
  if (String(body.body).length > 400) throw fail(422, 'A comment cannot exceed 400 characters.', { body: ['A comment cannot exceed 400 characters.'] })

  const existing = findComment(student.id)
  const lock = body.lock === true || body.lock === 'true'

  if (existing) {
    // A person has now vouched for this text, so provenance changes.
    existing.body = String(body.body).trim()
    existing.source = 'teacher'
    existing.is_locked = lock
    existing.written_by = user.id
    return ok(config, { data: serializeComment(existing) })
  }

  const comment = {
    id: nextMockId(),
    school_id: school.id,
    student_id: student.id,
    subject_id: null,
    academic_year_id: currentYearFor(school.id)?.id ?? null,
    semester_id: null,
    body: String(body.body).trim(),
    source: 'teacher',
    is_locked: lock,
    written_by: user.id,
    created_at: new Date().toISOString(),
  }
  db.reportCardComments.push(comment)
  return ok(config, { data: serializeComment(comment) })
}

function studentRequestTypes(config) {
  requireActiveTenant(config)
  return ok(config, {
    data: DOCUMENT_TYPES.map((type) => ({
      label: type.label,
      slug: type.slug,
      auto_generatable: type.auto_generatable,
      note: type.auto_generatable
        ? null
        : 'Prepared by a member of staff, so allow extra time.',
    })),
  })
}

function studentListDocuments(config) {
  const { user, school } = requireActiveTenant(config)
  if (!(planOf(school)?.features ?? []).includes('document_management')) throw fail(403, 'This feature is not available on your current plan.')
  const student = studentForUser(user.id, school.id)
  return ok(config, { data: db.documents.filter((document) => document.student_id === student.id).sort((a, b) => b.id - a.id).map(serializeDocument) })
}

// ---- teacher ----

function teacherDashboard(config) {
  const { user, school } = requireActiveTenant(config)
  const teacher = teacherForUser(user.id, school.id)
  if (!teacher) throw fail(403, 'No teacher profile is attached to this account.')
  const assignments = teacherAssignments(teacher)
  return ok(config, {
    summary: {
      assignments: assignments.length,
      students: assignments.reduce((sum, assignment) => sum + assignment.students_count, 0),
      classes: new Set(assignments.map((assignment) => assignment.class.id)).size,
    },
    assignments,
  })
}

const minutesBetween = (start, end) => {
  const [startHour, startMinute] = String(start).split(':').map(Number)
  const [endHour, endMinute] = String(end).split(':').map(Number)
  return Math.max(endHour * 60 + endMinute - (startHour * 60 + startMinute), 0)
}

function teacherTimetable(config) {
  const { user, school } = requireActiveTenant(config)
  const teacher = teacherForUser(user.id, school.id)
  if (!teacher) throw fail(403, 'No teacher profile is attached to this account.')

  const year = currentYearFor(school.id)
  const pairs = new Set(
    db.teachingAssignments
      .filter((assignment) => assignment.teacher_id === teacher.id && assignment.academic_year_id === year?.id)
      .map((assignment) => `${assignment.class_id}:${assignment.subject_id}`),
  )

  const entries = db.timetableEntries
    .filter((entry) => entry.school_id === school.id && entry.academic_year_id === year?.id)
    .filter((entry) => pairs.has(`${entry.class_id}:${entry.subject_id}`))
    .sort((a, b) => a.day - b.day || a.start.localeCompare(b.start))
    .map((entry) => ({
      id: entry.id,
      day: Number(entry.day),
      start: entry.start,
      end: entry.end,
      duration_minutes: minutesBetween(entry.start, entry.end),
      subject: { id: entry.subject_id, name: byId(db.subjects, entry.subject_id)?.name },
      class: { id: entry.class_id, name: byId(db.classes, entry.class_id)?.name },
    }))

  const minutes = entries.reduce((total, entry) => total + entry.duration_minutes, 0)
  const perDay = entries.reduce((acc, entry) => ({ ...acc, [entry.day]: (acc[entry.day] ?? 0) + 1 }), {})
  const busiest = Object.entries(perDay).sort((a, b) => b[1] - a[1])[0]

  const now = new Date()
  const isoToday = now.getDay() === 0 ? 7 : now.getDay()
  const time = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`

  const conflicts = Object.values(
    entries.reduce((acc, entry) => {
      const key = `${entry.day}|${entry.start}`
      ;(acc[key] ??= { day: entry.day, start: entry.start, entries: [] }).entries.push(entry)
      return acc
    }, {}),
  ).filter((group) => group.entries.length > 1)

  return ok(config, {
    academic_year: year,
    entries,
    summary: {
      lessons: entries.length,
      classes: new Set(entries.map((entry) => entry.class.id)).size,
      subjects: new Set(entries.map((entry) => entry.subject.id)).size,
      minutes_per_week: minutes,
      hours_per_week: Math.round((minutes / 60) * 10) / 10,
      busiest_day: busiest ? Number(busiest[0]) : null,
    },
    today: entries.filter((entry) => entry.day === isoToday),
    next:
      entries.find((entry) => entry.day > isoToday || (entry.day === isoToday && entry.start > time)) ??
      entries[0] ??
      null,
    conflicts,
  })
}

function teacherAssignmentsList(config) {
  const { user, school } = requireActiveTenant(config)
  const teacher = teacherForUser(user.id, school.id)
  return ok(config, { data: teacher ? teacherAssignments(teacher) : [] })
}

// ---- attendance ----

function rosterFor(classId, schoolId, date) {
  const year = currentYearFor(schoolId)
  const klass = db.classes.find((item) => item.id === Number(classId) && item.school_id === schoolId)
  if (!klass) throw fail(404, 'Not found.')

  const students = db.enrollments
    .filter((entry) => entry.class_id === Number(classId) && entry.academic_year_id === year?.id)
    .map((entry) => {
      const student = byId(db.students, entry.student_id)
      const row = db.attendances.find(
        (a) => a.student_id === student.id && a.class_id === Number(classId) && a.date === date,
      )
      return {
        id: student.id,
        name: userName(student.user_id),
        matricule: student.matricule,
        status: row?.status ?? null,
        remark: row?.remark ?? null,
      }
    })
    .sort((a, b) => (a.name ?? '').localeCompare(b.name ?? ''))

  return { class: klass, academic_year: year, date, students }
}

function upsertAttendance(schoolId, classId, date, records, teacherId = null) {
  for (const record of records) {
    const existing = db.attendances.find(
      (a) => a.student_id === Number(record.student_id) && a.class_id === Number(classId) && a.date === date,
    )
    if (existing) {
      existing.status = record.status
      existing.remark = record.remark ?? null
      if (teacherId) existing.teacher_id = teacherId
    } else {
      db.attendances.push({
        id: nextMockId(),
        school_id: Number(schoolId),
        class_id: Number(classId),
        student_id: Number(record.student_id),
        academic_year_id: currentYearFor(schoolId)?.id,
        teacher_id: teacherId,
        date,
        status: record.status,
        remark: record.remark ?? null,
      })
    }
  }
}

function teacherAttendanceGet(config, match) {
  const { user, school } = requireActiveTenant(config)
  const teacher = teacherForUser(user.id, school.id)
  if (!teacher) throw fail(403, 'No teacher profile is attached to this account.')
  const classId = Number(match[1])
  const assigned = db.teachingAssignments.some(
    (a) => a.teacher_id === teacher.id && a.class_id === classId && a.academic_year_id === currentYearFor(school.id)?.id,
  )
  if (!assigned) throw fail(403, 'You are not assigned to teach this class.')
  const date = config.params?.date ?? new Date().toISOString().slice(0, 10)
  return ok(config, rosterFor(classId, school.id, date))
}

function teacherAttendancePost(config, match) {
  const { user, school } = requireActiveTenant(config)
  const teacher = teacherForUser(user.id, school.id)
  if (!teacher) throw fail(403, 'No teacher profile is attached to this account.')
  const classId = Number(match[1])
  const assigned = db.teachingAssignments.some(
    (a) => a.teacher_id === teacher.id && a.class_id === classId && a.academic_year_id === currentYearFor(school.id)?.id,
  )
  if (!assigned) throw fail(403, 'You are not assigned to teach this class.')
  const body = readBody(config)
  upsertAttendance(school.id, classId, body.date, body.records ?? [], teacher.id)
  return ok(config, rosterFor(classId, school.id, body.date))
}

function adminAttendanceGet(config) {
  const { user, school } = requireActiveTenant(config)
  // Laravel mounts every /admin route inside the role:admin group; requireActiveTenant only
  // checks the tenant and the subscription.
  if (user.role !== 'admin') throw fail(403, 'Only administrators can do this.')
  const classId = Number(config.params?.class_id)
  if (!classId) throw fail(422, 'The given data was invalid.', { class_id: ['The class id field is required.'] })
  const date = config.params?.date ?? new Date().toISOString().slice(0, 10)
  return ok(config, rosterFor(classId, school.id, date))
}

function adminAttendancePost(config) {
  const { user, school } = requireActiveTenant(config)
  // Laravel mounts every /admin route inside the role:admin group; requireActiveTenant only
  // checks the tenant and the subscription.
  if (user.role !== 'admin') throw fail(403, 'Only administrators can do this.')
  const body = readBody(config)
  upsertAttendance(school.id, body.class_id, body.date, body.records ?? [])
  return ok(config, rosterFor(body.class_id, school.id, body.date))
}

function studentAttendance(config) {
  const { user, school } = requireActiveTenant(config)
  const student = studentForUser(user.id, school.id)
  if (!student) throw fail(403, 'No student profile is attached to this account.')

  const rows = db.attendances
    .filter((a) => a.student_id === student.id && a.school_id === school.id)
    .sort((a, b) => b.date.localeCompare(a.date))

  const total = rows.length
  const present = rows.filter((a) => a.status === 'present').length
  const late = rows.filter((a) => a.status === 'late').length
  const absent = rows.filter((a) => a.status === 'absent').length
  const excused = rows.filter((a) => a.status === 'excused').length
  const percentage = total > 0 ? Math.round(((present + late) / total) * 1000) / 10 : null

  return ok(config, {
    academic_year: currentYearFor(school.id),
    summary: { total, present, late, absent, excused, percentage },
    recent: rows.slice(0, 14).map((row) => ({
      id: row.id,
      date: row.date,
      status: row.status,
      class: byId(db.classes, row.class_id)?.name,
    })),
  })
}

function teacherClassStudents(config, match) {
  const { user, school } = requireActiveTenant(config)
  const teacher = teacherForUser(user.id, school.id)
  const classId = Number(match[1])
  const subjectId = Number(match[2])
  const klass = db.classes.find((item) => item.id === classId && item.school_id === school.id)
  const subject = db.subjects.find((item) => item.id === subjectId && item.school_id === school.id)
  if (!klass || !subject) throw fail(404, 'Not found.')
  assertAssigned(teacher, classId, subjectId)
  const students = db.enrollments
    .filter((entry) => entry.class_id === classId && entry.academic_year_id === currentYearFor(school.id)?.id)
    .map((entry) => {
      const student = byId(db.students, entry.student_id)
      return { id: student.id, matricule: student.matricule, name: userName(student.user_id) }
    })
    .sort((a, b) => (a.name ?? '').localeCompare(b.name ?? ''))
  return ok(config, { class: klass, subject, academic_year: currentYearFor(school.id), students })
}

function teacherGradebook(config, match) {
  const { user, school } = requireActiveTenant(config)
  const teacher = teacherForUser(user.id, school.id)
  const classId = Number(match[1])
  const subjectId = Number(match[2])
  const klass = db.classes.find((item) => item.id === classId && item.school_id === school.id)
  const subject = db.subjects.find((item) => item.id === subjectId && item.school_id === school.id)
  if (!klass || !subject) throw fail(404, 'Not found.')
  assertAssigned(teacher, classId, subjectId)
  const semesterId = config.params?.semester_id
  const components = effectiveComponents(school.id, subjectId)
  return ok(config, {
    class: klass,
    subject,
    academic_year: currentYearFor(school.id),
    semester: semesterId ? byId(db.semesters, semesterId) : null,
    semesters: semestersFor(school.id, currentYearFor(school.id)?.id),
    components: components.map((component) => ({ id: component.id, name: component.name, weight: component.weight })),
    students: gradebookStudents(classId, subjectId, school.id, semesterId),
  })
}

function teacherSaveGrades(config, match) {
  const { user, school } = requireActiveTenant(config)
  const teacher = teacherForUser(user.id, school.id)
  const classId = Number(match[1])
  const subjectId = Number(match[2])
  const klass = db.classes.find((item) => item.id === classId && item.school_id === school.id)
  const subject = db.subjects.find((item) => item.id === subjectId && item.school_id === school.id)
  if (!klass || !subject) throw fail(404, 'Not found.')
  assertAssigned(teacher, classId, subjectId)
  const body = readBody(config)
  const semesterId = config.params?.semester_id
  const year = currentYearFor(school.id)
  for (const row of body.grades ?? []) {
    const legacy = { test1: row.test1 ?? null, test2: row.test2 ?? null, exam: row.exam ?? null }
    const componentScores = row.scores ?? []
    const hasLegacy = Object.values(legacy).some((value) => value !== null && value !== undefined)
    if (!hasLegacy && componentScores.length === 0) continue

    const existing = db.grades.find(
      (grade) =>
        grade.student_id === Number(row.student_id) &&
        grade.subject_id === subjectId &&
        grade.class_id === classId &&
        grade.academic_year_id === year?.id &&
        (!semesterId || grade.semester_id === Number(semesterId)),
    )
    let grade
    if (existing) {
      existing.test1 = legacy.test1
      existing.test2 = legacy.test2
      existing.exam = legacy.exam
      existing.teacher_id = teacher.id
      grade = existing
    } else {
      grade = {
        id: nextMockId(),
        school_id: school.id,
        student_id: Number(row.student_id),
        subject_id: subjectId,
        class_id: classId,
        academic_year_id: year?.id,
        semester_id: semesterId ? Number(semesterId) : null,
        teacher_id: teacher.id,
        ...legacy,
      }
      db.grades.push(grade)
    }

    for (const score of componentScores) {
      const found = db.gradeScores.find(
        (row) => row.grade_id === grade.id && row.component_id === Number(score.component_id),
      )
      if (found) found.score = score.score ?? null
      else {
        db.gradeScores.push({
          id: nextMockId(),
          school_id: school.id,
          grade_id: grade.id,
          component_id: Number(score.component_id),
          score: score.score ?? null,
        })
      }
    }
  }
  const components = effectiveComponents(school.id, subjectId)
  return ok(config, {
    class: klass,
    subject,
    academic_year: year,
    semester: semesterId ? byId(db.semesters, semesterId) : null,
    components: components.map((component) => ({ id: component.id, name: component.name, weight: component.weight })),
    students: gradebookStudents(classId, subjectId, school.id, semesterId),
  })
}

// ---- school admin ----

function adminDashboard(config) {
  const { user, school } = requireActiveTenant(config)
  // Laravel mounts every /admin route inside the role:admin group; requireActiveTenant only
  // checks the tenant and the subscription.
  if (user.role !== 'admin') throw fail(403, 'Only administrators can do this.')
  return ok(config, {
    summary: {
      students: db.students.filter((student) => student.school_id === school.id).length,
      teachers: db.teachers.filter((teacher) => teacher.school_id === school.id).length,
      classes: db.classes.filter((klass) => klass.school_id === school.id).length,
      subjects: db.subjects.filter((subject) => subject.school_id === school.id).length,
      pending_requests: db.requests.filter((request) => request.school_id === school.id && ['submitted', 'under_review', 'approved'].includes(request.status)).length,
    },
  })
}

function billingGet(config) {
  const { school } = requireTenant(config)
  return ok(config, billingPayload(school))
}

function billingUpgrade(config) {
  const { school } = requireTenant(config)
  const body = readBody(config)
  const plan = byId(db.plans, body.plan_id)
  if (!plan) throw fail(422, 'The given data was invalid.', { plan_id: ['The selected plan is invalid.'] })
  recordPayment(school, plan.price, body.method ?? 'mock')
  activateSubscription(school, plan, 'active')
  return ok(config, billingPayload(school))
}

function billingRenew(config) {
  const { school } = requireTenant(config)
  const plan = planOf(school)
  if (!plan) throw fail(409, 'No plan is attached to this school.')
  recordPayment(school, plan.price, 'mock')
  activateSubscription(school, plan, 'active')
  return ok(config, billingPayload(school))
}

function readSettings(school) {
  return {
    grading_scale: getSetting(school.id, 'grading_scale', '0-20'),
    semester_structure: getSetting(school.id, 'semester_structure', '2 semesters'),
    custom_branding_enabled: getSetting(school.id, 'custom_branding_enabled', false),
    notification_preferences: getSetting(school.id, 'notification_preferences', {}),
    primary_color: school.primary_color ?? '#4f46e5',
    timezone: school.timezone ?? 'Africa/Douala',
    logo: school.logo ?? null,
    name: school.name ?? null,
    email: school.email ?? null,
    phone: school.phone ?? null,
    address: school.address ?? null,
  }
}

function settingsGet(config) {
  const { school } = requireTenant(config)
  return ok(config, { data: readSettings(school) })
}

function settingsUpdate(config) {
  const { school } = requireTenant(config)
  const body = readBody(config)
  const settings = body.settings ?? {}
  for (const [key, value] of Object.entries(settings)) setSetting(school.id, key, value)
  if (settings.primary_color) school.primary_color = settings.primary_color
  if (settings.timezone) school.timezone = settings.timezone

  if (body.logo !== undefined) school.logo = body.logo || null
  if (body.name !== undefined) school.name = body.name
  if (body.email !== undefined) school.email = body.email
  if (body.phone !== undefined) school.phone = body.phone
  if (body.address !== undefined) school.address = body.address

  return ok(config, { message: 'Settings updated.', data: readSettings(school) })
}

function adminListAcademicYears(config) {
  const { user, school } = requireActiveTenant(config)
  // Laravel mounts every /admin route inside the role:admin group; requireActiveTenant only
  // checks the tenant and the subscription.
  if (user.role !== 'admin') throw fail(403, 'Only administrators can do this.')
  return ok(config, { data: db.academicYears.filter((year) => year.school_id === school.id).sort((a, b) => b.name.localeCompare(a.name)) })
}

function adminCreateAcademicYear(config) {
  const { user, school } = requireActiveTenant(config)
  // Laravel mounts every /admin route inside the role:admin group; requireActiveTenant only
  // checks the tenant and the subscription.
  if (user.role !== 'admin') throw fail(403, 'Only administrators can do this.')
  const body = readBody(config)
  const errors = validate(body, ['name'])
  if (Object.keys(errors).length) throw fail(422, 'The given data was invalid.', errors)
  const year = { id: nextMockId(), school_id: school.id, name: body.name, start_date: body.start_date ?? null, end_date: body.end_date ?? null, is_current: false }
  db.academicYears.push(year)
  if (body.is_current) {
    db.academicYears.forEach((item) => { if (item.school_id === school.id) item.is_current = item.id === year.id })
  }
  return ok(config, { data: year }, 201)
}

function adminActivateYear(config, match) {
  const { user, school } = requireActiveTenant(config)
  // Laravel mounts every /admin route inside the role:admin group; requireActiveTenant only
  // checks the tenant and the subscription.
  if (user.role !== 'admin') throw fail(403, 'Only administrators can do this.')
  const id = Number(match[1])
  db.academicYears.forEach((item) => { if (item.school_id === school.id) item.is_current = item.id === id })
  return ok(config, { data: db.academicYears.find((item) => item.id === id) })
}

function adminUpdateAcademicYear(config, match) {
  const { user, school } = requireActiveTenant(config)
  // Laravel mounts every /admin route inside the role:admin group; requireActiveTenant only
  // checks the tenant and the subscription.
  if (user.role !== 'admin') throw fail(403, 'Only administrators can do this.')
  const year = db.academicYears.find((item) => item.id === Number(match[1]) && item.school_id === school.id)
  if (!year) throw fail(404, 'Not found.')
  const body = readBody(config)
  const errors = validate(body, ['name'])
  if (Object.keys(errors).length) throw fail(422, 'The given data was invalid.', errors)
  year.name = body.name
  year.start_date = body.start_date ?? null
  year.end_date = body.end_date ?? null
  return ok(config, { data: year })
}

function adminDeleteAcademicYear(config, match) {
  const { user, school } = requireActiveTenant(config)
  // Laravel mounts every /admin route inside the role:admin group; requireActiveTenant only
  // checks the tenant and the subscription.
  if (user.role !== 'admin') throw fail(403, 'Only administrators can do this.')
  const id = Number(match[1])
  const year = db.academicYears.find((item) => item.id === id && item.school_id === school.id)
  if (!year) throw fail(404, 'Not found.')
  if (year.is_current) throw fail(422, 'The current academic year cannot be deleted. Set another year as current first.')
  db.academicYears = db.academicYears.filter((item) => item.id !== id || item.school_id !== school.id)
  return ok(config, { message: 'Academic year removed.' })
}

function adminListClasses(config) {
  const { user, school } = requireActiveTenant(config)
  // Laravel mounts every /admin route inside the role:admin group; requireActiveTenant only
  // checks the tenant and the subscription.
  if (user.role !== 'admin') throw fail(403, 'Only administrators can do this.')
  return ok(config, { data: db.classes.filter((klass) => klass.school_id === school.id).sort((a, b) => a.name.localeCompare(b.name)) })
}

function adminCreateClass(config) {
  const { user, school } = requireActiveTenant(config)
  // Laravel mounts every /admin route inside the role:admin group; requireActiveTenant only
  // checks the tenant and the subscription.
  if (user.role !== 'admin') throw fail(403, 'Only administrators can do this.')
  const body = readBody(config)
  const errors = validate(body, ['name'])
  if (Object.keys(errors).length) throw fail(422, 'The given data was invalid.', errors)
  assertCanCreate(school, 'classes')
  const item = { id: nextMockId(), school_id: school.id, name: body.name }
  db.classes.push(item)
  return ok(config, { data: item }, 201)
}

function adminListSubjects(config) {
  const { user, school } = requireActiveTenant(config)
  // Laravel mounts every /admin route inside the role:admin group; requireActiveTenant only
  // checks the tenant and the subscription.
  if (user.role !== 'admin') throw fail(403, 'Only administrators can do this.')
  return ok(config, { data: db.subjects.filter((subject) => subject.school_id === school.id).sort((a, b) => a.name.localeCompare(b.name)) })
}

function adminCreateSubject(config) {
  const { user, school } = requireActiveTenant(config)
  // Laravel mounts every /admin route inside the role:admin group; requireActiveTenant only
  // checks the tenant and the subscription.
  if (user.role !== 'admin') throw fail(403, 'Only administrators can do this.')
  const body = readBody(config)
  const errors = validate(body, ['name'])
  if (Object.keys(errors).length) throw fail(422, 'The given data was invalid.', errors)
  const item = { id: nextMockId(), school_id: school.id, name: body.name, code: body.code ?? null }
  db.subjects.push(item)
  return ok(config, { data: item }, 201)
}

function adminUpdateSubject(config, match) {
  const { user, school } = requireActiveTenant(config)
  // Laravel mounts every /admin route inside the role:admin group; requireActiveTenant only
  // checks the tenant and the subscription.
  if (user.role !== 'admin') throw fail(403, 'Only administrators can do this.')
  const subject = db.subjects.find((item) => item.id === Number(match[1]) && item.school_id === school.id)
  if (!subject) throw fail(404, 'Not found.')
  const body = readBody(config)
  const errors = validate(body, ['name'])
  if (Object.keys(errors).length) throw fail(422, 'The given data was invalid.', errors)
  subject.name = body.name
  subject.code = body.code ?? null
  return ok(config, { data: subject })
}

function adminDeleteSubject(config, match) {
  const { user, school } = requireActiveTenant(config)
  // Laravel mounts every /admin route inside the role:admin group; requireActiveTenant only
  // checks the tenant and the subscription.
  if (user.role !== 'admin') throw fail(403, 'Only administrators can do this.')
  const id = Number(match[1])
  db.subjects = db.subjects.filter((item) => !(item.id === id && item.school_id === school.id))
  db.teachingAssignments = db.teachingAssignments.filter((item) => !(item.subject_id === id && item.school_id === school.id))
  db.timetableEntries = db.timetableEntries.filter((item) => !(item.subject_id === id && item.school_id === school.id))
  return ok(config, { message: 'Subject removed.' })
}

function adminListTeachers(config) {
  const { user, school } = requireActiveTenant(config)
  // Laravel mounts every /admin route inside the role:admin group; requireActiveTenant only
  // checks the tenant and the subscription.
  if (user.role !== 'admin') throw fail(403, 'Only administrators can do this.')
  const rows = db.teachers.filter((teacher) => teacher.school_id === school.id).map(serializeTeacher)
  return ok(config, paginate(config, rows, ['name', 'email', 'staff_no']))
}

function adminCreateTeacher(config) {
  const { user, school } = requireActiveTenant(config)
  // Laravel mounts every /admin route inside the role:admin group; requireActiveTenant only
  // checks the tenant and the subscription.
  if (user.role !== 'admin') throw fail(403, 'Only administrators can do this.')
  const body = readBody(config)
  const errors = validate(body, ['name', 'email', 'password'])
  if (Object.keys(errors).length) throw fail(422, 'The given data was invalid.', errors)
  assertCanCreate(school, 'teachers')
  const userId = nextMockId()
  db.users.push({ id: userId, school_id: school.id, name: body.name, email: body.email, password: body.password, role: 'teacher' })
  const teacher = { id: nextMockId(), school_id: school.id, user_id: userId, staff_no: body.staff_no ?? null }
  db.teachers.push(teacher)
  return ok(config, { data: serializeTeacher(teacher) }, 201)
}

function adminListStudents(config) {
  const { user, school } = requireActiveTenant(config)
  // Laravel mounts every /admin route inside the role:admin group; requireActiveTenant only
  // checks the tenant and the subscription.
  if (user.role !== 'admin') throw fail(403, 'Only administrators can do this.')
  const rows = db.students.filter((student) => student.school_id === school.id).map(serializeStudent)
  return ok(config, paginate(config, rows, ['name', 'email', 'matricule']))
}

function adminCreateStudent(config) {
  const { user, school } = requireActiveTenant(config)
  // Laravel mounts every /admin route inside the role:admin group; requireActiveTenant only
  // checks the tenant and the subscription.
  if (user.role !== 'admin') throw fail(403, 'Only administrators can do this.')
  const body = readBody(config)
  const errors = validate(body, ['name', 'email', 'password', 'matricule', 'class_id'])
  if (Object.keys(errors).length) throw fail(422, 'The given data was invalid.', errors)
  assertCanCreate(school, 'students')
  const klass = db.classes.find((item) => item.id === Number(body.class_id) && item.school_id === school.id)
  if (!klass) throw fail(422, 'The given data was invalid.', { class_id: ['The selected class is invalid.'] })
  const userId = nextMockId()
  db.users.push({ id: userId, school_id: school.id, name: body.name, email: body.email, password: body.password, role: 'student' })
  const student = { id: nextMockId(), school_id: school.id, user_id: userId, matricule: body.matricule }
  db.students.push(student)
  db.enrollments.push({ id: nextMockId(), school_id: school.id, student_id: student.id, class_id: Number(body.class_id), academic_year_id: currentYearFor(school.id)?.id })
  return ok(config, { data: serializeStudent(student) }, 201)
}

function adminListAssignments(config) {
  const { user, school } = requireActiveTenant(config)
  // Laravel mounts every /admin route inside the role:admin group; requireActiveTenant only
  // checks the tenant and the subscription.
  if (user.role !== 'admin') throw fail(403, 'Only administrators can do this.')
  return ok(config, { data: db.teachingAssignments.filter((assignment) => assignment.school_id === school.id).map(serializeAssignment).sort((a, b) => b.id - a.id) })
}

function adminCreateAssignment(config) {
  const { user, school } = requireActiveTenant(config)
  // Laravel mounts every /admin route inside the role:admin group; requireActiveTenant only
  // checks the tenant and the subscription.
  if (user.role !== 'admin') throw fail(403, 'Only administrators can do this.')
  const body = readBody(config)
  const errors = validate(body, ['teacher_id', 'subject_id', 'class_id'])
  if (Object.keys(errors).length) throw fail(422, 'The given data was invalid.', errors)
  const assignment = {
    id: nextMockId(),
    school_id: school.id,
    teacher_id: Number(body.teacher_id),
    subject_id: Number(body.subject_id),
    class_id: Number(body.class_id),
    academic_year_id: Number(body.academic_year_id ?? currentYearFor(school.id)?.id),
  }
  db.teachingAssignments.push(assignment)
  return ok(config, { data: serializeAssignment(assignment) }, 201)
}

function adminDeleteAssignment(config, match) {
  const { user, school } = requireActiveTenant(config)
  // Laravel mounts every /admin route inside the role:admin group; requireActiveTenant only
  // checks the tenant and the subscription.
  if (user.role !== 'admin') throw fail(403, 'Only administrators can do this.')
  const id = Number(match[1])
  db.teachingAssignments = db.teachingAssignments.filter((assignment) => !(assignment.id === id && assignment.school_id === school.id))
  return ok(config, { message: 'Assignment removed.' })
}

function adminTimetable(config) {
  const { user, school } = requireActiveTenant(config)
  // Laravel mounts every /admin route inside the role:admin group; requireActiveTenant only
  // checks the tenant and the subscription.
  if (user.role !== 'admin') throw fail(403, 'Only administrators can do this.')
  const classId = Number(config.params?.class_id)
  if (!classId) throw fail(422, 'The given data was invalid.', { class_id: ['The class id field is required.'] })
  const klass = db.classes.find((item) => item.id === classId && item.school_id === school.id)
  if (!klass) throw fail(404, 'Not found.')
  return ok(config, { class: klass, academic_year: currentYearFor(school.id), entries: timetableFor(classId, school.id) })
}

/*
| Timetable overlap detection. Mirrors TimetableService::assertNoOverlap: two
| intervals clash when each starts before the other ends, and back to back is
| not a clash. Compared on minutes since midnight, because "08:00" and "08:00:00"
| do not compare correctly as strings at the boundary.
*/
const timeToMinutes = (value) => {
  const match = /^(\d{1,2}):(\d{2})(?::(\d{2}))?$/.exec(String(value ?? '').trim())
  return match ? Number(match[1]) * 60 + Number(match[2]) : null
}

const findTimetableClash = (schoolId, classId, yearId, day, start, end, ignoreId = null) => db.timetableEntries
  .filter((entry) => entry.school_id === schoolId
    && Number(entry.class_id) === Number(classId)
    && Number(entry.academic_year_id) === Number(yearId)
    && Number(entry.day) === Number(day)
    && (ignoreId === null || entry.id !== Number(ignoreId)))
  .find((entry) => start < timeToMinutes(entry.end) && end > timeToMinutes(entry.start))

const assertNoTimetableClash = (schoolId, classId, yearId, body, ignoreId = null) => {
  const start = timeToMinutes(body.start)
  const end = timeToMinutes(body.end)
  if (start === null || end === null) {
    throw fail(422, 'The given data was invalid.', { start: ['Both a start and an end time are required.'] })
  }
  if (end <= start) {
    throw fail(422, 'The given data was invalid.', { end: ['The end time must be after the start time.'] })
  }
  const clash = findTimetableClash(schoolId, classId, yearId, body.day, start, end, ignoreId)
  if (!clash) return
  const subject = db.subjects.find((item) => item.id === Number(clash.subject_id))
  throw fail(422, 'The given data was invalid.', {
    start: [`That clashes with ${subject?.name ?? 'another lesson'}, which already occupies `
      + `${String(clash.start).slice(0, 5)}–${String(clash.end).slice(0, 5)} on this day.`],
  })
}

function adminCreateTimetableEntry(config) {
  const { user, school } = requireActiveTenant(config)
  if (user.role !== 'admin') throw fail(403, 'Only administrators can edit the timetable.')
  const body = readBody(config)
  const errors = validate(body, ['class_id', 'subject_id', 'day', 'start', 'end'])
  if (Object.keys(errors).length) throw fail(422, 'The given data was invalid.', errors)
  const yearId = currentYearFor(school.id)?.id
  assertNoTimetableClash(school.id, Number(body.class_id), yearId, body)
  const entry = { id: nextMockId(), school_id: school.id, class_id: Number(body.class_id), academic_year_id: yearId, subject_id: Number(body.subject_id), day: Number(body.day), start: body.start, end: body.end }
  db.timetableEntries.push(entry)
  return ok(config, { data: serializeTimetableEntry(entry) }, 201)
}

function adminUpdateTimetableEntry(config, match) {
  const { user, school } = requireActiveTenant(config)
  if (user.role !== 'admin') throw fail(403, 'Only administrators can edit the timetable.')
  const entry = db.timetableEntries.find(
    (item) => item.id === Number(match[1]) && item.school_id === school.id,
  )
  if (!entry) throw fail(404, 'Not found.')
  const body = readBody(config)
  const errors = validate(body, ['class_id', 'subject_id', 'day', 'start', 'end'])
  if (Object.keys(errors).length) throw fail(422, 'The given data was invalid.', errors)
  // Excluding the entry itself, so saving it unchanged is not a clash with itself.
  assertNoTimetableClash(school.id, Number(body.class_id), entry.academic_year_id, body, entry.id)
  Object.assign(entry, {
    class_id: Number(body.class_id),
    subject_id: Number(body.subject_id),
    day: Number(body.day),
    start: body.start,
    end: body.end,
  })
  return ok(config, { data: serializeTimetableEntry(entry) })
}

function adminDeleteTimetableEntry(config, match) {
  const { user, school } = requireActiveTenant(config)
  // Laravel mounts every /admin route inside the role:admin group; requireActiveTenant only
  // checks the tenant and the subscription.
  if (user.role !== 'admin') throw fail(403, 'Only administrators can do this.')
  const id = Number(match[1])
  db.timetableEntries = db.timetableEntries.filter((entry) => !(entry.id === id && entry.school_id === school.id))
  return ok(config, { message: 'Timetable entry removed.' })
}

function adminListRequests(config) {
  const { school } = requireAdmin(config)
  const status = config.params?.status
  // Laravel's $request->boolean() accepts 1, '1', true and 'true'; axios sends
  // a bare number, which a string-only list silently drops.
  const needsHuman = [1, '1', true, 'true'].includes(config.params?.needs_human)
  const rows = db.requests
    .filter((request) => request.school_id === school.id)
    .filter((request) => !status || request.status === status)
    .filter((request) => !needsHuman || !documentTriage(request).auto_generatable)
    .map(serializeAdminRequest)
    .sort((a, b) => b.id - a.id)
  return ok(config, paginate(config, rows, ['reference', 'type']))
}

function adminRequestTriage(config) {
  const { school } = requireAdmin(config)
  const rows = db.requests.filter((request) => request.school_id === school.id)
  const triaged = rows.map((request) => documentTriage(request))

  return ok(config, {
    data: {
      total: rows.length,
      auto_generatable: triaged.filter((row) => row.auto_generatable).length,
      needs_human: triaged.filter((row) => !row.auto_generatable).length,
      catalogue: DOCUMENT_TYPES.map((type) => ({
        label: type.label,
        slug: type.slug,
        auto_generatable: type.auto_generatable,
        note: type.auto_generatable
          ? null
          : 'Prepared by a member of staff, so allow extra time.',
      })),
    },
  })
}

function notifyStudentAboutRequest(schoolId, studentId, request, status) {
  const studentUser = db.users.find((user) => user.id === db.students.find((student) => student.id === Number(studentId))?.user_id)
  if (!studentUser) return
  const messages = { under_review: `Your request ${request.reference} is now under review.`, approved: `Your request ${request.reference} has been approved.`, ready: `Your request ${request.reference} is ready to download.`, rejected: `Your request ${request.reference} was declined.` }
  db.notifications.push({ id: nextMockId(), school_id: schoolId, user_id: studentUser.id, type: 'request_updated', title: 'Request update', message: messages[status] ?? `Your request ${request.reference} was updated.`, data: { request_id: request.id, status }, read_at: null })
}

function adminUpdateRequest(config, match) {
  const { school } = requireAdmin(config)
  const body = readBody(config)
  const request = db.requests.find((item) => item.id === Number(match[1]) && item.school_id === school.id)
  if (!request) throw fail(404, 'Not found.')
  request.status = body.status
  request.admin_note = body.admin_note ?? request.admin_note
  notifyStudentAboutRequest(school.id, request.student_id, request, body.status)
  return ok(config, { data: serializeAdminRequest(request) })
}

function adminGenerateDocument(config, match) {
  const { school } = requireAdmin(config)
  const request = db.requests.find((item) => item.id === Number(match[1]) && item.school_id === school.id)
  if (!request) throw fail(404, 'Not found.')

  /*
  | The bug this replaces: any type was turned into a document titled with the
  | request's own words, the request was marked ready, and the student was told
  | it was available to download. Refuse instead, and leave the request open.
  */
  const triage = documentTriage(request)
  if (!triage.auto_generatable) {
    throw fail(422, triage.reason ?? 'This document cannot be generated automatically.', {
      type: [triage.reason ?? 'This document cannot be generated automatically.'],
    })
  }

  if (!db.documents.find((document) => document.request_id === request.id)) {
    db.documents.push({ id: nextMockId(), school_id: school.id, request_id: request.id, student_id: request.student_id, title: request.type, file_name: `${request.type.toLowerCase().replace(/\s+/g, '-')}-${request.reference.toLowerCase()}.pdf`, mime_type: 'application/pdf', size: 1840, created_at: new Date().toISOString() })
  }
  request.status = 'ready'
  notifyStudentAboutRequest(school.id, request.student_id, request, 'ready')
  return ok(config, { data: serializeAdminRequest(request) })
}

/*
| Announcement drafting. Mirrors DeterministicAnnouncementDrafter clause for
| clause: same salutations, same openings, same logistics wording, same limits.
| There is no provider in the mock, so `source` is always `deterministic` and
| `ai_available` is always false. Drafting persists nothing, exactly as the
| Laravel endpoint does.
*/
/*
| Two different truncations, because they answer two different questions.
|
| `truncateTo` bounds the result at `max` inclusive — used for the body, which
| must fit `StoreAnnouncementRequest`'s max:5000 or the draft is unpublishable.
|
| `strLimit` mirrors Laravel's `Str::limit`, which truncates to the limit and
| *then* appends the end marker, so it can return limit + 1 characters. Used for
| `short_body`, whose whole purpose is to match what
| `AnnouncementPublishedNotification::body()` already produces with
| `Str::limit(..., 240)`.
*/
const truncateTo = (value, max, end = '…') => {
  const text = String(value ?? '')
  if (text.length <= max) return text
  return `${text.slice(0, Math.max(0, max - end.length)).trimEnd()}${end}`
}

const strLimit = (value, limit, end = '…') => {
  const text = String(value ?? '')
  return text.length <= limit ? text : `${text.slice(0, limit).trimEnd()}${end}`
}

const ucfirst = (value) => {
  const text = String(value ?? '').trim()
  return text.charAt(0).toUpperCase() + text.slice(1)
}

const lcfirst = (value) => {
  const text = String(value ?? '').trim()
  return text.charAt(0).toLowerCase() + text.slice(1)
}

const writeAnnouncementDraft = (input, userLocale) => {
  const fr = String(userLocale ?? input.locale ?? 'en').toLowerCase().startsWith('fr')
  const friendly = input.tone === 'friendly'
  const subject = String(input.subject ?? '').trim()
  const audience = ['all', 'students', 'teachers'].includes(input.audience) ? input.audience : 'all'
  const points = Array.isArray(input.key_points)
    ? input.key_points.map((point) => String(point ?? '').trim()).filter(Boolean)
    : []
  const dateText = input.date_text ? String(input.date_text).trim() : null
  const venue = input.venue ? String(input.venue).trim() : null
  const action = input.action_required ? String(input.action_required).trim() : null

  const salutation = fr
    ? { students: 'Chers élèves,', teachers: 'Chers collègues,', all: 'Chers élèves et membres du personnel,' }[audience]
    : { students: 'Dear students,', teachers: 'Dear colleagues,', all: 'Dear students and staff,' }[audience]

  const opening = fr
    ? (friendly ? `Petit mot au sujet de ${lcfirst(subject)}.` : `Nous vous informons que ${lcfirst(subject)}.`)
    : (friendly ? `A quick note about ${lcfirst(subject)}.` : `This is to inform you that ${lcfirst(subject)}.`)

  const parts = [salutation, opening]

  if (points.length > 0) {
    const heading = fr ? 'Points importants :' : 'Key details:'
    parts.push([heading, ...points.map((point) => `• ${ucfirst(point)}`)].join('\n'))
  }

  if (dateText || venue) {
    if (fr) {
      parts.push(dateText && venue
        ? `Cela aura lieu le ${dateText}, à ${venue}.`
        : dateText
          ? `Cela aura lieu le ${dateText}.`
          : `Cela aura lieu à ${venue}.`)
    } else {
      parts.push(dateText && venue
        ? `It takes place on ${dateText} at ${venue}.`
        : dateText
          ? `It takes place on ${dateText}.`
          : `It takes place at ${venue}.`)
    }
  }

  if (action) parts.push(fr ? `Action requise : ${action}` : `Action required: ${action}`)

  parts.push(fr
    ? (friendly ? 'Merci et à bientôt !' : 'Merci de votre attention.')
    : (friendly ? 'Thanks — see you there!' : 'Thank you for your attention.'))

  const body = truncateTo(parts.join('\n\n'), 5000)

  return {
    title: ucfirst(subject),
    body,
    short_body: strLimit(body.replace(/\s+/g, ' ').trim(), 240),
  }
}

function adminDraftAnnouncement(config) {
  const { user } = requireActiveTenant(config)
  if (user.role !== 'admin') throw fail(403, 'Only administrators can draft announcements.')

  const body = readBody(config)
  const errors = validate(body, ['subject'])
  if (Object.keys(errors).length) throw fail(422, 'The given data was invalid.', errors)
  if (!['all', 'students', 'teachers'].includes(body.audience ?? 'all')) {
    throw fail(422, 'The given data was invalid.', { audience: ['The selected audience is invalid.'] })
  }
  if (!['formal', 'friendly'].includes(body.tone ?? 'formal')) {
    throw fail(422, 'The given data was invalid.', { tone: ['The selected tone is invalid.'] })
  }
  if (body.locale != null && !['en', 'fr'].includes(body.locale)) {
    throw fail(422, 'The given data was invalid.', { locale: ['The selected locale is invalid.'] })
  }
  if (Array.isArray(body.key_points) && body.key_points.length > 10) {
    throw fail(422, 'The given data was invalid.', { key_points: ['At most 10 key points.'] })
  }

  const draft = writeAnnouncementDraft(body, user.locale)

  // No row is written and nobody is notified — the admin publishes separately.
  return ok(config, {
    data: {
      ...draft,
      source: 'deterministic',
      locale: String(user.locale ?? body.locale ?? 'en').toLowerCase().startsWith('fr') ? 'fr' : 'en',
      ai_available: false,
    },
  })
}

function adminCreateAnnouncement(config) {
  const { user, school } = requireActiveTenant(config)
  // Laravel mounts this under the role:admin group; requireActiveTenant only
  // checks the tenant and the subscription, so the role has to be checked here.
  if (user.role !== 'admin') throw fail(403, 'Only administrators can publish announcements.')
  const body = readBody(config)
  const errors = validate(body, ['title', 'body'])
  if (Object.keys(errors).length) throw fail(422, 'The given data was invalid.', errors)
  const announcement = { id: nextMockId(), school_id: school.id, title: body.title, body: body.body, audience: body.audience ?? 'all', published_at: new Date().toISOString(), author: { name: user.name } }
  db.announcements.push(announcement)
  return ok(config, { data: announcement }, 201)
}

// ---- super admin ----

function superAdminDashboard(config) {
  requireRole(config, 'super_admin')
  return ok(config, { stats: platformStats(), recent_activity: [] })
}

function superAdminListSchools(config) {
  requireRole(config, 'super_admin')
  return ok(config, { data: db.schools.map(serializeSchool).sort((a, b) => a.id - b.id) })
}

function superAdminCreateSchool(config) {
  requireRole(config, 'super_admin')
  const body = readBody(config)
  const errors = validate(body, ['name', 'slug'])
  if (Object.keys(errors).length) throw fail(422, 'The given data was invalid.', errors)
  const school = { id: nextMockId(), name: body.name, slug: body.slug, code: body.code ?? null, email: body.email ?? null, phone: body.phone ?? null, address: body.address ?? null, status: body.status ?? 'trial', timezone: 'Africa/Douala', primary_color: body.primary_color ?? null, subscription_plan_id: null, subscription_status: 'none', subscription_started_at: null, subscription_expires_at: null }
  db.schools.push(school)
  return ok(config, { data: serializeSchool(school) }, 201)
}

function superAdminGetSchool(config, match) {
  requireRole(config, 'super_admin')
  const school = byId(db.schools, match[1])
  if (!school) throw fail(404, 'Not found.')
  return ok(config, { data: serializeSchool(school), users_count: db.users.filter((user) => user.school_id === school.id).length })
}

function superAdminSchoolStatus(config, match) {
  requireRole(config, 'super_admin')
  const school = byId(db.schools, match[1])
  if (!school) throw fail(404, 'Not found.')
  const body = readBody(config)
  school.status = body.status
  const subscription = latestSubscription(school)
  if (subscription) subscription.status = body.status === 'suspended' ? 'suspended' : body.status === 'active' ? 'active' : body.status
  school.subscription_status = subscription?.status ?? school.subscription_status
  return ok(config, { data: serializeSchool(school) })
}

function superAdminSchoolUsers(config, match) {
  requireRole(config, 'super_admin')
  const school = byId(db.schools, match[1])
  if (!school) throw fail(404, 'Not found.')
  return ok(config, { data: db.users.filter((user) => user.school_id === school.id).map((user) => ({ id: user.id, name: user.name, email: user.email, role: user.role })) })
}

function superAdminListPlans(config) {
  requireRole(config, 'super_admin')
  return ok(config, { data: db.plans.sort((a, b) => a.price - b.price) })
}

function superAdminCreatePlan(config) {
  requireRole(config, 'super_admin')
  const body = readBody(config)
  const errors = validate(body, ['name', 'slug', 'price'])
  if (Object.keys(errors).length) throw fail(422, 'The given data was invalid.', errors)
  const plan = {
    id: nextMockId(),
    name: body.name,
    slug: body.slug,
    description: body.description ?? null,
    price: Number(body.price),
    billing_interval: body.billing_interval ?? 'monthly',
    currency: body.currency ?? 'XAF',
    max_students: body.max_students ?? null,
    max_teachers: body.max_teachers ?? null,
    max_classes: body.max_classes ?? null,
    features: body.features ?? [],
    status: 'active',
  }
  db.plans.push(plan)
  return ok(config, { data: plan }, 201)
}

function superAdminUpdatePlan(config, match) {
  requireRole(config, 'super_admin')
  const plan = byId(db.plans, match[1])
  if (!plan) throw fail(404, 'Not found.')
  const body = readBody(config)
  Object.assign(plan, {
    name: body.name ?? plan.name,
    slug: body.slug ?? plan.slug,
    description: body.description ?? plan.description,
    price: body.price !== undefined ? Number(body.price) : plan.price,
    billing_interval: body.billing_interval ?? plan.billing_interval,
    currency: body.currency ?? plan.currency,
    max_students: body.max_students !== undefined ? body.max_students : plan.max_students,
    max_teachers: body.max_teachers !== undefined ? body.max_teachers : plan.max_teachers,
    max_classes: body.max_classes !== undefined ? body.max_classes : plan.max_classes,
    features: body.features ?? plan.features,
  })
  return ok(config, { data: plan })
}

function superAdminListSubscriptions(config) {
  requireRole(config, 'super_admin')
  return ok(config, {
    data: db.subscriptions
      .map((subscription) => ({ ...subscription, school: byId(db.schools, subscription.school_id), plan: byId(db.plans, subscription.plan_id) }))
      .sort((a, b) => b.id - a.id),
  })
}

function superAdminListPayments(config) {
  requireRole(config, 'super_admin')
  return ok(config, {
    data: db.payments
      .map((payment) => ({ ...payment, school: byId(db.schools, payment.school_id), subscription: payment.subscription_id ? db.subscriptions.find((subscription) => subscription.id === payment.subscription_id) : null }))
      .sort((a, b) => b.id - a.id),
  })
}

// ---- semesters / grade components / exams / import (admin) ----

function adminListSemesters(config) {
  const { user, school } = requireActiveTenant(config)
  // Laravel mounts every /admin route inside the role:admin group; requireActiveTenant only
  // checks the tenant and the subscription.
  if (user.role !== 'admin') throw fail(403, 'Only administrators can do this.')
  const year = currentYearFor(school.id)
  return ok(config, { data: year ? semestersFor(school.id, year.id) : [], academic_year: year })
}

function adminCreateSemester(config) {
  const { user, school } = requireActiveTenant(config)
  // Laravel mounts every /admin route inside the role:admin group; requireActiveTenant only
  // checks the tenant and the subscription.
  if (user.role !== 'admin') throw fail(403, 'Only administrators can do this.')
  const body = readBody(config)
  const errors = validate(body, ['academic_year_id', 'name'])
  if (Object.keys(errors).length) throw fail(422, 'The given data was invalid.', errors)
  const semester = {
    id: nextMockId(),
    school_id: school.id,
    academic_year_id: Number(body.academic_year_id),
    name: body.name,
    sequence: Number(body.sequence ?? 1),
    start_date: body.start_date ?? null,
    end_date: body.end_date ?? null,
    is_current: false,
  }
  db.semesters.push(semester)
  if (body.is_current) {
    db.semesters.forEach((item) => {
      if (item.school_id === school.id && item.academic_year_id === semester.academic_year_id) item.is_current = item.id === semester.id
    })
  }
  return ok(config, { data: semester }, 201)
}

function adminActivateSemester(config, match) {
  const { user, school } = requireActiveTenant(config)
  // Laravel mounts every /admin route inside the role:admin group; requireActiveTenant only
  // checks the tenant and the subscription.
  if (user.role !== 'admin') throw fail(403, 'Only administrators can do this.')
  const id = Number(match[1])
  const semester = byId(db.semesters, id)
  if (!semester || semester.school_id !== school.id) throw fail(404, 'Not found.')
  db.semesters.forEach((item) => {
    if (item.school_id === school.id && item.academic_year_id === semester.academic_year_id) item.is_current = item.id === id
  })
  return ok(config, { data: byId(db.semesters, id) })
}

function adminDeleteSemester(config, match) {
  const { user, school } = requireActiveTenant(config)
  // Laravel mounts every /admin route inside the role:admin group; requireActiveTenant only
  // checks the tenant and the subscription.
  if (user.role !== 'admin') throw fail(403, 'Only administrators can do this.')
  const id = Number(match[1])
  db.semesters = db.semesters.filter((item) => !(item.id === id && item.school_id === school.id))
  return ok(config, { message: 'Semester removed.' })
}

function adminListGradeComponents(config) {
  const { user, school } = requireActiveTenant(config)
  // Laravel mounts every /admin route inside the role:admin group; requireActiveTenant only
  // checks the tenant and the subscription.
  if (user.role !== 'admin') throw fail(403, 'Only administrators can do this.')
  const defaults = db.gradeComponents.filter((component) => component.school_id === school.id && component.subject_id == null)
  const bySubject = {}
  for (const component of db.gradeComponents.filter((component) => component.school_id === school.id && component.subject_id != null)) {
    ;(bySubject[component.subject_id] ??= []).push(component)
  }
  return ok(config, { default: defaults, by_subject: bySubject })
}

function adminCreateGradeComponent(config) {
  const { user, school } = requireActiveTenant(config)
  // Laravel mounts every /admin route inside the role:admin group; requireActiveTenant only
  // checks the tenant and the subscription.
  if (user.role !== 'admin') throw fail(403, 'Only administrators can do this.')
  const body = readBody(config)
  const errors = validate(body, ['name', 'weight'])
  if (Object.keys(errors).length) throw fail(422, 'The given data was invalid.', errors)
  const component = {
    id: nextMockId(),
    school_id: school.id,
    subject_id: body.subject_id ?? null,
    name: body.name,
    weight: Number(body.weight),
    sequence: db.gradeComponents.filter((component) => component.school_id === school.id).length + 1,
  }
  db.gradeComponents.push(component)
  return ok(config, { data: component }, 201)
}

function adminUpdateGradeComponent(config, match) {
  const { user, school } = requireActiveTenant(config)
  // Laravel mounts every /admin route inside the role:admin group; requireActiveTenant only
  // checks the tenant and the subscription.
  if (user.role !== 'admin') throw fail(403, 'Only administrators can do this.')
  const component = db.gradeComponents.find((item) => item.id === Number(match[1]) && item.school_id === school.id)
  if (!component) throw fail(404, 'Not found.')
  const body = readBody(config)
  component.name = body.name ?? component.name
  component.weight = body.weight !== undefined ? Number(body.weight) : component.weight
  component.subject_id = body.subject_id !== undefined ? body.subject_id : component.subject_id
  return ok(config, { data: component })
}

function adminDeleteGradeComponent(config, match) {
  const { user, school } = requireActiveTenant(config)
  // Laravel mounts every /admin route inside the role:admin group; requireActiveTenant only
  // checks the tenant and the subscription.
  if (user.role !== 'admin') throw fail(403, 'Only administrators can do this.')
  const id = Number(match[1])
  db.gradeComponents = db.gradeComponents.filter((item) => !(item.id === id && item.school_id === school.id))
  return ok(config, { message: 'Component removed.' })
}

function adminListExams(config) {
  const { user, school } = requireActiveTenant(config)
  // Laravel mounts every /admin route inside the role:admin group; requireActiveTenant only
  // checks the tenant and the subscription.
  if (user.role !== 'admin') throw fail(403, 'Only administrators can do this.')
  const classId = config.params?.class_id
  const exams = db.exams
    .filter((exam) => exam.school_id === school.id && (!classId || exam.class_id === Number(classId)))
    .sort((a, b) => a.date.localeCompare(b.date) || a.start.localeCompare(b.start))
    .map(serializeExam)
  return ok(config, { data: exams })
}

function adminCreateExam(config) {
  const { user, school } = requireActiveTenant(config)
  // Laravel mounts every /admin route inside the role:admin group; requireActiveTenant only
  // checks the tenant and the subscription.
  if (user.role !== 'admin') throw fail(403, 'Only administrators can do this.')
  const body = readBody(config)
  const errors = validate(body, ['subject_id', 'class_id', 'date', 'start', 'end'])
  if (Object.keys(errors).length) throw fail(422, 'The given data was invalid.', errors)
  const exam = {
    id: nextMockId(),
    school_id: school.id,
    academic_year_id: currentYearFor(school.id)?.id,
    semester_id: body.semester_id ?? currentSemesterFor(school.id, currentYearFor(school.id)?.id)?.id ?? null,
    subject_id: Number(body.subject_id),
    class_id: Number(body.class_id),
    date: body.date,
    start: body.start,
    end: body.end,
    room: body.room ?? null,
  }
  db.exams.push(exam)
  return ok(config, { data: serializeExam(exam) }, 201)
}

function adminDeleteExam(config, match) {
  const { user, school } = requireActiveTenant(config)
  // Laravel mounts every /admin route inside the role:admin group; requireActiveTenant only
  // checks the tenant and the subscription.
  if (user.role !== 'admin') throw fail(403, 'Only administrators can do this.')
  const id = Number(match[1])
  db.exams = db.exams.filter((item) => !(item.id === id && item.school_id === school.id))
  return ok(config, { message: 'Exam session removed.' })
}

function adminExamRanking(config) {
  const { user, school } = requireActiveTenant(config)
  // Laravel mounts every /admin route inside the role:admin group; requireActiveTenant only
  // checks the tenant and the subscription.
  if (user.role !== 'admin') throw fail(403, 'Only administrators can do this.')
  const classId = Number(config.params?.class_id)
  const subjectId = Number(config.params?.subject_id)
  const semesterId = config.params?.semester_id
  const year = currentYearFor(school.id)
  const students = db.enrollments
    .filter((entry) => entry.class_id === classId && entry.academic_year_id === year?.id)
    .map((entry) => {
      const student = byId(db.students, entry.student_id)
      const grade = db.grades.find(
        (item) =>
          item.student_id === student.id &&
          item.subject_id === subjectId &&
          item.class_id === classId &&
          item.academic_year_id === year?.id &&
          (!semesterId || item.semester_id === Number(semesterId)),
      )
      return { student_id: student.id, name: userName(student.user_id), matricule: student.matricule, average: grade ? weightedAverageOf(grade) : null }
    })
    .filter((item) => item.average !== null)
    .sort((a, b) => b.average - a.average)
    .map((item, index) => ({ ...item, rank: index + 1 }))
  return ok(config, {
    subject: byId(db.subjects, subjectId),
    class: byId(db.classes, classId),
    academic_year: year,
    semester: semesterId ? byId(db.semesters, semesterId) : null,
    ranking: students,
  })
}

function teacherListExams(config) {
  const { user, school } = requireActiveTenant(config)
  const teacher = teacherForUser(user.id, school.id)
  if (!teacher) throw fail(403, 'No teacher profile is attached to this account.')
  const assignments = db.teachingAssignments.filter(
    (assignment) => assignment.teacher_id === teacher.id && assignment.academic_year_id === currentYearFor(school.id)?.id,
  )
  const exams = db.exams
    .filter((exam) =>
      exam.school_id === school.id &&
      assignments.some((assignment) => assignment.class_id === exam.class_id && assignment.subject_id === exam.subject_id),
    )
    .sort((a, b) => a.date.localeCompare(b.date) || a.start.localeCompare(b.start))
    .map(serializeExam)
  return ok(config, { data: exams })
}

/*
| CSV import mapping. Mirrors DeterministicHeaderMapper, ValueNormaliser and
| ClassResolver: same alias table, same scored fuzzy match with tie-break-to-none,
| same phone normalisation, same refusal to guess an ambiguous class. There is no
| provider in the mock, so `source` is always `deterministic`.
*/
const IMPORT_FIELDS = {
  students: ['name', 'email', 'matricule', 'class', 'academic_year', 'phone', 'password'],
  teachers: ['name', 'email', 'staff_no', 'phone', 'password'],
}

const HEADER_ALIASES = {
  name: ['name', 'full name', 'names', 'student name', 'pupil name', 'surname', 'name of student',
    'nom', 'noms', 'nom complet', 'nom et prenom', 'nom de l eleve', 'eleve', 'nom et prenoms'],
  email: ['email', 'e mail', 'mail', 'electronic mail', 'email address', 'courriel',
    'adresse courriel', 'adresse e mail', 'adresse electronique', 'mel'],
  matricule: ['matricule', 'matricule number', 'student number', 'student id', 'registration number',
    'reg no', 'numero d eleve', 'numero eleve', 'code eleve', 'matricule eleve'],
  staff_no: ['staff no', 'staff number', 'staff id', 'teacher number', 'teacher id',
    'numero du personnel', 'matricule enseignant', 'numero enseignant'],
  class: ['class', 'class name', 'classe', 'level', 'niveau', 'form', 'nom de la classe', 'classe eleve'],
  academic_year: ['academic year', 'year', 'session', 'annee', 'annee scolaire', 'exercice'],
  phone: ['phone', 'phone number', 'telephone', 'tel', 'mobile', 'cell', 'contact',
    'numero de telephone', 'telephone portable'],
  password: ['password', 'temporary password', 'mot de passe', 'mot de passe temporaire'],
}

/*
| Folding accents is for matching headers and class names only — never for
| values. Stripping the accents out of a pupil's name would corrupt the record
| being imported. `String.prototype.normalize` + a combining-mark strip matches
| Laravel's Str::ascii closely enough for Latin-1 school exports.
*/
const importMatchKey = (value) => String(value ?? '')
  .normalize('NFD')
  .replace(/[\u0300-\u036f]/g, '')
  .toLowerCase()
  .replace(/[^a-z0-9]+/g, ' ')
  .trim()

const importTokens = (value) => importMatchKey(value).split(' ').filter(Boolean)

const mapImportHeaders = (headers, fields) => {
  const mapping = {}
  const confidence = {}
  const conflicts = {}
  const claimedKeys = []

  const exactField = (header) => {
    const key = importMatchKey(header)
    for (const field of fields) {
      for (const alias of HEADER_ALIASES[field] ?? []) {
        if (importMatchKey(alias) === key) return field
      }
    }
    return null
  }

  for (const header of headers) {
    const field = exactField(header)
    if (field === null) continue
    if (mapping[field]) {
      ;(conflicts[field] ??= []).push(header)
      continue
    }
    mapping[field] = header
    confidence[field] = 'exact'
    claimedKeys.push(importMatchKey(header))
  }

  for (const header of headers) {
    if (claimedKeys.includes(importMatchKey(header))) continue

    const tokens = importTokens(header)
    if (tokens.length === 0) continue

    const scores = {}
    for (const field of fields) {
      if (mapping[field]) continue
      const vocabulary = new Set((HEADER_ALIASES[field] ?? []).flatMap(importTokens))
      const score = tokens.filter((token) => vocabulary.has(token)).length
      if (score > 0) scores[field] = score
    }

    const entries = Object.entries(scores)
    if (entries.length === 0) continue
    const best = Math.max(...entries.map(([, score]) => score))
    const winners = entries.filter(([, score]) => score === best).map(([field]) => field)

    // A tie is a guess waiting to happen, so it stays unmapped.
    if (winners.length !== 1) continue
    if (best < Math.ceil(tokens.length / 2)) continue

    mapping[winners[0]] = header
    confidence[winners[0]] = 'fuzzy'
    claimedKeys.push(importMatchKey(header))
  }

  return {
    mapping,
    confidence,
    conflicts,
    unmapped: headers.filter((header) => !Object.values(mapping).includes(header)),
  }
}

const normaliseImportPhone = (value) => {
  const trimmed = String(value ?? '').trim()
  if (trimmed === '') return null
  const digits = trimmed.replace(/\D+/g, '')
  if (digits === '') return trimmed
  let local = null
  if (digits.startsWith('00237')) local = digits.slice(5)
  else if (digits.startsWith('237') && digits.length === 12) local = digits.slice(3)
  else if (digits.length === 9) local = digits
  // Anything else is left exactly as written: a wrong guess here would mean
  // texting whoever happens to own that number.
  return local === null ? trimmed : `+237${local}`
}

const normaliseImportEmail = (value) => {
  const trimmed = String(value ?? '').trim()
  return trimmed === '' ? null : trimmed.toLowerCase()
}

const normaliseImportText = (value) => {
  const trimmed = String(value ?? '').trim()
  return trimmed === '' ? null : trimmed
}

const resolveImportClass = (schoolId, label) => {
  const key = importMatchKey(label)
  if (key === '') return { class_id: null, matched: null, ambiguous: false }

  const candidates = db.classes.filter((klass) => klass.school_id === schoolId)
  const exact = candidates.filter((klass) => importMatchKey(klass.name) === key)
  if (exact.length === 1) return { class_id: exact[0].id, matched: exact[0].name, ambiguous: false }
  if (exact.length > 1) return { class_id: null, matched: null, ambiguous: true }

  const contained = candidates.filter(
    (klass) => importMatchKey(klass.name) !== '' && importMatchKey(klass.name).includes(key),
  )
  if (contained.length === 1) return { class_id: contained[0].id, matched: contained[0].name, ambiguous: false }
  return contained.length > 1
    ? { class_id: null, matched: null, ambiguous: true }
    : { class_id: null, matched: null, ambiguous: false }
}

const applyImportMapping = (row, mapping, type) => {
  const pick = (field) => (mapping[field] ? row[mapping[field]] : undefined)
  const values = {
    name: normaliseImportText(pick('name')),
    email: normaliseImportEmail(pick('email')),
    phone: normaliseImportPhone(pick('phone')),
    password: normaliseImportText(pick('password')),
  }
  if (type === 'teachers') {
    values.staff_no = normaliseImportText(pick('staff_no'))
    return values
  }
  values.matricule = normaliseImportText(pick('matricule'))
  values.class = normaliseImportText(pick('class'))
  values.academic_year = normaliseImportText(pick('academic_year'))
  return values
}

const importRowWarnings = (values, resolved, type) => {
  const warnings = []
  if (!values.name) warnings.push('No name — no column mapped to "name", or the cell is empty.')
  if (!values.email) {
    warnings.push('No email address.')
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(values.email)) {
    warnings.push(`"${values.email}" is not a valid email address.`)
  }
  if (type === 'teachers') return warnings
  if (resolved.ambiguous) {
    warnings.push(`The class "${values.class}" matches more than one class in this school.`)
  } else if (resolved.class_id === null) {
    warnings.push(`No class in this school matches "${values.class}".`)
  }
  return warnings
}

function adminImportPreview(config) {
  const { user, school } = requireActiveTenant(config)
  if (user.role !== 'admin') throw fail(403, 'Only administrators can preview an import.')

  const body = readBody(config)
  const type = body.type === 'teachers' ? 'teachers' : 'students'
  const rows = Array.isArray(body.rows) ? body.rows : []

  if (rows.length === 0) throw fail(422, 'The given data was invalid.', { rows: ['Send at least one row.'] })
  if (rows.length > 500) {
    throw fail(422, 'The given data was invalid.', { rows: ['Preview at most 500 rows at a time.'] })
  }

  const headers = Object.keys(rows.find((row) => row && typeof row === 'object') ?? {})
  const { mapping, confidence, conflicts, unmapped } = mapImportHeaders(headers, IMPORT_FIELDS[type])

  const previewRows = rows.map((row, index) => {
    const values = applyImportMapping(row, mapping, type)
    const resolved = type === 'students'
      ? resolveImportClass(school.id, values.class ?? '')
      : { class_id: null, matched: null, ambiguous: false }
    const warnings = importRowWarnings(values, resolved, type)

    return {
      row: index + 1,
      values: {
        name: values.name ?? null,
        email: values.email ?? null,
        matricule: values.matricule ?? null,
        staff_no: values.staff_no ?? null,
        phone: values.phone ?? null,
        class_id: resolved.class_id,
      },
      class: { label: values.class ?? null, matched: resolved.matched, ambiguous: resolved.ambiguous },
      warnings,
    }
  })

  const importable = previewRows.filter((row) => row.warnings.length === 0).length

  // Nothing is written. The administrator confirms and POSTs to /admin/import.
  return ok(config, {
    data: {
      type,
      source: 'deterministic',
      fields: IMPORT_FIELDS[type],
      headers,
      mapping,
      confidence,
      unmapped,
      conflicts,
      available_classes: type === 'students'
        ? db.classes
          .filter((klass) => klass.school_id === school.id)
          .map((klass) => ({ id: klass.id, name: klass.name }))
          .sort((a, b) => a.name.localeCompare(b.name))
        : [],
      rows: previewRows,
      summary: { total: previewRows.length, importable, needs_attention: previewRows.length - importable },
    },
  })
}

function adminImport(config) {
  const { user, school } = requireActiveTenant(config)
  // Laravel mounts this under the role:admin group; requireActiveTenant only
  // checks the tenant and the subscription.
  if (user.role !== 'admin') throw fail(403, 'Only administrators can run an import.')

  const body = readBody(config)
  const type = body.type === 'teachers' ? 'teachers' : 'students'
  let rows = body.rows ?? []

  if (body.mapping && typeof body.mapping === 'object') {
    const fields = IMPORT_FIELDS[type]
    const headers = Object.keys(rows.find((row) => row && typeof row === 'object') ?? {})

    for (const [field, header] of Object.entries(body.mapping)) {
      if (!fields.includes(field)) {
        throw fail(422, 'The given data was invalid.', {
          [`mapping.${field}`]: [`"${field}" is not a field a ${type} import accepts.`],
        })
      }
      // Without this, a mapping pointing at a column the file does not have
      // would import every row as null without a single error.
      if (!headers.includes(header)) {
        throw fail(422, 'The given data was invalid.', {
          [`mapping.${field}`]: [`No column named "${header}" was sent in rows.`],
        })
      }
    }

    rows = rows.map((row) => {
      const values = applyImportMapping(row, body.mapping, type)
      const resolved = type === 'students' ? resolveImportClass(school.id, values.class ?? '') : null
      const clean = {}
      for (const [key, value] of Object.entries(values)) {
        if (value === null || key === 'class' || key === 'academic_year') continue
        clean[key] = value
      }
      if (resolved) clean.class_id = resolved.class_id
      return clean
    })
  }

  let created = 0
  const errors = []

  for (const [index, row] of rows.entries()) {
    try {
      if (type === 'teachers') {
        const userId = nextMockId()
        db.users.push({ id: userId, school_id: school.id, name: row.name, email: row.email, password: row.password ?? 'password123', role: 'teacher' })
        db.teachers.push({ id: nextMockId(), school_id: school.id, user_id: userId, staff_no: row.staff_no ?? null })
      } else {
        const classId = Number(row.class_id)
        const klass = db.classes.find((item) => item.id === classId && item.school_id === school.id)
        if (!klass) throw new Error('A class is required for each imported student.')
        const userId = nextMockId()
        db.users.push({ id: userId, school_id: school.id, name: row.name, email: row.email, password: row.password ?? 'password123', role: 'student' })
        const student = { id: nextMockId(), school_id: school.id, user_id: userId, matricule: row.matricule ?? `IMP-${nextMockId()}` }
        db.students.push(student)
        db.enrollments.push({ id: nextMockId(), school_id: school.id, student_id: student.id, class_id: classId, academic_year_id: currentYearFor(school.id)?.id })
      }
      created++
    } catch (err) {
      errors.push({ row: index + 1, name: row.name ?? null, message: err.message })
    }
  }

  return ok(config, { created, skipped: errors.length, errors })
}

// ---------------------------------------------------------------------------
// Route table
// ---------------------------------------------------------------------------

// ---------------------------------------------------------------------------
// Phase 5: password lifecycle, profile, PDFs, verification, audit trail
// ---------------------------------------------------------------------------

function forgotPassword(config) {
  const body = readBody(config)
  const user = db.users.find((candidate) => candidate.email === body.email)

  // Same answer either way — never leak whether an account exists.
  if (user) {
    const token = `mock-reset-${Math.random().toString(36).slice(2)}`
    resetTokens.set(token, user.id)
    // Surface the link in the console so it is usable in mock mode.
    console.info(`[SYNAPSE mock] reset link: /reset-password?token=${token}&email=${encodeURIComponent(user.email)}`)
  }

  return ok(config, { message: 'If that address belongs to an account, a reset link is on its way.' })
}

function resetPassword(config) {
  const body = readBody(config)
  const userId = resetTokens.get(body.token)
  const user = db.users.find((candidate) => candidate.id === userId && candidate.email === body.email)

  if (!user) {
    throw fail(422, 'The given data was invalid.', { email: ['This password reset link is invalid or has expired.'] })
  }

  if (!body.password || body.password.length < 8) {
    throw fail(422, 'The given data was invalid.', { password: ['The password must be at least 8 characters.'] })
  }

  user.password = body.password
  user.must_change_password = false
  resetTokens.delete(body.token)

  return ok(config, { message: 'Your password has been reset. You can now sign in.' })
}

function changePassword(config) {
  const user = authUser(config)
  const body = readBody(config)

  if (user.password !== body.current_password) {
    throw fail(422, 'The given data was invalid.', { current_password: ['Your current password is incorrect.'] })
  }

  if (!body.password || body.password.length < 8) {
    throw fail(422, 'The given data was invalid.', { password: ['The password must be at least 8 characters.'] })
  }

  user.password = body.password
  user.must_change_password = false

  return ok(config, { message: 'Your password has been updated.', user: publicUser(user) })
}

function showProfile(config) {
  const user = authUser(config)

  return ok(config, {
    data: publicUser(user),
    sessions: [{ id: 1, name: 'This device', last_used_at: new Date().toISOString(), created_at: user.last_login_at ?? null }],
  })
}

function updateProfile(config) {
  const user = authUser(config)
  const body = readBody(config)

  for (const field of ['name', 'email', 'phone', 'locale', 'notify_email', 'notify_sms']) {
    if (body[field] !== undefined) user[field] = body[field]
  }

  return ok(config, { message: 'Profile updated.', data: publicUser(user) })
}

function signOutOthers(config) {
  authUser(config)
  return ok(config, { message: 'All other sessions have been signed out.' })
}

function verifyDocumentCode(config, match) {
  const code = decodeURIComponent(match[1]).toUpperCase()
  const document = db.documents.find((item) => (item.verification_code ?? '').toUpperCase() === code)

  if (!document) {
    throw fail(404, 'No document matches this verification code.')
  }

  const student = db.students.find((item) => item.id === document.student_id)

  return ok(config, {
    valid: true,
    title: document.title,
    type: document.type ?? 'certificate',
    issued_on: (document.created_at ?? new Date().toISOString()).slice(0, 10),
    issued_to: userName(student?.user_id),
    matricule: student?.matricule ?? null,
    school: db.schools.find((item) => item.id === document.school_id)?.name ?? null,
    verification_code: document.verification_code,
  })
}

function reportCardLines(student, school) {
  const grades = db.grades.filter((grade) => grade.student_id === student?.id)
  const averages = grades.map((grade) => weightedAverageOf(grade)).filter((value) => value !== null)
  const average = averages.length ? averages.reduce((sum, value) => sum + value, 0) / averages.length : null

  return [
    `${school?.name ?? 'SYNAPSE'} — Report Card`,
    `Student: ${userName(student?.user_id)}`,
    `Matricule: ${student?.matricule ?? '—'}`,
    `Class: ${classOfStudent(student?.id, student?.school_id)?.name ?? '—'}`,
    `Subjects graded: ${grades.length}`,
    `Overall average: ${average === null ? '—' : average.toFixed(2)} / 20`,
    'Generated in SYNAPSE mock mode.',
  ]
}

function studentReportCardPdf(config) {
  const { user, school } = requireActiveTenant(config)
  const student = studentForUser(user.id, school.id)
  return pdfResponse(config, 'report-card.pdf', reportCardLines(student, school))
}

function studentTranscriptPdf(config) {
  const { user, school } = requireActiveTenant(config)
  const student = studentForUser(user.id, school.id)
  return pdfResponse(config, 'transcript.pdf', [
    `${school?.name ?? 'SYNAPSE'} — Academic Transcript`,
    `Student: ${userName(student?.user_id)}`,
    `Matricule: ${student?.matricule ?? '—'}`,
    'Generated in SYNAPSE mock mode.',
  ])
}

function adminStudentReportCardPdf(config, match) {
  const { user, school } = requireActiveTenant(config)
  // Laravel mounts every /admin route inside the role:admin group; requireActiveTenant only
  // checks the tenant and the subscription.
  if (user.role !== 'admin') throw fail(403, 'Only administrators can do this.')
  const student = db.students.find((item) => item.id === Number(match[1]) && item.school_id === school.id)
  if (!student) throw fail(404, 'Not found.')
  return pdfResponse(config, `report-card-${student.id}.pdf`, reportCardLines(student, school))
}

function adminStudentTranscriptPdf(config, match) {
  const { user, school } = requireActiveTenant(config)
  // Laravel mounts every /admin route inside the role:admin group; requireActiveTenant only
  // checks the tenant and the subscription.
  if (user.role !== 'admin') throw fail(403, 'Only administrators can do this.')
  const student = db.students.find((item) => item.id === Number(match[1]) && item.school_id === school.id)
  if (!student) throw fail(404, 'Not found.')
  return pdfResponse(config, `transcript-${student.id}.pdf`, [
    `${school?.name ?? 'SYNAPSE'} — Academic Transcript`,
    `Student: ${userName(student.user_id)}`,
    `Matricule: ${student.matricule}`,
    'Generated in SYNAPSE mock mode.',
  ])
}

function adminClassReportCards(config, match) {
  const { user, school } = requireActiveTenant(config)
  // Laravel mounts every /admin route inside the role:admin group; requireActiveTenant only
  // checks the tenant and the subscription.
  if (user.role !== 'admin') throw fail(403, 'Only administrators can do this.')
  const klass = db.classes.find((item) => item.id === Number(match[1]) && item.school_id === school.id)
  if (!klass) throw fail(404, 'Not found.')

  return ok(
    config,
    {
      message: 'Report cards are being generated. Students will be notified as each one is ready.',
      class: klass.name,
    },
    202,
  )
}

function adminPaymentReceiptPdf(config, match) {
  const { user, school } = requireActiveTenant(config)
  // Laravel mounts every /admin route inside the role:admin group; requireActiveTenant only
  // checks the tenant and the subscription.
  if (user.role !== 'admin') throw fail(403, 'Only administrators can do this.')
  const payment = db.payments.find((item) => item.id === Number(match[1]) && item.school_id === school.id)
  if (!payment) throw fail(404, 'Not found.')

  return pdfResponse(config, `receipt-${payment.reference}.pdf`, [
    `${school?.name ?? 'SYNAPSE'} — Payment Receipt`,
    `Reference: ${payment.reference}`,
    `Amount: ${payment.amount} ${payment.currency}`,
    `Status: ${String(payment.status).toUpperCase()}`,
    'Generated in SYNAPSE mock mode.',
  ])
}

function adminAuditLogs(config) {
  const { user, school } = requireActiveTenant(config)
  // Laravel mounts every /admin route inside the role:admin group; requireActiveTenant only
  // checks the tenant and the subscription.
  if (user.role !== 'admin') throw fail(403, 'Only administrators can do this.')
  const rows = (db.auditLogs ?? [])
    .filter((entry) => entry.school_id === school.id)
    .map((entry) => ({
      id: entry.id,
      action: entry.action,
      entity_type: String(entry.entity_type ?? '').split('\\').pop(),
      entity_id: entry.entity_id,
      metadata: entry.metadata ?? {},
      created_at: entry.created_at,
      user: entry.user_id
        ? {
            id: entry.user_id,
            name: userName(entry.user_id),
            role: db.users.find((user) => user.id === entry.user_id)?.role,
          }
        : null,
    }))
    .sort((a, b) => b.id - a.id)

  return ok(config, paginate(config, rows, ['action', 'entity_type']))
}

function superAdminAuditLogs(config) {
  requireRole(config, 'super_admin')
  const schoolId = Number(config.params?.school_id) || null
  const rows = (db.auditLogs ?? [])
    .filter((entry) => !schoolId || entry.school_id === schoolId)
    .map((entry) => ({
      id: entry.id,
      action: entry.action,
      entity_type: String(entry.entity_type ?? '').split('\\').pop(),
      entity_id: entry.entity_id,
      metadata: entry.metadata ?? {},
      created_at: entry.created_at,
      school: db.schools.find((school) => school.id === entry.school_id)?.name ?? null,
      user: entry.user_id ? { id: entry.user_id, name: userName(entry.user_id) } : null,
    }))
    .sort((a, b) => b.id - a.id)

  return ok(config, paginate(config, rows, ['action', 'entity_type', 'school']))
}

function superAdminPaymentReceiptPdf(config, match) {
  requireRole(config, 'super_admin')
  const payment = db.payments.find((item) => item.id === Number(match[1]))
  if (!payment) throw fail(404, 'Not found.')

  return pdfResponse(config, `receipt-${payment.reference}.pdf`, [
    'SYNAPSE — Payment Receipt',
    `Reference: ${payment.reference}`,
    `School: ${db.schools.find((school) => school.id === payment.school_id)?.name ?? '—'}`,
    `Amount: ${payment.amount} ${payment.currency}`,
    `Status: ${String(payment.status).toUpperCase()}`,
  ])
}

// ---------------------------------------------------------------------------
// Homework — mirrors App\Services\HomeworkService.
//
// Same two invariants as the backend: a teacher only touches homework inside a
// class/subject they hold a TeachingAssignment for, and a student only sees
// published homework for the class they are enrolled in.
// ---------------------------------------------------------------------------

const homeworkTeacher = (config) => {
  const { user, school } = requireActiveTenant(config)
  const teacher = teacherForUser(user.id, school.id)
  if (!teacher) throw fail(403, 'No teacher profile is attached to this account.')
  return { user, school, teacher }
}

const homeworkStudent = (config) => {
  const { user, school } = requireActiveTenant(config)
  const student = studentForUser(user.id, school.id)
  if (!student) throw fail(403, 'No student profile is attached to this account.')
  return { user, school, student }
}

/*
 * Scoped lookups. The backend gets row isolation for free from TenantScope — a
 * foreign school's id simply is not found, so it 404s rather than 403s. The
 * mock has to reproduce that explicitly, or a leaked id would expose another
 * school's record.
 */
const scopedFind = (list, id, schoolId) =>
  list.find((row) => Number(row.id) === Number(id) && row.school_id === schoolId)

const findHomework = (config, id) => {
  const { school } = requireTenant(config)
  const homework = scopedFind(db.homeworkAssignments, id, school.id)
  if (!homework) throw fail(404, 'No query results for model [HomeworkAssignment].')
  return homework
}

const findSubmission = (config, id) => {
  const { school } = requireTenant(config)
  const submission = scopedFind(db.homeworkSubmissions, id, school.id)
  if (!submission) throw fail(404, 'No query results for model [HomeworkSubmission].')
  return submission
}

/** Mirrors HomeworkService::assertAssigned(). */
const assertTeacherAssigned = (teacher, classId, subjectId, yearId) => {
  const assigned = db.teachingAssignments.some(
    (row) =>
      row.teacher_id === teacher.id &&
      row.class_id === Number(classId) &&
      row.subject_id === Number(subjectId) &&
      row.academic_year_id === Number(yearId),
  )
  if (!assigned) throw fail(403, 'You are not assigned to teach this subject in this class.')
}

const assertOwnsHomework = (teacher, homework) => {
  if (homework.teacher_id !== teacher.id) throw fail(403, 'This homework belongs to another teacher.')
}

const submissionFor = (homeworkId, studentId) =>
  db.homeworkSubmissions.find(
    (row) => row.homework_assignment_id === Number(homeworkId) && row.student_id === Number(studentId),
  )

const enrolledStudentIds = (classId, yearId) =>
  db.enrollments
    .filter((row) => row.class_id === Number(classId) && row.academic_year_id === Number(yearId))
    .map((row) => row.student_id)

const homeworkWithCounts = (homework) => {
  const submissions = db.homeworkSubmissions.filter(
    (row) => row.homework_assignment_id === homework.id,
  )
  return {
    ...serializeHomework(homework),
    submissions_count: submissions.length,
    graded_count: submissions.filter((row) => row.score !== null && row.score !== undefined).length,
  }
}

const studentHomeworkSummary = (student) => {
  let pending = 0
  let awaiting = 0
  let graded = 0
  for (const homework of studentHomeworkRows(student)) {
    const submission = submissionFor(homework.id, student.id)
    if (submission && submission.score !== null && submission.score !== undefined) graded += 1
    else if (submission) awaiting += 1
    else if (serializeHomework(homework).is_open) pending += 1
  }
  return { pending, awaiting_grade: awaiting, graded }
}

const studentHomeworkRows = (student) => {
  const year = currentYearFor(student.school_id)
  const enrollment = db.enrollments.find(
    (row) => row.student_id === student.id && row.academic_year_id === year?.id,
  )
  if (!enrollment) return []
  return db.homeworkAssignments.filter(
    (homework) =>
      homework.class_id === enrollment.class_id &&
      homework.academic_year_id === year.id &&
      homework.is_published,
  )
}

function teacherHomeworkIndex(config) {
  const { teacher } = homeworkTeacher(config)
  const rows = db.homeworkAssignments
    .filter((homework) => homework.teacher_id === teacher.id)
    .sort((a, b) => new Date(b.due_at) - new Date(a.due_at))
    .map(homeworkWithCounts)

  return ok(config, paginate(config, rows, ['title', 'instructions']))
}

async function teacherHomeworkStore(config) {
  const { school, teacher, user } = homeworkTeacher(config)
  const { fields: body, files } = readMultipart(config)

  validate(body, ['class_id', 'subject_id', 'title', 'max_score', 'due_at'])

  const klass = byId(db.classes, body.class_id)
  const subject = byId(db.subjects, body.subject_id)
  if (!klass || klass.school_id !== school.id) throw fail(422, 'The selected class belongs to another school.')
  if (!subject || subject.school_id !== school.id) throw fail(422, 'The selected subject belongs to another school.')

  const year = byId(db.academicYears, body.academic_year_id) ?? currentYearFor(school.id)
  if (!year) throw fail(409, 'No active academic year is configured.')

  assertTeacherAssigned(teacher, klass.id, subject.id, year.id)

  if (new Date(body.due_at).getTime() <= Date.now()) {
    throw fail(422, 'The deadline must be in the future.', { due_at: ['The deadline must be in the future.'] })
  }

  const duplicate = db.homeworkAssignments.some(
    (row) =>
      row.class_id === klass.id &&
      row.subject_id === subject.id &&
      row.academic_year_id === year.id &&
      row.title.toLowerCase() === String(body.title).toLowerCase(),
  )
  if (duplicate) {
    throw fail(422, 'Homework with this title already exists for that class and subject.', {
      title: ['Homework with this title already exists for that class and subject.'],
    })
  }

  const homework = {
    id: nextMockId(),
    school_id: school.id,
    teacher_id: teacher.id,
    subject_id: subject.id,
    class_id: klass.id,
    academic_year_id: year.id,
    semester_id: body.semester_id ? Number(body.semester_id) : (currentSemesterFor(school.id, year.id)?.id ?? null),
    title: body.title,
    instructions: body.instructions ?? null,
    max_score: Number(body.max_score),
    due_at: body.due_at,
    is_published: false,
    published_at: null,
    created_at: new Date().toISOString(),
  }
  db.homeworkAssignments.push(homework)

  await attachFiles(HOMEWORK_TYPE, homework.id, school.id, files, 'teacher', 'class', user.id)

  return ok(config, { data: serializeHomework(homework) }, 201)
}

function teacherHomeworkShow(config, match) {
  const { teacher } = homeworkTeacher(config)
  const homework = findHomework(config, match[1])
  assertOwnsHomework(teacher, homework)
  return ok(config, { data: homeworkWithCounts(homework) })
}

async function teacherHomeworkUpdate(config, match) {
  const { school, teacher, user } = homeworkTeacher(config)
  const homework = findHomework(config, match[1])
  assertOwnsHomework(teacher, homework)

  // The SPA posts multipart with `_method=PUT` when files are attached.
  const { fields: body, files } = readMultipart(config)
  if (body.title !== undefined) homework.title = body.title
  if (body.instructions !== undefined) homework.instructions = body.instructions
  if (body.max_score !== undefined) homework.max_score = Number(body.max_score)
  if (body.due_at !== undefined) homework.due_at = body.due_at

  await attachFiles(HOMEWORK_TYPE, homework.id, school.id, files, 'teacher', 'class', user.id)

  return ok(config, { data: homeworkWithCounts(homework) })
}

function teacherHomeworkDestroy(config, match) {
  const { teacher } = homeworkTeacher(config)
  const homework = findHomework(config, match[1])
  assertOwnsHomework(teacher, homework)

  db.homeworkAssignments = db.homeworkAssignments.filter((row) => row.id !== homework.id)
  db.homeworkSubmissions = db.homeworkSubmissions.filter(
    (row) => row.homework_assignment_id !== homework.id,
  )

  return ok(config, { message: 'Homework deleted.' })
}

function teacherHomeworkPublish(config, match) {
  const { teacher } = homeworkTeacher(config)
  const homework = findHomework(config, match[1])
  assertOwnsHomework(teacher, homework)

  if (!homework.is_published) {
    homework.is_published = true
    homework.published_at = new Date().toISOString()

    // Notify every enrolled student, as HomeworkService::publish() does.
    for (const studentId of enrolledStudentIds(homework.class_id, homework.academic_year_id)) {
      const student = byId(db.students, studentId)
      const userId = student?.user_id
      if (!userId) continue
      db.notifications.unshift({
        id: nextMockId(),
        school_id: homework.school_id,
        user_id: userId,
        type: 'homework_published',
        title: `New homework: ${homework.title}`,
        message: `New ${byId(db.subjects, homework.subject_id)?.name} homework for ${byId(db.classes, homework.class_id)?.name}, due ${homework.due_at.slice(0, 10)}.`,
        data: { homework_id: homework.id },
        read_at: null,
        created_at: new Date().toISOString(),
      })
    }
  }

  return ok(config, { data: homeworkWithCounts(homework) })
}

function teacherHomeworkUnpublish(config, match) {
  const { teacher } = homeworkTeacher(config)
  const homework = findHomework(config, match[1])
  assertOwnsHomework(teacher, homework)
  homework.is_published = false
  return ok(config, { data: homeworkWithCounts(homework) })
}

function teacherHomeworkSubmissions(config, match) {
  const { teacher } = homeworkTeacher(config)
  const homework = findHomework(config, match[1])
  assertOwnsHomework(teacher, homework)

  const ids = enrolledStudentIds(homework.class_id, homework.academic_year_id)

  const students = ids
    .map((studentId) => {
      const student = byId(db.students, studentId)
      const submission = submissionFor(homework.id, studentId)
      return {
        student_id: studentId,
        name: userName(student?.user_id),
        matricule: student?.matricule ?? null,
        submission: submission ? serializeSubmission(submission) : null,
        status: submission
          ? submission.score !== null && submission.score !== undefined
            ? 'graded'
            : submission.is_late
              ? 'late'
              : 'submitted'
          : 'not_submitted',
        score: submission?.score ?? null,
        max_score: homework.max_score,
      }
    })
    .sort((a, b) => String(a.name).localeCompare(String(b.name)))

  return ok(config, {
    assignment: homeworkWithCounts(homework),
    students,
    stats: {
      total: students.length,
      submitted: students.filter((row) => row.submission).length,
      graded: students.filter((row) => row.submission && row.submission.score !== null).length,
      late: students.filter((row) => row.submission?.is_late).length,
    },
  })
}

function teacherSubmissionShow(config, match) {
  const { teacher } = homeworkTeacher(config)
  const submission = findSubmission(config, match[1])
  const homework = findHomework(config, submission.homework_assignment_id)
  assertOwnsHomework(teacher, homework)

  return ok(config, { data: serializeSubmission(submission) })
}

function teacherSubmissionGrade(config, match) {
  const { teacher } = homeworkTeacher(config)
  const submission = findSubmission(config, match[1])
  const homework = findHomework(config, submission.homework_assignment_id)
  assertOwnsHomework(teacher, homework)

  const body = readBody(config)
  validate(body, ['score'])

  const score = Number(body.score)
  if (Number.isNaN(score) || score < 0) throw fail(422, 'The score cannot be negative.', { score: ['The score cannot be negative.'] })
  if (score > homework.max_score) {
    throw fail(422, `The score cannot exceed the maximum of ${homework.max_score}.`, {
      score: [`The score cannot exceed the maximum of ${homework.max_score}.`],
    })
  }

  submission.score = score
  submission.feedback = body.feedback ?? null
  submission.graded_by = teacher.id
  submission.graded_at = new Date().toISOString()

  const student = byId(db.students, submission.student_id)
  if (student?.user_id) {
    db.notifications.unshift({
      id: nextMockId(),
      school_id: submission.school_id,
      user_id: student.user_id,
      type: 'homework_graded',
      title: 'Your homework has been graded',
      message: `"${homework.title}" was marked ${score}/${homework.max_score}.`,
      data: { homework_id: homework.id, score },
      read_at: null,
      created_at: new Date().toISOString(),
    })
  }

  return ok(config, { data: serializeSubmission(submission) })
}

// ---------------------------------------------------------------------------
// Attachments — mirrors App\Services\AttachmentService.
//
// The mock has no disk, so uploads are read into `fileStore` and handed back as
// real Blobs. Same authorization rules as the backend: a class brief is open to
// every enrolled student, a submission is private to its author and the teacher
// who set the work.
// ---------------------------------------------------------------------------

const ATTACHMENT_MIMES = ['pdf', 'doc', 'docx', 'odt', 'rtf', 'txt', 'png', 'jpg', 'jpeg']
const ATTACHMENT_MAX_BYTES = 10 * 1024 * 1024
const ATTACHMENT_MAX_FILES = 5

/**
 * Accepts FormData or a plain object and returns `{ fields, files }`.
 * The real adapter serialises FormData itself; here we read it directly.
 */
/**
 * Rebuilds `a[0][b]` keys into a nested object, the way PHP parses them.
 * Only used by the mock: a real Laravel request gets this for free.
 */
const unflatten = (fields) => {
  const root = {}

  for (const [rawKey, value] of Object.entries(fields)) {
    const path = [...rawKey.matchAll(/([^[\]]+)/g)].map((match) => match[1])

    // No brackets — nothing to rebuild.
    if (path.length === 1) {
      root[rawKey] = value
      continue
    }

    let node = root
    for (let index = 0; index < path.length - 1; index += 1) {
      const key = path[index]
      if (node[key] === undefined) node[key] = /^\d+$/.test(path[index + 1]) ? [] : {}
      node = node[key]
    }

    node[path[path.length - 1]] = value
  }

  return root
}

const readMultipart = (config) => {
  const raw = config.data

  if (typeof FormData !== 'undefined' && raw instanceof FormData) {
    const fields = {}
    const files = []

    for (const [key, value] of raw.entries()) {
      if (value && typeof value === 'object' && 'arrayBuffer' in value) {
        files.push(value)
      } else {
        fields[key] = value
      }
    }

    // `questions[0][prompt]` style keys arrive flat; rebuild the nesting PHP
    // would give a real Laravel request.
    return { fields: unflatten(fields), files }
  }

  return { fields: readBody(config), files: [] }
}

const attachFiles = async (attachableType, attachableId, schoolId, files, role, visibility, userId) => {
  const existing = db.attachments.filter(
    (row) => row.attachable_type === attachableType && row.attachable_id === Number(attachableId),
  ).length

  const accepted = []

  for (const file of files.slice(0, Math.max(ATTACHMENT_MAX_FILES - existing, 0))) {
    const name = file.name ?? 'file'
    const extension = String(name.split('.').pop() ?? '').toLowerCase()

    if (!ATTACHMENT_MIMES.includes(extension)) {
      throw fail(422, `That file type is not allowed. Accepted: ${ATTACHMENT_MIMES.join(', ')}.`, {
        attachments: [`The ${name} file type is not allowed.`],
      })
    }
    if (file.size > ATTACHMENT_MAX_BYTES) {
      throw fail(422, 'Each file must be 10 MB or smaller.', { attachments: ['Each file must be 10 MB or smaller.'] })
    }

    const attachment = {
      id: nextMockId(),
      school_id: schoolId,
      attachable_type: attachableType,
      attachable_id: Number(attachableId),
      uploaded_by_role: role,
      uploaded_by: userId,
      file_name: name,
      mime_type: file.type || 'application/octet-stream',
      size: file.size,
      disk: 'local',
      path: `mock/${attachableType.split('\\').pop().toLowerCase()}-${nextMockId()}.${extension}`,
      visibility,
      created_at: new Date().toISOString(),
    }

    storeFile(attachment.id, new Blob([await file.arrayBuffer()], { type: attachment.mime_type }))
    db.attachments.push(attachment)
    accepted.push(attachment)
  }

  return accepted.map(serializeAttachment)
}

/** Mirrors AttachmentService::authorize(). */
const authorizeAttachment = (attachment, user, schoolId) => {
  // Resolve the owning record the same way HasAttachments does server-side:
  // a lesson owns itself, a submission defers to the homework it belongs to.
  let owner = null
  let ownerClassId = null
  let ownerYearId = null

  // Every lookup is school-scoped: an attachment id from another tenant must
  // not resolve here any more than it would through the model's TenantScope.
  if (attachment.attachable_type === HOMEWORK_TYPE) {
    owner = scopedFind(db.homeworkAssignments, attachment.attachable_id, schoolId)
    ownerClassId = owner?.class_id
    ownerYearId = owner?.academic_year_id
  } else if (attachment.attachable_type === SUBMISSION_TYPE) {
    const submission = scopedFind(db.homeworkSubmissions, attachment.attachable_id, schoolId)
    owner = submission ? scopedFind(db.homeworkAssignments, submission.homework_assignment_id, schoolId) : null
    ownerClassId = owner?.class_id
    ownerYearId = owner?.academic_year_id
  } else if (attachment.attachable_type === LESSON_TYPE) {
    owner = scopedFind(db.lessons, attachment.attachable_id, schoolId)
    ownerClassId = owner?.class_id
    ownerYearId = owner?.academic_year_id
  } else if (attachment.attachable_type === QUIZ_TYPE) {
    owner = scopedFind(db.quizzes, attachment.attachable_id, schoolId)
    ownerClassId = owner?.class_id
    ownerYearId = owner?.academic_year_id
  }

  if (!owner) throw fail(404, 'The record this file belonged to no longer exists.')

  if (user.role === 'admin') return

  if (user.role === 'teacher') {
    const teacher = teacherForUser(user.id, schoolId)
    if (teacher?.id !== owner.teacher_id) throw fail(403, "This file belongs to another teacher's work.")
    return
  }

  if (user.role === 'student') {
    const student = studentForUser(user.id, schoolId)
    if (!student) throw fail(403, 'No student profile is attached to this account.')

    if (attachment.uploaded_by === user.id) return

    if (attachment.visibility === 'private') throw fail(403, 'This file is not available to you.')

    const enrolled = db.enrollments.some(
      (row) =>
        row.student_id === student.id &&
        row.class_id === ownerClassId &&
        row.academic_year_id === ownerYearId,
    )
    if (!enrolled) throw fail(403, 'You are not enrolled in the class this file belongs to.')
    return
  }

  throw fail(403, 'You do not have permission to download this file.')
}

async function attachmentDownload(config, match) {
  const { user, school } = requireTenant(config)
  const attachment = byId(db.attachments, match[1])
  if (!attachment || attachment.school_id !== school.id) throw fail(404, 'Not found.')

  authorizeAttachment(attachment, user, school.id)

  const blob = readFile(attachment.id)
  if (!blob) throw fail(410, 'The stored file is no longer available.')

  return {
    data: blob,
    status: 200,
    statusText: 'OK',
    headers: {
      'content-type': attachment.mime_type,
      'content-disposition': `attachment; filename="${attachment.file_name}"`,
    },
    config,
  }
}

function studentHomeworkIndex(config) {
  const { student } = homeworkStudent(config)

  const rows = studentHomeworkRows(student)
    .sort((a, b) => new Date(b.due_at) - new Date(a.due_at))
    .map((homework) => {
      const submission = submissionFor(homework.id, student.id)
      return {
        assignment: serializeHomework(homework),
        submission: submission ? serializeSubmission(submission) : null,
      }
    })

  return ok(config, { data: rows, summary: studentHomeworkSummary(student) })
}

async function studentHomeworkSubmit(config, match) {
  const { school, student, user } = homeworkStudent(config)
  const homework = findHomework(config, match[1])

  if (!homework.is_published) throw fail(403, 'This homework is not available yet.')
  if (new Date(homework.due_at).getTime() < Date.now()) {
    throw fail(422, 'The deadline for this homework has passed.')
  }

  // Mirrors HomeworkService::assertEnrolled().
  const year = currentYearFor(student.school_id)
  const enrolled = db.enrollments.some(
    (row) =>
      row.student_id === student.id &&
      row.class_id === homework.class_id &&
      row.academic_year_id === homework.academic_year_id,
  )
  if (!enrolled) throw fail(403, 'You are not enrolled in the class this homework was set for.')
  if (!year) throw fail(409, 'No active academic year is configured.')

  const { fields: body, files } = readMultipart(config)

  // A blank submission is not a submission: require text or a file.
  if (!String(body.content ?? '').trim() && files.length === 0) {
    throw fail(422, 'Write your answer or attach a file before submitting.', {
      content: ['Write your answer or attach a file before submitting.'],
    })
  }

  const existing = submissionFor(homework.id, student.id)
  if (existing && existing.score !== null && existing.score !== undefined) {
    throw fail(422, 'This submission has already been graded and can no longer be changed.')
  }

  if (existing) {
    existing.content = body.content ?? null
    existing.attempts += 1
    existing.submitted_at = new Date().toISOString()
    existing.is_late = false
    await attachFiles(SUBMISSION_TYPE, existing.id, school.id, files, 'student', 'private', user.id)
    return ok(config, { data: serializeSubmission(existing) }, 201)
  }

  const submission = {
    id: nextMockId(),
    school_id: homework.school_id,
    homework_assignment_id: homework.id,
    student_id: student.id,
    content: body.content ?? null,
    attempts: 1,
    submitted_at: new Date().toISOString(),
    is_late: false,
    score: null,
    feedback: null,
    graded_by: null,
    graded_at: null,
    created_at: new Date().toISOString(),
  }
  db.homeworkSubmissions.push(submission)

  await attachFiles(SUBMISSION_TYPE, submission.id, school.id, files, 'student', 'private', user.id)

  return ok(config, { data: serializeSubmission(submission) }, 201)
}

// ---------------------------------------------------------------------------
// Course materials — mirrors App\Services\LessonService.
// ---------------------------------------------------------------------------

const lessonTeacher = (config) => homeworkTeacher(config)

const findLesson = (config, id) => {
  const { school } = requireTenant(config)
  const lesson = scopedFind(db.lessons, id, school.id)
  if (!lesson) throw fail(404, 'No query results for model [Lesson].')
  return lesson
}

const assertOwnsLesson = (teacher, lesson) => {
  if (lesson.teacher_id !== teacher.id) throw fail(403, 'This lesson belongs to another teacher.')
}

const studentLessonRows = (student) => {
  const year = currentYearFor(student.school_id)
  const enrollment = db.enrollments.find(
    (row) => row.student_id === student.id && row.academic_year_id === year?.id,
  )
  if (!enrollment) return []
  return db.lessons.filter(
    (lesson) =>
      lesson.class_id === enrollment.class_id &&
      lesson.academic_year_id === year.id &&
      lesson.is_published,
  )
}

function teacherLessonIndex(config) {
  const { teacher } = lessonTeacher(config)

  const rows = db.lessons
    .filter((lesson) => lesson.teacher_id === teacher.id)
    .sort((a, b) => b.is_published - a.is_published || b.id - a.id)
    .map((lesson) => serializeLesson(lesson))

  return ok(config, paginate(config, rows, ['title', 'topic', 'summary']))
}

async function teacherLessonStore(config) {
  const { school, teacher, user } = lessonTeacher(config)
  const { fields: body, files } = readMultipart(config)

  validate(body, ['class_id', 'subject_id', 'title'])

  const klass = byId(db.classes, body.class_id)
  const subject = byId(db.subjects, body.subject_id)
  if (!klass || klass.school_id !== school.id) throw fail(422, 'The selected class belongs to another school.')
  if (!subject || subject.school_id !== school.id) throw fail(422, 'The selected subject belongs to another school.')

  const year = byId(db.academicYears, body.academic_year_id) ?? currentYearFor(school.id)
  if (!year) throw fail(409, 'No active academic year is configured.')

  assertTeacherAssigned(teacher, klass.id, subject.id, year.id)

  const duplicate = db.lessons.some(
    (row) =>
      row.class_id === klass.id &&
      row.subject_id === subject.id &&
      row.academic_year_id === year.id &&
      row.title.toLowerCase() === String(body.title).toLowerCase(),
  )
  if (duplicate) {
    throw fail(422, 'A lesson with this title already exists for that class and subject.', {
      title: ['A lesson with this title already exists for that class and subject.'],
    })
  }

  const lesson = {
    id: nextMockId(),
    school_id: school.id,
    teacher_id: teacher.id,
    subject_id: subject.id,
    class_id: klass.id,
    academic_year_id: year.id,
    semester_id: body.semester_id
      ? Number(body.semester_id)
      : (currentSemesterFor(school.id, year.id)?.id ?? null),
    title: body.title,
    topic: body.topic || null,
    summary: body.summary || null,
    body: body.body || null,
    minutes: body.minutes ? Number(body.minutes) : null,
    sequence: body.sequence ? Number(body.sequence) : 0,
    is_published: false,
    published_at: null,
    created_at: new Date().toISOString(),
  }
  db.lessons.push(lesson)

  await attachFiles(LESSON_TYPE, lesson.id, school.id, files, 'teacher', 'class', user.id)

  return ok(config, { data: serializeLesson(lesson) }, 201)
}

function teacherLessonShow(config, match) {
  const { teacher } = lessonTeacher(config)
  const lesson = findLesson(config, match[1])
  assertOwnsLesson(teacher, lesson)
  return ok(config, { data: serializeLesson(lesson, { includeBody: true }) })
}

async function teacherLessonUpdate(config, match) {
  const { school, teacher, user } = lessonTeacher(config)
  const lesson = findLesson(config, match[1])
  assertOwnsLesson(teacher, lesson)

  const { fields: body, files } = readMultipart(config)
  if (body.title !== undefined) lesson.title = body.title
  if (body.topic !== undefined) lesson.topic = body.topic || null
  if (body.summary !== undefined) lesson.summary = body.summary || null
  if (body.body !== undefined) lesson.body = body.body || null
  if (body.minutes !== undefined) lesson.minutes = body.minutes ? Number(body.minutes) : null
  if (body.sequence !== undefined) lesson.sequence = Number(body.sequence) || 0

  await attachFiles(LESSON_TYPE, lesson.id, school.id, files, 'teacher', 'class', user.id)

  return ok(config, { data: serializeLesson(lesson, { includeBody: true }) })
}

function teacherLessonDestroy(config, match) {
  const { teacher } = lessonTeacher(config)
  const lesson = findLesson(config, match[1])
  assertOwnsLesson(teacher, lesson)

  db.lessons = db.lessons.filter((row) => row.id !== lesson.id)
  db.attachments = db.attachments.filter(
    (row) => !(row.attachable_type === LESSON_TYPE && row.attachable_id === lesson.id),
  )

  return ok(config, { message: 'Lesson deleted.' })
}

function teacherLessonPublish(config, match) {
  const { teacher } = lessonTeacher(config)
  const lesson = findLesson(config, match[1])
  assertOwnsLesson(teacher, lesson)

  if (!lesson.is_published) {
    lesson.is_published = true
    lesson.published_at = new Date().toISOString()

    for (const studentId of enrolledStudentIds(lesson.class_id, lesson.academic_year_id)) {
      const student = byId(db.students, studentId)
      if (!student?.user_id) continue
      const files = db.attachments.filter(
        (row) => row.attachable_type === LESSON_TYPE && row.attachable_id === lesson.id,
      ).length
      db.notifications.unshift({
        id: nextMockId(),
        school_id: lesson.school_id,
        user_id: student.user_id,
        type: 'lesson_published',
        title: `New lesson: ${lesson.title}`,
        message: `New ${byId(db.subjects, lesson.subject_id)?.name} material for ${byId(db.classes, lesson.class_id)?.name}${files ? `, with ${files} file${files === 1 ? '' : 's'} to download.` : '.'}`,
        data: { lesson_id: lesson.id },
        read_at: null,
        created_at: new Date().toISOString(),
      })
    }
  }

  return ok(config, { data: serializeLesson(lesson) })
}

function teacherLessonUnpublish(config, match) {
  const { teacher } = lessonTeacher(config)
  const lesson = findLesson(config, match[1])
  assertOwnsLesson(teacher, lesson)
  lesson.is_published = false
  return ok(config, { data: serializeLesson(lesson) })
}

function studentLessonIndex(config) {
  const { student } = homeworkStudent(config)

  // Grouped subject → topic, mirroring LessonService::forStudent().
  const grouped = {}
  for (const lesson of studentLessonRows(student).sort((a, b) => (a.sequence ?? 0) - (b.sequence ?? 0))) {
    const subjectName = byId(db.subjects, lesson.subject_id)?.name ?? 'Other'
    const topic = lesson.topic || 'General'
    grouped[subjectName] ??= {}
    grouped[subjectName][topic] ??= []
    grouped[subjectName][topic].push(serializeLesson(lesson))
  }

  const all = studentLessonRows(student)

  return ok(config, {
    data: grouped,
    summary: {
      lessons: all.length,
      subjects: new Set(all.map((lesson) => lesson.subject_id)).size,
      files: all.reduce(
        (total, lesson) =>
          total +
          db.attachments.filter(
            (row) => row.attachable_type === LESSON_TYPE && row.attachable_id === lesson.id,
          ).length,
        0,
      ),
    },
  })
}

function studentLessonShow(config, match) {
  const { student } = homeworkStudent(config)
  const lesson = findLesson(config, match[1])

  if (!lesson.is_published) throw fail(403, 'This lesson is not available yet.')

  const enrolled = db.enrollments.some(
    (row) =>
      row.student_id === student.id &&
      row.class_id === lesson.class_id &&
      row.academic_year_id === lesson.academic_year_id,
  )
  if (!enrolled) throw fail(403, 'You are not enrolled in the class this lesson was written for.')

  return ok(config, { data: serializeLesson(lesson, { includeBody: true }) })
}

// ---------------------------------------------------------------------------
// Quizzes — mirrors App\Services\QuizService.
//
// The answer key (`correct_option`) is only ever placed in a payload a teacher
// owns or a student has already submitted, exactly as the backend restricts it.
// ---------------------------------------------------------------------------

const quizTeacher = (config) => homeworkTeacher(config)

const findQuiz = (config, id) => {
  const { school } = requireTenant(config)
  const quiz = scopedFind(db.quizzes, id, school.id)
  if (!quiz) throw fail(404, 'No query results for model [Quiz].')
  return quiz
}

const findQuizAttempt = (config, id) => {
  const { school } = requireTenant(config)
  const attempt = scopedFind(db.quizAttempts, id, school.id)
  if (!attempt) throw fail(404, 'No query results for model [QuizAttempt].')
  return attempt
}

const assertOwnsQuiz = (teacher, quiz) => {
  if (quiz.teacher_id !== teacher.id) throw fail(403, 'This quiz belongs to another teacher.')
}

const questionsFor = (quizId) =>
  db.quizQuestions
    .filter((question) => question.quiz_id === Number(quizId))
    .sort((a, b) => (a.sequence ?? 0) - (b.sequence ?? 0) || a.id - b.id)

const attemptsUsedBy = (studentId, quizId) =>
  db.quizAttempts.filter(
    (attempt) =>
      attempt.quiz_id === Number(quizId) &&
      attempt.student_id === Number(studentId) &&
      attempt.submitted_at,
  )

const bestAttemptFor = (studentId, quizId) => {
  const rows = attemptsUsedBy(studentId, quizId)
  return rows.length ? [...rows].sort((a, b) => (b.score ?? 0) - (a.score ?? 0))[0] : null
}

const studentQuizRows = (student) => {
  const year = currentYearFor(student.school_id)
  const enrollment = db.enrollments.find(
    (row) => row.student_id === student.id && row.academic_year_id === year?.id,
  )
  if (!enrollment) return []
  return db.quizzes.filter(
    (quiz) =>
      quiz.class_id === enrollment.class_id &&
      quiz.academic_year_id === year.id &&
      quiz.is_published,
  )
}

function teacherQuizIndex(config) {
  const { teacher } = quizTeacher(config)

  const rows = db.quizzes
    .filter((quiz) => quiz.teacher_id === teacher.id)
    .sort((a, b) => b.id - a.id)
    .map((quiz) => serializeQuiz(quiz))

  return ok(config, paginate(config, rows, ['title', 'instructions']))
}

async function teacherQuizStore(config) {
  const { school, teacher, user } = quizTeacher(config)
  const { fields: body, files } = readMultipart(config)

  validate(body, ['class_id', 'subject_id', 'title', 'max_score'])

  const klass = byId(db.classes, body.class_id)
  const subject = byId(db.subjects, body.subject_id)
  if (!klass || klass.school_id !== school.id) throw fail(422, 'The selected class belongs to another school.')
  if (!subject || subject.school_id !== school.id) throw fail(422, 'The selected subject belongs to another school.')

  const year = byId(db.academicYears, body.academic_year_id) ?? currentYearFor(school.id)
  if (!year) throw fail(409, 'No active academic year is configured.')

  assertTeacherAssigned(teacher, klass.id, subject.id, year.id)

  if (body.closes_at && new Date(body.closes_at).getTime() <= Date.now()) {
    throw fail(422, 'The closing time must be in the future.', { closes_at: ['The closing time must be in the future.'] })
  }

  const duplicate = db.quizzes.some(
    (row) =>
      row.class_id === klass.id &&
      row.subject_id === subject.id &&
      row.academic_year_id === year.id &&
      row.title.toLowerCase() === String(body.title).toLowerCase(),
  )
  if (duplicate) {
    throw fail(422, 'A quiz with this title already exists for that class and subject.', {
      title: ['A quiz with this title already exists for that class and subject.'],
    })
  }

  const questions = body.questions ?? []
  const questionErrors = validateQuestions(questions)
  if (Object.keys(questionErrors).length) throw fail(422, 'The given data was invalid.', questionErrors)

  const quiz = {
    id: nextMockId(),
    school_id: school.id,
    teacher_id: teacher.id,
    subject_id: subject.id,
    class_id: klass.id,
    academic_year_id: year.id,
    semester_id: body.semester_id ? Number(body.semester_id) : (currentSemesterFor(school.id, year.id)?.id ?? null),
    title: body.title,
    instructions: body.instructions || null,
    max_score: Number(body.max_score),
    closes_at: body.closes_at || null,
    time_limit_minutes: body.time_limit_minutes ? Number(body.time_limit_minutes) : null,
    attempts_allowed: body.attempts_allowed ? Number(body.attempts_allowed) : 1,
    is_published: false,
    is_locked: false,
    published_at: null,
    created_at: new Date().toISOString(),
  }
  db.quizzes.push(quiz)

  replaceQuizQuestions(quiz, questions)

  await attachFiles(QUIZ_TYPE, quiz.id, school.id, files, 'teacher', 'class', user.id)

  return ok(config, { data: serializeQuiz(quiz) }, 201)
}

/** Mirrors StoreQuizRequest::withValidator(): the key must point at a real option. */
const validateQuestions = (questions) => {
  const errors = {}
  questions.forEach((question, index) => {
    if (!question?.prompt) errors[`questions.${index}.prompt`] = ['The prompt field is required.']
    const options = question?.options ?? []
    if (options.length < 2) errors[`questions.${index}.options`] = ['Each question needs at least 2 options.']
    const correct = question?.correct_option
    if (correct === undefined || correct === null || !options[Number(correct)]) {
      errors[`questions.${index}.correct_option`] = ['Select one of the listed options as the correct answer.']
    }
  })
  return errors
}

const replaceQuizQuestions = (quiz, questions) => {
  db.quizQuestions = db.quizQuestions.filter((row) => row.quiz_id !== quiz.id)
  questions.forEach((question, index) => {
    db.quizQuestions.push({
      id: nextMockId(),
      quiz_id: quiz.id,
      school_id: quiz.school_id,
      prompt: question.prompt,
      options: question.options ?? [],
      correct_option: Number(question.correct_option ?? 0),
      points: question.points ? Number(question.points) : 1,
      sequence: question.sequence ? Number(question.sequence) : index + 1,
    })
  })
}

function teacherQuizShow(config, match) {
  const { teacher } = quizTeacher(config)
  const quiz = findQuiz(config, match[1])
  assertOwnsQuiz(teacher, quiz)
  return ok(config, { data: serializeQuiz(quiz) })
}

async function teacherQuizUpdate(config, match) {
  const { school, teacher, user } = quizTeacher(config)
  const quiz = findQuiz(config, match[1])
  assertOwnsQuiz(teacher, quiz)

  const { fields: body, files } = readMultipart(config)

  if (body.questions !== undefined && quiz.is_locked) {
    throw fail(422, 'Students have already sat this quiz, so its questions can no longer be changed.', {
      questions: ['Students have already sat this quiz, so its questions can no longer be changed.'],
    })
  }

  if (body.title !== undefined) quiz.title = body.title
  if (body.instructions !== undefined) quiz.instructions = body.instructions || null
  if (body.max_score !== undefined) quiz.max_score = Number(body.max_score)
  if (body.closes_at !== undefined) quiz.closes_at = body.closes_at || null
  if (body.time_limit_minutes !== undefined) quiz.time_limit_minutes = body.time_limit_minutes ? Number(body.time_limit_minutes) : null
  if (body.attempts_allowed !== undefined) quiz.attempts_allowed = Number(body.attempts_allowed)

  if (body.questions !== undefined && !quiz.is_locked) {
    const questionErrors = validateQuestions(body.questions ?? [])
    if (Object.keys(questionErrors).length) throw fail(422, 'The given data was invalid.', questionErrors)
    replaceQuizQuestions(quiz, body.questions ?? [])
  }

  await attachFiles(QUIZ_TYPE, quiz.id, school.id, files, 'teacher', 'class', user.id)

  return ok(config, { data: serializeQuiz(quiz) })
}

function teacherQuizDestroy(config, match) {
  const { teacher } = quizTeacher(config)
  const quiz = findQuiz(config, match[1])
  assertOwnsQuiz(teacher, quiz)

  db.quizzes = db.quizzes.filter((row) => row.id !== quiz.id)
  db.quizQuestions = db.quizQuestions.filter((row) => row.quiz_id !== quiz.id)
  db.quizAttempts = db.quizAttempts.filter((row) => row.quiz_id !== quiz.id)
  db.attachments = db.attachments.filter(
    (row) => !(row.attachable_type === QUIZ_TYPE && row.attachable_id === quiz.id),
  )

  return ok(config, { message: 'Quiz deleted.' })
}

function teacherQuizPublish(config, match) {
  const { teacher } = quizTeacher(config)
  const quiz = findQuiz(config, match[1])
  assertOwnsQuiz(teacher, quiz)

  const questions = questionsFor(quiz.id)

  if (questions.length === 0) {
    throw fail(422, 'Add at least one question before publishing this quiz.', {
      questions: ['Add at least one question before publishing this quiz.'],
    })
  }
  const unmarkable = questions.some((question) => !question.options?.[question.correct_option])
  if (unmarkable) {
    throw fail(422, 'Every question needs a correct answer selected.', {
      questions: ['Every question needs a correct answer selected.'],
    })
  }

  if (!quiz.is_published) {
    quiz.is_published = true
    quiz.published_at = new Date().toISOString()

    for (const studentId of enrolledStudentIds(quiz.class_id, quiz.academic_year_id)) {
      const student = byId(db.students, studentId)
      if (!student?.user_id) continue
      db.notifications.unshift({
        id: nextMockId(),
        school_id: quiz.school_id,
        user_id: student.user_id,
        type: 'quiz_published',
        title: `New quiz: ${quiz.title}`,
        message: `New ${byId(db.subjects, quiz.subject_id)?.name} quiz for ${byId(db.classes, quiz.class_id)?.name} · ${questions.length} question${questions.length === 1 ? '' : 's'}${quiz.time_limit_minutes ? ` · ${quiz.time_limit_minutes} minutes` : ''}.`,
        data: { quiz_id: quiz.id },
        read_at: null,
        created_at: new Date().toISOString(),
      })
    }
  }

  return ok(config, { data: serializeQuiz(quiz) })
}

function teacherQuizUnpublish(config, match) {
  const { teacher } = quizTeacher(config)
  const quiz = findQuiz(config, match[1])
  assertOwnsQuiz(teacher, quiz)
  quiz.is_published = false
  return ok(config, { data: serializeQuiz(quiz) })
}

/** Class results with per-question breakdown — mirrors QuizService::resultsFor(). */
function teacherQuizResults(config, match) {
  const { teacher } = quizTeacher(config)
  const quiz = findQuiz(config, match[1])
  assertOwnsQuiz(teacher, quiz)

  const questions = questionsFor(quiz.id)

  const byStudent = new Map()
  for (const attempt of db.quizAttempts.filter((row) => row.quiz_id === quiz.id && row.submitted_at)) {
    const current = byStudent.get(attempt.student_id)
    if (!current || (attempt.score ?? 0) > (current.score ?? 0)) byStudent.set(attempt.student_id, attempt)
  }

  const students = enrolledStudentIds(quiz.class_id, quiz.academic_year_id)
    .map((studentId) => {
      const student = byId(db.students, studentId)
      const attempt = byStudent.get(studentId) ?? null
      return {
        student_id: studentId,
        name: userName(student?.user_id),
        matricule: student?.matricule ?? null,
        score: attempt?.score ?? null,
        max_score: quiz.max_score,
        correct_count: attempt?.correct_count ?? null,
        total_questions: attempt?.total_questions ?? null,
        percentage: attempt?.total_questions ? Math.round((attempt.correct_count / attempt.total_questions) * 1000) / 10 : null,
        attempts: attempt?.attempt ?? 0,
        is_reviewed: Boolean(attempt?.is_reviewed),
        submitted_at: attempt?.submitted_at ?? null,
        attempt_id: attempt?.id ?? null,
        status: attempt ? 'submitted' : 'not_attempted',
      }
    })
    .sort((a, b) => String(a.name).localeCompare(String(b.name)))

  const scores = students.map((row) => row.score).filter((value) => value !== null)
  const passMark = 10

  return ok(config, {
    quiz: serializeQuiz(quiz),
    students,
    questions: questions.map((question) => ({
      id: question.id,
      prompt: question.prompt,
      points: question.points,
      sequence: question.sequence,
      correct_count: [...byStudent.values()].filter((attempt) =>
        Number(attempt.answers?.[question.id]) === question.correct_option,
      ).length,
    })),
    stats: {
      total: students.length,
      submitted: scores.length,
      average: scores.length ? Math.round((scores.reduce((sum, value) => sum + value, 0) / scores.length) * 100) / 100 : null,
      highest: scores.length ? Math.max(...scores) : null,
      lowest: scores.length ? Math.min(...scores) : null,
      pass_rate: scores.length
        ? Math.round((scores.filter((value) => value >= passMark).length / scores.length) * 1000) / 10
        : null,
    },
  })
}

function teacherQuizAttemptShow(config, match) {
  const { teacher } = quizTeacher(config)
  const attempt = findQuizAttempt(config, match[1])
  const quiz = findQuiz(config, attempt.quiz_id)
  assertOwnsQuiz(teacher, quiz)
  return ok(config, { data: serializeQuizAttempt(attempt) })
}

function teacherQuizAttemptReview(config, match) {
  const { teacher } = quizTeacher(config)
  const attempt = findQuizAttempt(config, match[1])
  const quiz = findQuiz(config, attempt.quiz_id)
  assertOwnsQuiz(teacher, quiz)

  const body = readBody(config)
  if (!attempt.submitted_at) throw fail(422, 'This attempt has not been submitted yet.')

  attempt.feedback = body.feedback ?? null
  attempt.is_reviewed = true
  attempt.reviewed_at = new Date().toISOString()
  attempt.reviewed_by = teacher.id

  const student = byId(db.students, attempt.student_id)
  if (student?.user_id) {
    db.notifications.unshift({
      id: nextMockId(),
      school_id: attempt.school_id,
      user_id: student.user_id,
      type: 'quiz_reviewed',
      title: 'Your quiz result is ready',
      message: `"${quiz.title}" was marked ${Number(attempt.score).toFixed(2)}/${quiz.max_score} (${attempt.correct_count}/${attempt.total_questions} correct).`,
      data: { quiz_id: quiz.id, attempt_id: attempt.id, score: attempt.score },
      read_at: null,
      created_at: new Date().toISOString(),
    })
  }

  return ok(config, { data: serializeQuizAttempt(attempt) })
}

function studentQuizIndex(config) {
  const { student } = homeworkStudent(config)

  const rows = studentQuizRows(student)
    .sort((a, b) => b.id - a.id)
    .map((quiz) => {
      const best = bestAttemptFor(student.id, quiz.id)
      return {
        quiz: serializeQuiz(quiz, { withKey: false }),
        attempt: best ? serializeQuizAttempt(best) : null,
        attempts_used: attemptsUsedBy(student.id, quiz.id).length,
      }
    })

  const scores = rows.map((row) => row.attempt?.score).filter((value) => value !== null && value !== undefined)

  return ok(config, {
    data: rows,
    summary: {
      available: rows.length,
      completed: scores.length,
      average: scores.length ? Math.round((scores.reduce((sum, value) => sum + value, 0) / scores.length) * 100) / 100 : null,
    },
  })
}

/**
 * The paper to sit — the answer key is stripped here, not by the client.
 */
function studentQuizPaper(config, match) {
  const { student } = homeworkStudent(config)
  const quiz = findQuiz(config, match[1])

  if (!quiz.is_published) throw fail(403, 'This quiz is not available yet.')

  const enrolled = db.enrollments.some(
    (row) =>
      row.student_id === student.id &&
      row.class_id === quiz.class_id &&
      row.academic_year_id === quiz.academic_year_id,
  )
  if (!enrolled) throw fail(403, 'You are not enrolled in the class this quiz was set for.')

  if (quizClosed(quiz)) throw fail(403, 'This quiz has closed.')

  const remaining = (quiz.attempts_allowed ?? 1) - attemptsUsedBy(student.id, quiz.id).length
  if (remaining <= 0) throw fail(422, 'You have already used every attempt for this quiz.')

  const questions = questionsFor(quiz.id).map((question) => ({
    id: question.id,
    prompt: question.prompt,
    options: question.options,
    points: question.points,
    sequence: question.sequence,
  }))

  return ok(config, {
    quiz: serializeQuiz(quiz, { withKey: false }),
    questions,
    attempts_remaining: remaining,
    points_available: questions.reduce((total, question) => total + (question.points ?? 1), 0),
  })
}

function studentQuizSubmit(config, match) {
  const { student } = homeworkStudent(config)
  const quiz = findQuiz(config, match[1])

  if (!quiz.is_published) throw fail(403, 'This quiz is not available yet.')
  if (quizClosed(quiz)) throw fail(422, 'This quiz has closed.')

  const enrolled = db.enrollments.some(
    (row) =>
      row.student_id === student.id &&
      row.class_id === quiz.class_id &&
      row.academic_year_id === quiz.academic_year_id,
  )
  if (!enrolled) throw fail(403, 'You are not enrolled in the class this quiz was set for.')

  const used = attemptsUsedBy(student.id, quiz.id).length
  if (used >= (quiz.attempts_allowed ?? 1)) {
    throw fail(422, 'You have already used every attempt for this quiz.', {
      answers: ['You have already used every attempt for this quiz.'],
    })
  }

  const body = readBody(config)
  const answers = body.answers ?? {}
  const questions = questionsFor(quiz.id)
  if (questions.length === 0) throw fail(422, 'This quiz has no questions.')

  let correct = 0
  let earned = 0
  const stored = {}
  for (const question of questions) {
    const choice = answers[question.id]
    const index = Number.isInteger(choice) || (typeof choice === 'string' && choice !== '' && !Number.isNaN(Number(choice)))
      ? Number(choice)
      : null
    stored[question.id] = index
    if (index !== null && index === question.correct_option) {
      correct += 1
      earned += question.points ?? 1
    }
  }

  // Scale the earned points onto the quiz's own mark, so a 5-point question
  // really is worth five times a 1-point one.
  const points = questions.reduce((total, question) => total + (question.points ?? 1), 0)
  const score = points > 0 ? Math.round((earned / points) * quiz.max_score * 100) / 100 : 0

  const attempt = {
    id: nextMockId(),
    quiz_id: quiz.id,
    student_id: student.id,
    school_id: quiz.school_id,
    answers: stored,
    correct_count: correct,
    total_questions: questions.length,
    score,
    attempt: used + 1,
    started_at: new Date().toISOString(),
    submitted_at: new Date().toISOString(),
    feedback: null,
    is_reviewed: false,
    reviewed_at: null,
    reviewed_by: null,
  }
  db.quizAttempts.push(attempt)

  // Sitting a paper freezes it, exactly as the backend locks on first attempt.
  if (!quiz.is_locked) quiz.is_locked = true

  return ok(config, { data: serializeQuizAttempt(attempt) }, 201)
}

/** Per-question review of an attempt the student has already submitted. */
function studentQuizReview(config, match) {
  const { student } = homeworkStudent(config)
  const attempt = findQuizAttempt(config, match[1])

  if (attempt.student_id !== student.id) throw fail(403, 'This attempt belongs to another student.')
  if (!attempt.submitted_at) throw fail(403, 'This attempt has not been submitted.')

  const quiz = findQuiz(config, attempt.quiz_id)

  const questions = questionsFor(quiz.id).map((question) => {
    const choice = Number(attempt.answers?.[question.id])
    const chosen = Number.isNaN(choice) ? null : choice
    return {
      id: question.id,
      prompt: question.prompt,
      options: question.options,
      points: question.points,
      sequence: question.sequence,
      chosen,
      correct_option: question.correct_option,
      is_correct: chosen !== null && chosen === question.correct_option,
    }
  })

  return ok(config, {
    attempt: serializeQuizAttempt(attempt),
    quiz: serializeQuiz(quiz, { withKey: false }),
    questions,
  })
}

// ---------------------------------------------------------------------------
// Phase 5.5 — Messaging, school events and the personal calendar
// ---------------------------------------------------------------------------

/**
 * Conversations, tenant-scoped. A conversation id from another school must
 * 404 exactly as Laravel's TenantScope would, never 403 — otherwise the status
 * code leaks that the record exists.
 */
const findConversation = (config, id) => {
  const { school } = requireTenant(config)
  const conversation = scopedFind(db.conversations, id, school.id)
  if (!conversation) throw fail(404, 'No query results for model [Conversation].')
  return conversation
}

const findEvent = (config, id) => {
  const { school } = requireTenant(config)
  const event = scopedFind(db.events, id, school.id)
  if (!event) throw fail(404, 'No query results for model [Event].')
  return event
}

/** Lower id first, so a pair always resolves to one thread. */
const orderedPair = (a, b) => (Number(a) < Number(b) ? [Number(a), Number(b)] : [Number(b), Number(a)])

const isParticipant = (conversation, userId) =>
  Number(conversation.participant_a_id) === Number(userId)
  || Number(conversation.participant_b_id) === Number(userId)

const otherParticipantId = (conversation, userId) =>
  Number(conversation.participant_a_id) === Number(userId)
    ? conversation.participant_b_id
    : conversation.participant_a_id

function messageIndex(config) {
  const { user } = requireTenant(config)
  const rows = db.conversations
    .filter((conversation) => isParticipant(conversation, user.id))
    .sort((a, b) => String(b.last_message_at ?? '').localeCompare(String(a.last_message_at ?? '')) || b.id - a.id)
    .map((conversation) => serializeConversation(conversation, user.id))
  return ok(config, paginate(config, rows))
}

function messageStore(config) {
  const { user, school } = requireTenant(config)
  const body = readBody(config)

  if (!body.user_id) throw fail(422, 'The given data was invalid.', { user_id: ['The user id field is required.'] })

  const other = byId(db.users, body.user_id)
  if (!other) throw fail(422, 'The selected user id is invalid.', { user_id: ['The selected user id is invalid.'] })
  if (Number(other.id) === Number(user.id)) {
    throw fail(422, 'You cannot start a conversation with yourself.', {
      user_id: ['You cannot start a conversation with yourself.'],
    })
  }
  if (other.school_id !== school.id) throw fail(403, 'That person is not at your school.')

  // The safeguarding rule: students reach staff, not each other.
  if (user.role === 'student' && !['teacher', 'admin'].includes(other.role)) {
    throw fail(403, 'Students can message teachers and administrators only.')
  }

  const [a, b] = orderedPair(user.id, other.id)
  const existing = db.conversations.find(
    (row) => row.school_id === school.id
      && Number(row.participant_a_id) === a
      && Number(row.participant_b_id) === b,
  )
  const conversation = existing ?? {
    id: nextMockId(),
    school_id: school.id,
    participant_a_id: a,
    participant_b_id: b,
    last_message_at: null,
  }
  if (!existing) db.conversations.push(conversation)

  return ok(config, { data: serializeConversation(conversation, user.id) }, 201)
}

function messageShow(config, match) {
  const id = match[1]
  const { user } = requireTenant(config)
  const conversation = findConversation(config, id)
  if (!isParticipant(conversation, user.id)) throw fail(403, 'You are not part of this conversation.')

  // Opening a thread is the read receipt.
  db.messages.forEach((message) => {
    if (message.conversation_id === conversation.id
      && message.read_at == null
      && Number(message.sender_id) !== Number(user.id)) {
      message.read_at = new Date().toISOString()
    }
  })

  const rows = db.messages
    .filter((message) => message.conversation_id === conversation.id)
    .sort((a, b) => a.id - b.id)
    .map((message) => serializeMessage(message, user.id))
  return ok(config, paginate(config, rows))
}

function messageSend(config, match) {
  const id = match[1]
  const { user, school } = requireTenant(config)
  const conversation = findConversation(config, id)
  if (!isParticipant(conversation, user.id)) throw fail(403, 'You are not part of this conversation.')

  const body = readBody(config)
  const text = String(body.body ?? '').trim()
  if (!text) throw fail(422, 'The given data was invalid.', { body: ['The body field is required.'] })
  if (text.length > 2000) {
    throw fail(422, 'The given data was invalid.', { body: ['The body field must not exceed 2000 characters.'] })
  }

  const now = new Date().toISOString()
  const message = {
    id: nextMockId(),
    conversation_id: conversation.id,
    school_id: school.id,
    sender_id: user.id,
    body: text,
    read_at: null,
    created_at: now,
  }
  db.messages.push(message)
  conversation.last_message_at = now

  const recipient = byId(db.users, otherParticipantId(conversation, user.id))
  if (recipient) {
    db.notifications.push({
      id: nextMockId(),
      school_id: school.id,
      user_id: recipient.id,
      type: 'message',
      title: `New message from ${user.name}`,
      message: text.length > 140 ? `${text.slice(0, 137)}...` : text,
      data: { conversation_id: conversation.id, message_id: message.id, sender_id: user.id },
      read_at: null,
    })
  }

  return ok(config, { data: serializeMessage(message, user.id) }, 201)
}

function messageRead(config, match) {
  const id = match[1]
  const { user } = requireTenant(config)
  const conversation = findConversation(config, id)
  if (!isParticipant(conversation, user.id)) throw fail(403, 'You are not part of this conversation.')

  let marked = 0
  db.messages.forEach((message) => {
    if (message.conversation_id === conversation.id
      && message.read_at == null
      && Number(message.sender_id) !== Number(user.id)) {
      message.read_at = new Date().toISOString()
      marked += 1
    }
  })
  return ok(config, { marked })
}

function messageUnread(config) {
  const { user } = requireTenant(config)
  const mine = db.conversations.filter((row) => isParticipant(row, user.id)).map((row) => row.id)
  const unread = db.messages.filter(
    (message) => mine.includes(message.conversation_id)
      && message.read_at == null
      && Number(message.sender_id) !== Number(user.id),
  ).length
  return ok(config, { unread })
}

/**
 * Who may be messaged. Students get staff only; super admins are never listed
 * because they belong to the platform, not the school.
 */
function messageRecipients(config) {
  const { user, school } = requireTenant(config)
  const term = String(config.params?.search ?? '').trim().toLowerCase()
  const rows = db.users
    .filter((candidate) => candidate.school_id === school.id
      && Number(candidate.id) !== Number(user.id)
      && candidate.role !== 'super_admin'
      && (user.role !== 'student' || ['teacher', 'admin'].includes(candidate.role))
      && (!term || candidate.name.toLowerCase().includes(term)))
    .sort((a, b) => a.name.localeCompare(b.name))
    .slice(0, 25)
    .map(serializeUserBrief)
  return ok(config, { data: rows })
}

// --- events ----------------------------------------------------------------

const upcomingEvents = (user, days, type) => {
  const now = Date.now()
  const limit = now + days * 86400000
  return db.events
    .filter((event) => event.school_id === user.school_id
      && event.is_published
      && eventVisibleToRole(event, user.role)
      && (!type || event.type === type)
      && new Date(event.starts_at).getTime() <= limit
      && (event.ends_at ? new Date(event.ends_at).getTime() >= now : new Date(event.starts_at).getTime() >= now))
    .sort((a, b) => a.starts_at.localeCompare(b.starts_at))
}

function eventIndex(config) {
  const { user } = requireTenant(config)
  const days = Math.min(Math.max(Number(config.params?.days) || 60, 1), 365)
  const type = config.params?.type || null
  return ok(config, { data: upcomingEvents(user, days, type).map((event) => serializeEvent(event)) })
}

function eventShow(config, match) {
  const id = match[1]
  const { user } = requireTenant(config)
  const event = findEvent(config, id)

  // A draft, or an event aimed at another audience, simply does not exist for
  // this caller — 404 rather than 403, so its existence is not disclosed.
  if (!event.is_published || !eventVisibleToRole(event, user.role)) {
    throw fail(404, 'Event not found.')
  }
  return ok(config, { data: serializeEvent(event) })
}

function adminEventIndex(config) {
  const { school } = requireAdmin(config)
  const rows = db.events
    .filter((event) => event.school_id === school.id)
    .sort((a, b) => b.starts_at.localeCompare(a.starts_at))
    .map((event) => serializeEvent(event, { withAuthor: true }))

  // `paginate` applies ?search= itself, so the searchable fields have to be
  // declared here — pre-filtering and passing no fields returns nothing at all.
  return ok(config, paginate(config, rows, ['title', 'description', 'location']))
}

const EVENT_TYPES = ['assembly', 'exam', 'holiday', 'sports', 'meeting', 'deadline', 'other']
const EVENT_AUDIENCES = ['all', 'students', 'teachers']

function adminEventStore(config) {
  const { user, school } = requireAdmin(config)
  const body = readBody(config)
  const errors = { ...validate(body, ['title', 'type', 'starts_at', 'audience']) }

  if (body.type && !EVENT_TYPES.includes(body.type)) {
    errors.type = ['The selected type is invalid.']
  }
  if (body.audience && !EVENT_AUDIENCES.includes(body.audience)) {
    errors.audience = ['The selected audience is invalid.']
  }
  if (body.ends_at && new Date(body.ends_at) <= new Date(body.starts_at)) {
    errors.ends_at = ['An event must end after it starts.']
  }
  if (Object.keys(errors).length) throw fail(422, 'The given data was invalid.', errors)

  const event = {
    id: nextMockId(),
    school_id: school.id,
    user_id: user.id,
    title: body.title,
    description: body.description || null,
    type: body.type,
    starts_at: body.starts_at,
    ends_at: body.ends_at || null,
    all_day: Boolean(body.all_day),
    location: body.location || null,
    audience: body.audience,
    is_published: false,
    published_at: null,
    created_at: new Date().toISOString(),
  }
  db.events.push(event)
  return ok(config, { data: serializeEvent(event, { withAuthor: true }) }, 201)
}

function adminEventShow(config, match) {
  const id = match[1]
  requireAdmin(config)
  return ok(config, { data: serializeEvent(findEvent(config, id), { withAuthor: true }) })
}

function adminEventUpdate(config, match) {
  const id = match[1]
  requireAdmin(config)
  const event = findEvent(config, id)
  const body = readBody(config)
  const errors = {}

  const startsAt = body.starts_at ?? event.starts_at
  const endsAt = body.ends_at !== undefined ? body.ends_at : event.ends_at

  if (body.type && !EVENT_TYPES.includes(body.type)) errors.type = ['The selected type is invalid.']
  if (body.audience && !EVENT_AUDIENCES.includes(body.audience)) {
    errors.audience = ['The selected audience is invalid.']
  }
  if (endsAt && new Date(endsAt) <= new Date(startsAt)) errors.ends_at = ['An event must end after it starts.']
  if (Object.keys(errors).length) throw fail(422, 'The given data was invalid.', errors)

  if (body.title !== undefined) event.title = body.title
  if (body.description !== undefined) event.description = body.description || null
  if (body.type !== undefined) event.type = body.type
  if (body.starts_at !== undefined) event.starts_at = body.starts_at
  if (body.ends_at !== undefined) event.ends_at = body.ends_at || null
  if (body.all_day !== undefined) event.all_day = Boolean(body.all_day)
  if (body.location !== undefined) event.location = body.location || null
  if (body.audience !== undefined) event.audience = body.audience

  return ok(config, { data: serializeEvent(event, { withAuthor: true }) })
}

function adminEventPublish(config, match) {
  const id = match[1]
  const { school } = requireAdmin(config)
  const event = findEvent(config, id)

  if (!event.is_published) {
    event.is_published = true
    event.published_at = new Date().toISOString()

    const when = event.starts_at
    const body = [event.type.charAt(0).toUpperCase() + event.type.slice(1), when, event.location]
      .filter(Boolean)
      .join(' · ')
    db.users
      .filter((candidate) => candidate.school_id === school.id
        && (event.audience === 'all'
          || candidate.role === (event.audience === 'students' ? 'student' : 'teacher')))
      .forEach((candidate) => {
        db.notifications.push({
          id: nextMockId(),
          school_id: school.id,
          user_id: candidate.id,
          type: 'event',
          title: event.title,
          message: body,
          data: { event_id: event.id },
          read_at: null,
        })
      })
  }
  return ok(config, { data: serializeEvent(event, { withAuthor: true }) })
}

function adminEventUnpublish(config, match) {
  const id = match[1]
  requireAdmin(config)
  const event = findEvent(config, id)
  event.is_published = false
  return ok(config, { data: serializeEvent(event, { withAuthor: true }) })
}

function adminEventDestroy(config, match) {
  const id = match[1]
  requireAdmin(config)
  const event = findEvent(config, id)
  db.events = db.events.filter((row) => row.id !== event.id)
  return ok(config, { message: 'Event deleted.' })
}

// --- calendar --------------------------------------------------------------

const isoDateOf = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`

/** ISO weekday: Monday 1 … Sunday 7, matching how timetable `day` is stored. */
const isoWeekdayOf = (date) => ((date.getDay() + 6) % 7) + 1

const mondayOf = (date) => {
  const copy = new Date(date)
  copy.setDate(copy.getDate() - ((copy.getDay() + 6) % 7))
  return copy
}

const sundayOf = (date) => {
  const copy = mondayOf(date)
  copy.setDate(copy.getDate() + 6)
  return copy
}

/**
 * The class/subject pairs a user's calendar is built from, mirroring
 * CalendarService::scopeFor. A student has one class and every subject in it;
 * a teacher has each class/subject pair they are assigned.
 */
/**
 * Whether this caller may act on one specific student. Mirrors
 * AcademicScopeService::sees — an administrator may act on any pupil in their
 * school, a teacher only on pupils enrolled in a class they hold, a student
 * only on themselves.
 */
const academicSeesStudent = (user, student) => {
  if (user.role === 'admin') return Number(student.school_id) === Number(user.school_id)
  if (user.role === 'student') {
    const own = studentForUser(user.id, user.school_id)
    return Boolean(own) && Number(own.id) === Number(student.id)
  }
  const year = currentYearFor(user.school_id)
  const scope = academicScope(user)
  if (!year || !scope) return false
  return db.enrollments.some(
    (enrollment) => Number(enrollment.student_id) === Number(student.id)
      && Number(enrollment.academic_year_id) === Number(year.id)
      && scope.classes.map(Number).includes(Number(enrollment.class_id)),
  )
}

const academicScope = (user) => {
  const year = currentYearFor(user.school_id)
  if (!year) return null

  if (user.role === 'student') {
    // Both lookups are school-scoped, so the school id is required — without
    // it they match nothing and the calendar silently loses every lesson.
    const student = studentForUser(user.id, user.school_id)
    if (!student) return null
    const enrollment = db.enrollments.find(
      (row) => row.student_id === student.id && row.academic_year_id === year.id,
    )
    return enrollment
      ? { classes: [enrollment.class_id], pairs: [], isTeacher: false }
      : null
  }

  if (user.role === 'teacher') {
    const teacher = teacherForUser(user.id, user.school_id)
    if (!teacher) return null
    const rows = db.teachingAssignments.filter(
      (row) => row.teacher_id === teacher.id && row.academic_year_id === year.id,
    )
    if (rows.length === 0) return null
    return {
      classes: [...new Set(rows.map((row) => row.class_id))],
      pairs: [...new Set(rows.map((row) => `${row.class_id}:${row.subject_id}`))],
      isTeacher: true,
    }
  }

  return null
}

const inPairScope = (row, scope) =>
  scope.isTeacher
    ? scope.pairs.includes(`${row.class_id}:${row.subject_id}`)
    : scope.classes.includes(row.class_id)

const calendarLessons = (user, scope, from, to) => {
  const year = currentYearFor(user.school_id)
  const rows = db.timetableEntries.filter(
    (entry) => entry.academic_year_id === year.id
      && (scope.isTeacher
        ? scope.pairs.includes(`${entry.class_id}:${entry.subject_id}`)
        : scope.classes.includes(entry.class_id)),
  )

  const items = []
  const cursor = new Date(from)
  cursor.setHours(0, 0, 0, 0)
  const last = new Date(to)
  last.setHours(0, 0, 0, 0)

  let guard = 0
  while (cursor <= last && guard < 100) {
    const weekday = isoWeekdayOf(cursor)
    const date = isoDateOf(cursor)
    rows
      .filter((entry) => Number(entry.day) === weekday)
      .forEach((entry) => {
        items.push({
          kind: 'lesson',
          id: entry.id,
          title: byId(db.subjects, entry.subject_id)?.name ?? 'Lesson',
          subtitle: byId(db.classes, entry.class_id)?.name ?? null,
          starts_at: `${date}T${entry.start}:00`,
          ends_at: `${date}T${entry.end}:00`,
          all_day: false,
          url: user.role === 'student' ? '/student/timetable' : '/teacher/timetable',
        })
      })
    cursor.setDate(cursor.getDate() + 1)
    guard += 1
  }
  return items
}

const betweenInclusive = (value, from, to) => {
  const date = String(value).slice(0, 10)
  return date >= from && date <= to
}

const calendarExams = (user, scope, from, to) => {
  const year = currentYearFor(user.school_id)
  return db.exams
    .filter((exam) => exam.academic_year_id === year.id
      && betweenInclusive(exam.date, from, to)
      && (scope.isTeacher
        ? scope.pairs.includes(`${exam.class_id}:${exam.subject_id}`)
        : scope.classes.includes(exam.class_id)))
    .map((exam) => ({
      kind: 'exam',
      id: exam.id,
      title: `${byId(db.subjects, exam.subject_id)?.name ?? 'Exam'} exam`,
      subtitle: exam.room || byId(db.classes, exam.class_id)?.name || null,
      starts_at: `${exam.date}T${exam.start}:00`,
      ends_at: `${exam.date}T${exam.end}:00`,
      all_day: false,
      url: user.role === 'student' ? '/student/exams' : '/teacher/exams',
    }))
}

const calendarHomework = (user, scope, from, to) =>
  db.homeworkAssignments
    .filter((row) => row.is_published
      && betweenInclusive(row.due_at, from, to)
      && inPairScope(row, scope))
    .map((row) => ({
      kind: 'homework',
      id: row.id,
      title: row.title,
      subtitle: `${byId(db.subjects, row.subject_id)?.name ?? 'Subject'} due`,
      starts_at: row.due_at,
      ends_at: row.due_at,
      all_day: false,
      url: user.role === 'student' ? '/student/homework' : '/teacher/homework',
    }))

const calendarQuizzes = (user, scope, from, to) =>
  db.quizzes
    .filter((row) => row.is_published
      && row.closes_at
      && betweenInclusive(row.closes_at, from, to)
      && inPairScope(row, scope))
    .map((row) => ({
      kind: 'quiz',
      id: row.id,
      title: row.title,
      subtitle: `${byId(db.subjects, row.subject_id)?.name ?? 'Subject'} closes`,
      starts_at: row.closes_at,
      ends_at: row.closes_at,
      all_day: false,
      url: user.role === 'student' ? '/student/quizzes' : '/teacher/quizzes',
    }))

const calendarEvents = (user, from, to) =>
  db.events
    .filter((event) => event.school_id === user.school_id
      && event.is_published
      && eventVisibleToRole(event, user.role)
      && betweenInclusive(event.starts_at, from, to))
    .map((event) => ({
      kind: 'event',
      id: event.id,
      title: event.title,
      subtitle: event.location || event.type.charAt(0).toUpperCase() + event.type.slice(1),
      starts_at: event.starts_at,
      ends_at: event.ends_at ?? null,
      all_day: Boolean(event.all_day),
      url: null,
    }))

const calendarItems = (user, from, to) => {
  const scope = academicScope(user)
  let items = calendarEvents(user, from, to)

  if (scope) {
    items = items
      .concat(calendarLessons(user, scope, from, to))
      .concat(calendarExams(user, scope, from, to))
      .concat(calendarHomework(user, scope, from, to))
      .concat(calendarQuizzes(user, scope, from, to))
  }

  return items.sort(
    (a, b) => String(a.starts_at).localeCompare(String(b.starts_at)) || a.title.localeCompare(b.title),
  )
}

function calendarIndex(config) {
  const { user } = requireTenant(config)
  const params = config.params ?? {}
  const iso = /^\d{4}-\d{2}-\d{2}$/

  let from = iso.test(params.from ?? '') ? params.from : isoDateOf(mondayOf(new Date()))
  const requestedTo = iso.test(params.to ?? '') ? params.to : isoDateOf(sundayOf(new Date()))

  // Clamp the span, as the Laravel controller does, rather than refusing.
  const earliest = new Date(from)
  earliest.setDate(earliest.getDate() + 91)
  const limit = isoDateOf(earliest)
  const to = requestedTo > limit ? limit : requestedTo
  if (to < from) from = to

  return ok(config, { from, to, data: calendarItems(user, from, to) })
}

function calendarToday(config) {
  const { user } = requireTenant(config)
  const date = isoDateOf(new Date())
  return ok(config, { date, data: calendarItems(user, date, date) })
}

// ---------------------------------------------------------------------------
// Phase 5.6 — analytics and the pastoral register
// ---------------------------------------------------------------------------

/**
 * Tenant-scoped student lookup. A foreign-school id must 404, never 403.
 */
const findStudentRow = (config, id) => {
  const { school } = requireTenant(config)
  const student = scopedFind(db.students, id, school.id)
  if (!student) throw fail(404, 'No query results for model [Student].')
  return student
}

const gradeAverage = (grade) => weightedAverageOf(grade)

const meanOf = (values) => {
  const clean = values.filter((value) => value !== null && value !== undefined)
  return clean.length === 0 ? null : clean.reduce((sum, value) => sum + value, 0) / clean.length
}

/**
 * Attendance as present-or-late over present, late and absent. An excused
 * absence sits outside both sides: a medical absence is not a warning sign.
 */
const attendanceCounts = (studentId) => {
  const counts = {}
  db.attendances
    .filter((row) => Number(row.student_id) === Number(studentId))
    .forEach((row) => {
      counts[row.status] = (counts[row.status] ?? 0) + 1
    })
  return counts
}

const attendanceRateOf = (counts) => {
  const attended = (counts.present ?? 0) + (counts.late ?? 0)
  const counted = attended + (counts.absent ?? 0)
  return counted > 0 ? Math.round((attended / counted) * 1000) / 10 : null
}

const semesterPair = (schoolId) => {
  const year = currentYearFor(schoolId)
  if (!year) return { current: null, previous: null }
  const current = db.semesters.find((row) => row.academic_year_id === year.id && row.is_current)
  const previous = current
    ? db.semesters.find((row) => row.academic_year_id === year.id && row.sequence === current.sequence - 1)
    : null
  return { current: current?.id ?? null, previous: previous?.id ?? null }
}

/** The class a student is enrolled in *this* year. */
const currentClassOf = (studentId, schoolId) => {
  const year = currentYearFor(schoolId)
  const enrollment = db.enrollments.find(
    (row) => Number(row.student_id) === Number(studentId) && row.academic_year_id === year?.id,
  )
  return enrollment ? byId(db.classes, enrollment.class_id) : null
}

const scopedGrades = (studentIds, scope) =>
  db.grades.filter((grade) => studentIds.includes(Number(grade.student_id))
    && (!scope || inPairScope(grade, scope)))

const homeworkStats = (studentId, scope) => {
  const year = currentYearFor(db.grades.find((g) => Number(g.student_id) === Number(studentId))?.school_id)
  const assignments = db.homeworkAssignments.filter(
    (row) => row.is_published
      && (!year || row.academic_year_id === year.id)
      && (!scope || inPairScope(row, scope)),
  )
  if (assignments.length === 0) return null

  const submitted = new Set(
    db.homeworkSubmissions
      .filter((row) => Number(row.student_id) === Number(studentId)
        && row.submitted_at
        && assignments.some((assignment) => assignment.id === row.homework_assignment_id))
      .map((row) => row.homework_assignment_id),
  )
  const now = Date.now()
  const missing = assignments.filter(
    (row) => !submitted.has(row.id) && row.due_at && new Date(row.due_at).getTime() < now,
  ).length

  return { published: assignments.length, submitted: submitted.size, missing }
}

const quizStats = (studentId, scope) => {
  const quizzes = db.quizzes.filter(
    (row) => row.is_published && (!scope || inPairScope(row, scope)),
  )
  if (quizzes.length === 0) return null

  const attempts = db.quizAttempts.filter(
    (row) => Number(row.student_id) === Number(studentId)
      && row.submitted_at
      && quizzes.some((quiz) => quiz.id === row.quiz_id),
  )

  const percentages = attempts
    .map((attempt) => {
      const quiz = byId(db.quizzes, attempt.quiz_id)
      const max = Number(quiz?.max_score ?? 0)
      return max > 0 && attempt.score != null ? (Number(attempt.score) / max) * 100 : null
    })
    .filter((value) => value !== null)

  return {
    attempts: attempts.length,
    percentage: percentages.length === 0
      ? null
      : Math.round((percentages.reduce((sum, value) => sum + value, 0) / percentages.length) * 100) / 100,
  }
}

/**
 * One student's signals. Mirrors AtRiskService: each signal carries a reason in
 * words, because a bare score does not tell a form teacher what to do.
 */
const assessStudent = (student, scope) => {
  const grades = scopedGrades([student.id], scope)
  const averages = grades.map(gradeAverage).filter((value) => value != null)
  const overall = averages.length === 0
    ? null
    : Math.round((averages.reduce((sum, value) => sum + value, 0) / averages.length) * 100) / 100

  const signals = []
  const pass = GRADING.pass_mark

  if (overall !== null && overall < pass) {
    signals.push(overall < pass - AT_RISK.critical_margin
      ? { code: 'low_average', label: 'Low average', severity: 'critical', detail: `Term average is ${overall} out of 20, well below the pass mark of ${pass}.` }
      : { code: 'low_average', label: 'Low average', severity: 'warning', detail: `Term average is ${overall} out of 20, just below the pass mark of ${pass}.` })
  }

  const failing = grades
    .map((grade) => ({ subject: byId(db.subjects, grade.subject_id)?.name ?? 'Subject', average: gradeAverage(grade) }))
    .filter((row) => row.average != null && row.average < pass)
  if (failing.length >= AT_RISK.failing_subjects) {
    signals.push({
      code: 'failing_subjects',
      label: 'Failing subjects',
      severity: 'warning',
      detail: `Below the pass mark in ${failing.length} subject(s): ${failing.slice(0, 4).map((row) => row.subject).join(', ')}.`,
    })
  }

  const semesters = semesterPair(student.school_id)
  if (semesters.current && semesters.previous) {
    const meanFor = (semesterId) => meanOf(
      grades.filter((grade) => Number(grade.semester_id) === Number(semesterId)).map(gradeAverage),
    )
    const current = meanFor(semesters.current)
    const previous = meanFor(semesters.previous)
    if (current !== null && previous !== null) {
      const drop = Math.round((previous - current) * 100) / 100
      if (drop >= AT_RISK.decline_points) {
        signals.push({
          code: 'declining',
          label: 'Declining',
          severity: 'warning',
          detail: `Average fell from ${Math.round(previous * 100) / 100} to ${Math.round(current * 100) / 100} between semesters (${drop} points).`,
        })
      }
    }
  }

  const homework = homeworkStats(student.id, scope)
  if (homework) {
    if (homework.missing >= AT_RISK.missing_homework) {
      signals.push({
        code: 'missing_homework',
        label: 'Missing homework',
        severity: 'critical',
        detail: `${homework.missing} published assignment(s) past their deadline with nothing submitted.`,
      })
    }
    const rate = Math.round((homework.submitted / homework.published) * 1000) / 10
    if (rate < AT_RISK.submission_rate) {
      signals.push({
        code: 'low_submission_rate',
        label: 'Low submission rate',
        severity: 'warning',
        detail: `Handed in ${homework.submitted} of ${homework.published} assignments (${rate}%).`,
      })
    }
  }

  const attendance = attendanceCounts(student.id)
  const attendanceRate = attendanceRateOf(attendance)
  if (attendanceRate !== null && attendanceRate < AT_RISK.attendance_rate) {
    signals.push({
      code: 'poor_attendance',
      label: 'Poor attendance',
      severity: 'warning',
      detail: `Attendance is ${attendanceRate}% against a ${AT_RISK.attendance_rate}% expectation.`,
    })
  }

  const quizzes = quizStats(student.id, scope)
  if (quizzes?.percentage !== null && quizzes?.percentage !== undefined && quizzes.percentage < AT_RISK.quiz_average) {
    signals.push({
      code: 'low_quiz_average',
      label: 'Low quiz scores',
      severity: 'warning',
      detail: `Averaging ${quizzes.percentage}% on auto-marked quizzes, below the ${AT_RISK.quiz_average}% expectation.`,
    })
  }

  const klass = currentClassOf(student.id, student.school_id)

  return {
    // Duplicated from `student` deliberately: it is the list row key.
    id: student.id,
    student: {
      id: student.id,
      name: userName(student.user_id),
      matricule: student.matricule,
      class: { id: klass?.id ?? null, name: klass?.name ?? null },
    },
    average: overall,
    signals,
    severity: signals.some((signal) => signal.severity === 'critical')
      ? 'critical'
      : (signals.length > 0 ? 'warning' : null),
    attendance: attendanceRate,
    homework,
    quizzes,
  }
}

/** Students this caller may assess: admin sees the school, a teacher their classes. */
const assessableStudents = (user, scope) => {
  const year = currentYearFor(user.school_id)
  if (!year) return []
  return db.students.filter((student) => student.school_id === user.school_id
    && db.enrollments.some((row) => Number(row.student_id) === Number(student.id)
      && row.academic_year_id === year.id
      && (!scope || scope.classes.includes(row.class_id))))
}

const riskSummary = (entries) => {
  const flagged = entries.filter((entry) => entry.signals.length > 0)
  const byClass = {}
  flagged.forEach((entry) => {
    const name = entry.student.class?.name ?? 'Unassigned'
    byClass[name] = (byClass[name] ?? 0) + 1
  })
  return {
    flagged: flagged.length,
    critical: flagged.filter((entry) => entry.severity === 'critical').length,
    warning: flagged.filter((entry) => entry.severity === 'warning').length,
    monitored: entries.length - flagged.length,
    by_class: Object.entries(byClass)
      .sort((a, b) => b[1] - a[1])
      .map(([label, value]) => ({ label, value })),
  }
}

function analyticsOverview(config) {
  const { user } = requireTenant(config)
  const scope = user.role === 'admin' ? null : academicScope(user)
  if (user.role !== 'admin' && !scope) throw fail(403, 'You have no classes assigned this year.')

  const year = currentYearFor(user.school_id)
  const students = assessableStudents(user, scope)
  const ids = students.map((student) => student.id)
  const entries = students.map((student) => assessStudent(student, scope))

  const averages = entries.map((entry) => entry.average).filter((value) => value != null)
  const passing = averages.filter((value) => value >= GRADING.pass_mark)

  const byClassMap = {}
  db.classes
    .filter((klass) => klass.school_id === user.school_id && (!scope || scope.classes.includes(klass.id)))
    .forEach((klass) => {
      const enrolled = db.enrollments
        .filter((row) => row.class_id === klass.id && row.academic_year_id === year?.id)
        .map((row) => Number(row.student_id))

      // A class with nobody in it is not a finding, and reporting its average
      // as 0.0 would read as "this class is failing" rather than "no data".
      if (enrolled.length === 0) return

      const values = entries
        .filter((entry) => enrolled.includes(Number(entry.student.id)) && entry.average != null)
        .map((entry) => entry.average)
      byClassMap[klass.name] = {
        label: klass.name,
        value: values.length === 0
          ? null
          : Math.round((values.reduce((sum, v) => sum + v, 0) / values.length) * 100) / 100,
        students: enrolled.length,
      }
    })

  const buckets = GRADING.mentions.map((mention) => ({ label: mention.label, value: 0 }))
  averages.forEach((value) => {
    // Mentions are listed highest-first, so the first threshold met wins.
    const index = GRADING.mentions.findIndex((mention) => value >= mention.min)
    if (index >= 0) buckets[index].value += 1
  })

  const attendance = {}
  db.attendances
    .filter((row) => (!scope ? ids.includes(Number(row.student_id)) : scope.classes.includes(row.class_id)))
    .forEach((row) => {
      attendance[row.status] = (attendance[row.status] ?? 0) + 1
    })
  const attendedTotal = (attendance.present ?? 0) + (attendance.late ?? 0)
  const countedTotal = attendedTotal + (attendance.absent ?? 0)

  const assignments = db.homeworkAssignments.filter(
    (row) => row.is_published && (!year || row.academic_year_id === year.id) && (!scope || inPairScope(row, scope)),
  )
  const submissions = db.homeworkSubmissions.filter(
    (row) => row.submitted_at && assignments.some((assignment) => assignment.id === row.homework_assignment_id),
  )
  const expected = assignments.length * ids.length

  const quizzes = db.quizzes.filter(
    (row) => row.is_published && (!year || row.academic_year_id === year.id) && (!scope || inPairScope(row, scope)),
  )
  const attempts = db.quizAttempts.filter(
    (row) => row.submitted_at && quizzes.some((quiz) => quiz.id === row.quiz_id),
  )
  const quizPercentages = attempts
    .map((attempt) => {
      const quiz = byId(db.quizzes, attempt.quiz_id)
      const max = Number(quiz?.max_score ?? 0)
      return max > 0 && attempt.score != null ? (Number(attempt.score) / max) * 100 : null
    })
    .filter((value) => value !== null)

  return ok(config, {
    data: {
      academic_year: year ? { id: year.id, name: year.name } : null,
      scope: scope
        ? { type: 'teacher', classes: scope.classes.map((id) => byId(db.classes, id)?.name).filter(Boolean) }
        : { type: 'school' },
      counts: scope
        ? {
            students: ids.length,
            classes: scope.classes.length,
            subjects: new Set(scope.pairs.map((pair) => pair.split(':')[1])).size,
            teachers: new Set(
              db.teachingAssignments
                .filter((row) => scope.classes.includes(row.class_id))
                .map((row) => row.teacher_id),
            ).size,
          }
        : {
            students: db.students.filter((row) => row.school_id === user.school_id).length,
            teachers: db.teachers.filter((row) => row.school_id === user.school_id).length,
            classes: db.classes.filter((row) => row.school_id === user.school_id).length,
            subjects: db.subjects.filter((row) => row.school_id === user.school_id).length,
          },
      performance: {
        average: averages.length === 0
          ? null
          : Math.round((averages.reduce((sum, v) => sum + v, 0) / averages.length) * 100) / 100,
        pass_rate: averages.length === 0
          ? null
          : Math.round((passing.length / averages.length) * 1000) / 10,
        graded_students: averages.length,
      },
      by_class: Object.values(byClassMap),
      distribution: buckets,
      attendance: {
        rate: countedTotal > 0 ? Math.round((attendedTotal / countedTotal) * 1000) / 10 : null,
        records: Object.values(attendance).reduce((sum, value) => sum + value, 0),
        present: attendance.present ?? 0,
        late: attendance.late ?? 0,
        absent: attendance.absent ?? 0,
        excused: attendance.excused ?? 0,
      },
      engagement: {
        assignments_published: assignments.length,
        submissions: submissions.length,
        submission_rate: expected > 0 ? Math.round((submissions.length / expected) * 1000) / 10 : null,
        quizzes_published: quizzes.length,
        quiz_attempts: attempts.length,
        quiz_average: quizPercentages.length === 0
          ? null
          : Math.round((quizPercentages.reduce((sum, v) => sum + v, 0) / quizPercentages.length) * 10) / 10,
      },
      at_risk: riskSummary(entries),
    },
  })
}

function analyticsRegister(config) {
  const { user } = requireTenant(config)
  const params = config.params ?? {}
  const scope = user.role === 'admin' ? null : academicScope(user)
  if (user.role !== 'admin' && !scope) throw fail(403, 'You have no classes assigned this year.')

  const classId = params.class_id ? Number(params.class_id) : null
  const severity = ['warning', 'critical'].includes(params.severity) ? params.severity : null

  let entries = assessableStudents(user, scope).map((student) => assessStudent(student, scope))

  if (classId) {
    entries = entries.filter((entry) => Number(entry.student.class?.id) === classId)
  }
  entries = entries.filter((entry) => entry.signals.length > 0)
  if (severity) entries = entries.filter((entry) => entry.severity === severity)

  // Worst first: critical, then lowest average, then name. Matches
  // AtRiskService::compareEntries so the two backends cannot disagree on order.
  // A missing average sorts last — no data is not the worst possible mark.
  const rows = entries.sort(
    (a, b) => (a.severity === 'critical' ? 0 : 1) - (b.severity === 'critical' ? 0 : 1)
      || (a.average ?? Number.MAX_VALUE) - (b.average ?? Number.MAX_VALUE)
      || a.student.name.localeCompare(b.student.name),
  )

  // `paginate` applies ?search= itself, so the searchable fields have to be
  // declared here — filtering twice with an empty field list returns nothing.
  return ok(config, paginate(config, rows, ['student.name', 'student.matricule']))
}

function analyticsStudent(config, match) {
  const { user } = requireTenant(config)
  const student = findStudentRow(config, match[1])

  // A student may only review their own record.
  if (user.role === 'student' && Number(student.user_id) !== Number(user.id)) {
    throw fail(403, 'You can only review your own record.')
  }

  const scope = user.role === 'admin' ? null : academicScope(user)
  if (user.role === 'teacher') {
    const year = currentYearFor(user.school_id)
    const enrolled = db.enrollments.some((row) => Number(row.student_id) === Number(student.id)
      && row.academic_year_id === year?.id
      && scope?.classes.includes(row.class_id))
    if (!enrolled) throw fail(403, 'That student is not in one of your classes.')
  }

  return ok(config, { data: assessStudent(student, scope) })
}

function studentInsights(config) {
  const { user, school } = requireTenant(config)
  const student = db.students.find(
    (row) => Number(row.user_id) === Number(user.id) && row.school_id === school.id,
  )
  if (!student) return ok(config, { data: { signals: [], average: null, severity: null, attendance: null, student: null } })

  return ok(config, { data: assessStudent(student, null) })
}


/*
 * Role guards for the analytics routes. The Laravel side enforces this with the
 * `role:admin` / `role:teacher` route middleware; the mock has no route-level
 * middleware, so the check has to live here or a student could read the whole
 * school's numbers by asking for the admin path.
 */
function adminAnalyticsOverview(config) {
  requireAdmin(config)
  return analyticsOverview(config)
}

function adminAnalyticsRegister(config) {
  requireAdmin(config)
  return analyticsRegister(config)
}

function adminAnalyticsStudent(config, match) {
  requireAdmin(config)
  return analyticsStudent(config, match)
}

function teacherAnalyticsOverview(config) {
  requireRole(config, 'teacher')
  return analyticsOverview(config)
}

function teacherAnalyticsRegister(config) {
  requireRole(config, 'teacher')
  return analyticsRegister(config)
}

function teacherAnalyticsStudent(config, match) {
  requireRole(config, 'teacher')
  return analyticsStudent(config, match)
}

function studentInsightsRoute(config) {
  requireRole(config, 'student')
  return studentInsights(config)
}

const ROUTES = [
  ['post', /^\/login$/, login],
  ['post', /^\/logout$/, logout],
  ['get', /^\/user$/, me],
  ['get', /^\/tenant$/, tenantShow],
  ['get', /^\/school\/([\w-]+)$/, publicSchool],
  ['get', /^\/onboarding\/plans$/, onboardingPlans],
  ['post', /^\/onboarding\/schools$/, onboardingRegister],
  ['get', /^\/notifications$/, listNotifications],
  ['post', /^\/notifications\/read-all$/, readAllNotifications],
  ['post', /^\/notifications\/(\d+)\/read$/, readNotification],
  ['get', /^\/announcements$/, listAnnouncements],
  ['get', /^\/student\/dashboard$/, studentDashboard],
  ['get', /^\/student\/grades$/, studentGrades],
  ['get', /^\/student\/report-card$/, studentReportCard],
  ['get', /^\/student\/timetable$/, studentTimetable],
  ['get', /^\/student\/requests\/types$/, studentRequestTypes],
  ['get', /^\/student\/requests$/, studentListRequests],
  ['post', /^\/student\/requests$/, studentCreateRequest],
  ['get', /^\/student\/documents$/, studentListDocuments],
  ['get', /^\/teacher\/dashboard$/, teacherDashboard],
  ['get', /^\/teacher\/assignments$/, teacherAssignmentsList],
  ['get', /^\/teacher\/classes\/(\d+)\/subjects\/(\d+)\/students$/, teacherClassStudents],
  ['get', /^\/teacher\/classes\/(\d+)\/attendance$/, teacherAttendanceGet],
  ['post', /^\/teacher\/classes\/(\d+)\/attendance$/, teacherAttendancePost],
  ['get', /^\/admin\/attendance$/, adminAttendanceGet],
  ['post', /^\/admin\/attendance$/, adminAttendancePost],
  ['get', /^\/admin\/semesters$/, adminListSemesters],
  ['post', /^\/admin\/semesters$/, adminCreateSemester],
  ['post', /^\/admin\/semesters\/(\d+)\/activate$/, adminActivateSemester],
  ['delete', /^\/admin\/semesters\/(\d+)$/, adminDeleteSemester],
  ['get', /^\/admin\/grade-components$/, adminListGradeComponents],
  ['post', /^\/admin\/grade-components$/, adminCreateGradeComponent],
  ['put', /^\/admin\/grade-components\/(\d+)$/, adminUpdateGradeComponent],
  ['delete', /^\/admin\/grade-components\/(\d+)$/, adminDeleteGradeComponent],
  ['get', /^\/admin\/exams\/ranking$/, adminExamRanking],
  ['get', /^\/admin\/exams$/, adminListExams],
  ['post', /^\/admin\/exams$/, adminCreateExam],
  ['delete', /^\/admin\/exams\/(\d+)$/, adminDeleteExam],
  ['post', /^\/admin\/import\/preview$/, adminImportPreview],
  ['post', /^\/admin\/import$/, adminImport],
  ['get', /^\/student\/attendance$/, studentAttendance],
  ['get', /^\/student\/transcript$/, studentTranscript],
  ['get', /^\/student\/exams$/, studentExams],
  ['get', /^\/teacher\/classes\/(\d+)\/subjects\/(\d+)\/gradebook$/, teacherGradebook],
  ['post', /^\/teacher\/classes\/(\d+)\/subjects\/(\d+)\/grades$/, teacherSaveGrades],
  ['get', /^\/teacher\/exams\/ranking$/, adminExamRanking],
  ['get', /^\/teacher\/exams$/, teacherListExams],
  ['get', /^\/admin\/dashboard$/, adminDashboard],
  ['get', /^\/admin\/billing$/, billingGet],
  ['post', /^\/admin\/billing\/upgrade$/, billingUpgrade],
  ['post', /^\/admin\/billing\/renew$/, billingRenew],
  ['get', /^\/admin\/settings$/, settingsGet],
  ['patch', /^\/admin\/settings$/, settingsUpdate],
  ['get', /^\/admin\/academic-years$/, adminListAcademicYears],
  ['post', /^\/admin\/academic-years$/, adminCreateAcademicYear],
  ['post', /^\/admin\/academic-years\/(\d+)\/activate$/, adminActivateYear],
  ['put', /^\/admin\/academic-years\/(\d+)$/, adminUpdateAcademicYear],
  ['delete', /^\/admin\/academic-years\/(\d+)$/, adminDeleteAcademicYear],
  ['get', /^\/admin\/classes$/, adminListClasses],
  ['post', /^\/admin\/classes$/, adminCreateClass],
  ['get', /^\/admin\/subjects$/, adminListSubjects],
  ['post', /^\/admin\/subjects$/, adminCreateSubject],
  ['put', /^\/admin\/subjects\/(\d+)$/, adminUpdateSubject],
  ['delete', /^\/admin\/subjects\/(\d+)$/, adminDeleteSubject],
  ['get', /^\/admin\/teachers$/, adminListTeachers],
  ['post', /^\/admin\/teachers$/, adminCreateTeacher],
  ['get', /^\/admin\/students$/, adminListStudents],
  ['post', /^\/admin\/students$/, adminCreateStudent],
  ['get', /^\/admin\/teaching-assignments$/, adminListAssignments],
  ['post', /^\/admin\/teaching-assignments$/, adminCreateAssignment],
  ['delete', /^\/admin\/teaching-assignments\/(\d+)$/, adminDeleteAssignment],
  ['get', /^\/admin\/timetable$/, adminTimetable],
  ['post', /^\/admin\/timetable\/entries$/, adminCreateTimetableEntry],
  ['put', /^\/admin\/timetable\/entries\/(\d+)$/, adminUpdateTimetableEntry],
  ['delete', /^\/admin\/timetable\/entries\/(\d+)$/, adminDeleteTimetableEntry],
  ['get', /^\/admin\/requests$/, adminListRequests],
  ['get', /^\/admin\/requests\/triage$/, adminRequestTriage],
  ['post', /^\/admin\/requests\/(\d+)\/status$/, adminUpdateRequest],
  ['post', /^\/admin\/requests\/(\d+)\/generate-document$/, adminGenerateDocument],
  ['post', /^\/admin\/announcements$/, adminCreateAnnouncement],
  ['post', /^\/admin\/announcements\/draft$/, adminDraftAnnouncement],
  ['get', /^\/super-admin\/dashboard$/, superAdminDashboard],
  ['get', /^\/super-admin\/schools$/, superAdminListSchools],
  ['post', /^\/super-admin\/schools$/, superAdminCreateSchool],
  ['get', /^\/super-admin\/schools\/(\d+)$/, superAdminGetSchool],
  ['post', /^\/super-admin\/schools\/(\d+)\/status$/, superAdminSchoolStatus],
  ['get', /^\/super-admin\/schools\/(\d+)\/users$/, superAdminSchoolUsers],
  ['get', /^\/super-admin\/plans$/, superAdminListPlans],
  ['post', /^\/super-admin\/plans$/, superAdminCreatePlan],
  ['put', /^\/super-admin\/plans\/(\d+)$/, superAdminUpdatePlan],
  ['get', /^\/super-admin\/subscriptions$/, superAdminListSubscriptions],
  ['get', /^\/super-admin\/payments$/, superAdminListPayments],
  ['post', /^\/forgot-password$/, forgotPassword],
  ['post', /^\/reset-password$/, resetPassword],
  ['post', /^\/password$/, changePassword],
  ['get', /^\/profile$/, showProfile],
  ['patch', /^\/profile$/, updateProfile],
  ['post', /^\/profile\/sign-out-others$/, signOutOthers],
  ['get', /^\/verify\/([^/]+)$/, verifyDocumentCode],
  ['get', /^\/student\/report-card\/pdf$/, studentReportCardPdf],
  ['get', /^\/student\/transcript\/pdf$/, studentTranscriptPdf],
  ['get', /^\/admin\/students\/(\d+)\/report-card$/, adminStudentReportCardPdf],
  ['get', /^\/admin\/students\/(\d+)\/transcript$/, adminStudentTranscriptPdf],
  ['post', /^\/admin\/classes\/(\d+)\/report-cards$/, adminClassReportCards],
  ['get', /^\/admin\/payments\/(\d+)\/receipt$/, adminPaymentReceiptPdf],
  ['get', /^\/admin\/audit-logs$/, adminAuditLogs],
  ['get', /^\/teacher\/timetable$/, teacherTimetable],

  // Homework — the specific paths must precede the bare `/{id}` routes so the
  // id pattern cannot swallow "/publish" and friends.
  ['get', /^\/teacher\/homework$/, teacherHomeworkIndex],
  ['post', /^\/teacher\/homework$/, teacherHomeworkStore],
  ['get', /^\/teacher\/homework\/(\d+)\/submissions$/, teacherHomeworkSubmissions],
  ['post', /^\/teacher\/homework\/(\d+)\/publish$/, teacherHomeworkPublish],
  ['post', /^\/teacher\/homework\/(\d+)\/unpublish$/, teacherHomeworkUnpublish],
  ['get', /^\/teacher\/homework\/(\d+)$/, teacherHomeworkShow],
  ['put', /^\/teacher\/homework\/(\d+)$/, teacherHomeworkUpdate],
  ['delete', /^\/teacher\/homework\/(\d+)$/, teacherHomeworkDestroy],
  ['get', /^\/teacher\/homework-submissions\/(\d+)$/, teacherSubmissionShow],
  ['post', /^\/teacher\/homework-submissions\/(\d+)\/grade$/, teacherSubmissionGrade],
  ['get', /^\/student\/homework$/, studentHomeworkIndex],
  ['post', /^\/student\/homework\/(\d+)\/submit$/, studentHomeworkSubmit],
  ['get', /^\/attachments\/(\d+)\/download$/, attachmentDownload],

  // Course materials.
  ['get', /^\/teacher\/materials$/, teacherLessonIndex],
  ['post', /^\/teacher\/materials$/, teacherLessonStore],
  ['get', /^\/teacher\/materials\/(\d+)$/, teacherLessonShow],
  ['put', /^\/teacher\/materials\/(\d+)$/, teacherLessonUpdate],
  ['delete', /^\/teacher\/materials\/(\d+)$/, teacherLessonDestroy],
  ['post', /^\/teacher\/materials\/(\d+)\/publish$/, teacherLessonPublish],
  ['post', /^\/teacher\/materials\/(\d+)\/unpublish$/, teacherLessonUnpublish],
  ['get', /^\/student\/materials$/, studentLessonIndex],
  ['get', /^\/student\/materials\/(\d+)$/, studentLessonShow],

  // Quizzes.
  ['get', /^\/teacher\/quizzes$/, teacherQuizIndex],
  ['post', /^\/teacher\/quizzes$/, teacherQuizStore],
  ['get', /^\/teacher\/quizzes\/(\d+)\/results$/, teacherQuizResults],
  ['post', /^\/teacher\/quizzes\/(\d+)\/publish$/, teacherQuizPublish],
  ['post', /^\/teacher\/quizzes\/(\d+)\/unpublish$/, teacherQuizUnpublish],
  ['get', /^\/teacher\/quizzes\/(\d+)$/, teacherQuizShow],
  ['put', /^\/teacher\/quizzes\/(\d+)$/, teacherQuizUpdate],
  ['delete', /^\/teacher\/quizzes\/(\d+)$/, teacherQuizDestroy],
  ['get', /^\/teacher\/quiz-attempts\/(\d+)$/, teacherQuizAttemptShow],
  ['post', /^\/teacher\/quiz-attempts\/(\d+)\/review$/, teacherQuizAttemptReview],
  ['get', /^\/student\/quizzes$/, studentQuizIndex],
  ['get', /^\/student\/quizzes\/(\d+)\/paper$/, studentQuizPaper],
  ['post', /^\/student\/quizzes\/(\d+)\/submit$/, studentQuizSubmit],
  ['get', /^\/student\/quiz-attempts\/(\d+)\/review$/, studentQuizReview],
  ['get', /^\/super-admin\/audit-logs$/, superAdminAuditLogs],

  // Phase 5.5 — messaging, events, calendar
  ['get', /^\/messages$/, messageIndex],
  ['post', /^\/messages$/, messageStore],
  ['get', /^\/messages\/recipients$/, messageRecipients],
  ['get', /^\/messages\/unread$/, messageUnread],
  ['post', /^\/messages\/(\d+)\/read$/, messageRead],
  ['get', /^\/messages\/(\d+)$/, messageShow],
  ['post', /^\/messages\/(\d+)$/, messageSend],
  ['get', /^\/events$/, eventIndex],
  ['get', /^\/events\/(\d+)$/, eventShow],
  ['get', /^\/calendar$/, calendarIndex],
  ['get', /^\/calendar\/today$/, calendarToday],

  // Phase 5.6 — analytics and the pastoral register
  ['get', /^\/admin\/analytics$/, adminAnalyticsOverview],
  ['get', /^\/admin\/analytics\/at-risk$/, adminAnalyticsRegister],
  ['get', /^\/admin\/analytics\/students\/(\d+)$/, adminAnalyticsStudent],
  ['get', /^\/teacher\/analytics$/, teacherAnalyticsOverview],
  ['get', /^\/teacher\/students\/(\d+)\/report-card-comment$/, teacherShowComment],
  ['post', /^\/teacher\/students\/(\d+)\/report-card-comment\/draft$/, teacherDraftComment],
  ['put', /^\/teacher\/students\/(\d+)\/report-card-comment$/, teacherSaveComment],
  ['get', /^\/teacher\/analytics\/at-risk$/, teacherAnalyticsRegister],
  ['get', /^\/teacher\/analytics\/students\/(\d+)$/, teacherAnalyticsStudent],
  ['get', /^\/student\/insights$/, studentInsightsRoute],
  ['get', /^\/admin\/events$/, adminEventIndex],
  ['post', /^\/admin\/events$/, adminEventStore],
  ['get', /^\/admin\/events\/(\d+)$/, adminEventShow],
  ['put', /^\/admin\/events\/(\d+)$/, adminEventUpdate],
  ['post', /^\/admin\/events\/(\d+)\/publish$/, adminEventPublish],
  ['post', /^\/admin\/events\/(\d+)\/unpublish$/, adminEventUnpublish],
  ['delete', /^\/admin\/events\/(\d+)$/, adminEventDestroy],
  ['get', /^\/super-admin\/payments\/(\d+)\/receipt$/, superAdminPaymentReceiptPdf],
]

export function installMockAdapter(client) {
  client.defaults.adapter = async (config) => {
    await delay()

    const base = client.defaults.baseURL ?? ''
    const rawUrl = String(config.url ?? '').replace(base, '')
    const [path, queryString] = rawUrl.split('?')
    // Axios only serialises `config.params` inside its own adapters, so merge
    // both sources here: an explicit params object wins over the query string.
    const query = Object.fromEntries(new URLSearchParams(queryString ?? ''))
    config.params = { ...query, ...(config.params ?? {}) }

    let method = String(config.method ?? 'get').toLowerCase()

    // Laravel honours a `_method` field on POST (used for multipart PUT/DELETE,
    // which browsers cannot send as files otherwise). Mirror it so the mock
    // routes match the real API.
    if (method === 'post') {
      const data = config.data
      const override =
        data instanceof FormData
          ? data.get('_method')
          : (readBody(config)?._method ?? null)
      if (override) method = String(override).toLowerCase()
    }

    for (const [routeMethod, pattern, handler] of ROUTES) {
      if (routeMethod !== method) continue
      const match = path.match(pattern)
      if (match) return handler(config, match)
    }

    throw fail(404, `Mock route not found: ${method.toUpperCase()} ${path}`)
  }
}
