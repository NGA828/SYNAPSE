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
  serializeExam,
  serializeGrade,
  serializeTimetableEntry,
  setSetting,
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

const serializeStudentRequest = (request) => ({
  id: request.id,
  reference: request.reference,
  type: request.type,
  reason: request.reason,
  status: request.status,
  admin_note: request.admin_note,
  created_at: request.created_at,
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
  return ok(config, { token, user: publicUser(user) })
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
  const { school } = requireActiveTenant(config)
  const classId = Number(config.params?.class_id)
  if (!classId) throw fail(422, 'The given data was invalid.', { class_id: ['The class id field is required.'] })
  const date = config.params?.date ?? new Date().toISOString().slice(0, 10)
  return ok(config, rosterFor(classId, school.id, date))
}

function adminAttendancePost(config) {
  const { school } = requireActiveTenant(config)
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
  const { school } = requireActiveTenant(config)
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
  const { school } = requireActiveTenant(config)
  return ok(config, { data: db.academicYears.filter((year) => year.school_id === school.id).sort((a, b) => b.name.localeCompare(a.name)) })
}

function adminCreateAcademicYear(config) {
  const { school } = requireActiveTenant(config)
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
  const { school } = requireActiveTenant(config)
  const id = Number(match[1])
  db.academicYears.forEach((item) => { if (item.school_id === school.id) item.is_current = item.id === id })
  return ok(config, { data: db.academicYears.find((item) => item.id === id) })
}

function adminUpdateAcademicYear(config, match) {
  const { school } = requireActiveTenant(config)
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
  const { school } = requireActiveTenant(config)
  const id = Number(match[1])
  const year = db.academicYears.find((item) => item.id === id && item.school_id === school.id)
  if (!year) throw fail(404, 'Not found.')
  if (year.is_current) throw fail(422, 'The current academic year cannot be deleted. Set another year as current first.')
  db.academicYears = db.academicYears.filter((item) => item.id !== id || item.school_id !== school.id)
  return ok(config, { message: 'Academic year removed.' })
}

function adminListClasses(config) {
  const { school } = requireActiveTenant(config)
  return ok(config, { data: db.classes.filter((klass) => klass.school_id === school.id).sort((a, b) => a.name.localeCompare(b.name)) })
}

function adminCreateClass(config) {
  const { school } = requireActiveTenant(config)
  const body = readBody(config)
  const errors = validate(body, ['name'])
  if (Object.keys(errors).length) throw fail(422, 'The given data was invalid.', errors)
  assertCanCreate(school, 'classes')
  const item = { id: nextMockId(), school_id: school.id, name: body.name }
  db.classes.push(item)
  return ok(config, { data: item }, 201)
}

function adminListSubjects(config) {
  const { school } = requireActiveTenant(config)
  return ok(config, { data: db.subjects.filter((subject) => subject.school_id === school.id).sort((a, b) => a.name.localeCompare(b.name)) })
}

function adminCreateSubject(config) {
  const { school } = requireActiveTenant(config)
  const body = readBody(config)
  const errors = validate(body, ['name'])
  if (Object.keys(errors).length) throw fail(422, 'The given data was invalid.', errors)
  const item = { id: nextMockId(), school_id: school.id, name: body.name, code: body.code ?? null }
  db.subjects.push(item)
  return ok(config, { data: item }, 201)
}

function adminUpdateSubject(config, match) {
  const { school } = requireActiveTenant(config)
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
  const { school } = requireActiveTenant(config)
  const id = Number(match[1])
  db.subjects = db.subjects.filter((item) => !(item.id === id && item.school_id === school.id))
  db.teachingAssignments = db.teachingAssignments.filter((item) => !(item.subject_id === id && item.school_id === school.id))
  db.timetableEntries = db.timetableEntries.filter((item) => !(item.subject_id === id && item.school_id === school.id))
  return ok(config, { message: 'Subject removed.' })
}

function adminListTeachers(config) {
  const { school } = requireActiveTenant(config)
  return ok(config, { data: db.teachers.filter((teacher) => teacher.school_id === school.id).map(serializeTeacher) })
}

function adminCreateTeacher(config) {
  const { school } = requireActiveTenant(config)
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
  const { school } = requireActiveTenant(config)
  return ok(config, { data: db.students.filter((student) => student.school_id === school.id).map(serializeStudent) })
}

function adminCreateStudent(config) {
  const { school } = requireActiveTenant(config)
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
  const { school } = requireActiveTenant(config)
  return ok(config, { data: db.teachingAssignments.filter((assignment) => assignment.school_id === school.id).map(serializeAssignment).sort((a, b) => b.id - a.id) })
}

function adminCreateAssignment(config) {
  const { school } = requireActiveTenant(config)
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
  const { school } = requireActiveTenant(config)
  const id = Number(match[1])
  db.teachingAssignments = db.teachingAssignments.filter((assignment) => !(assignment.id === id && assignment.school_id === school.id))
  return ok(config, { message: 'Assignment removed.' })
}

function adminTimetable(config) {
  const { school } = requireActiveTenant(config)
  const classId = Number(config.params?.class_id)
  if (!classId) throw fail(422, 'The given data was invalid.', { class_id: ['The class id field is required.'] })
  const klass = db.classes.find((item) => item.id === classId && item.school_id === school.id)
  if (!klass) throw fail(404, 'Not found.')
  return ok(config, { class: klass, academic_year: currentYearFor(school.id), entries: timetableFor(classId, school.id) })
}

function adminCreateTimetableEntry(config) {
  const { school } = requireActiveTenant(config)
  const body = readBody(config)
  const errors = validate(body, ['class_id', 'subject_id', 'day', 'start', 'end'])
  if (Object.keys(errors).length) throw fail(422, 'The given data was invalid.', errors)
  const entry = { id: nextMockId(), school_id: school.id, class_id: Number(body.class_id), academic_year_id: currentYearFor(school.id)?.id, subject_id: Number(body.subject_id), day: Number(body.day), start: body.start, end: body.end }
  db.timetableEntries.push(entry)
  return ok(config, { data: serializeTimetableEntry(entry) }, 201)
}

function adminDeleteTimetableEntry(config, match) {
  const { school } = requireActiveTenant(config)
  const id = Number(match[1])
  db.timetableEntries = db.timetableEntries.filter((entry) => !(entry.id === id && entry.school_id === school.id))
  return ok(config, { message: 'Timetable entry removed.' })
}

function adminListRequests(config) {
  const { school } = requireActiveTenant(config)
  return ok(config, { data: db.requests.filter((request) => request.school_id === school.id).map(serializeAdminRequest).sort((a, b) => b.id - a.id) })
}

function notifyStudentAboutRequest(schoolId, studentId, request, status) {
  const studentUser = db.users.find((user) => user.id === db.students.find((student) => student.id === Number(studentId))?.user_id)
  if (!studentUser) return
  const messages = { under_review: `Your request ${request.reference} is now under review.`, approved: `Your request ${request.reference} has been approved.`, ready: `Your request ${request.reference} is ready to download.`, rejected: `Your request ${request.reference} was declined.` }
  db.notifications.push({ id: nextMockId(), school_id: schoolId, user_id: studentUser.id, type: 'request_updated', title: 'Request update', message: messages[status] ?? `Your request ${request.reference} was updated.`, data: { request_id: request.id, status }, read_at: null })
}

function adminUpdateRequest(config, match) {
  const { school } = requireActiveTenant(config)
  const body = readBody(config)
  const request = db.requests.find((item) => item.id === Number(match[1]) && item.school_id === school.id)
  if (!request) throw fail(404, 'Not found.')
  request.status = body.status
  request.admin_note = body.admin_note ?? request.admin_note
  notifyStudentAboutRequest(school.id, request.student_id, request, body.status)
  return ok(config, { data: serializeAdminRequest(request) })
}

function adminGenerateDocument(config, match) {
  const { school } = requireActiveTenant(config)
  const request = db.requests.find((item) => item.id === Number(match[1]) && item.school_id === school.id)
  if (!request) throw fail(404, 'Not found.')
  if (!db.documents.find((document) => document.request_id === request.id)) {
    db.documents.push({ id: nextMockId(), school_id: school.id, request_id: request.id, student_id: request.student_id, title: request.type, file_name: `${request.type.toLowerCase().replace(/\s+/g, '-')}-${request.reference.toLowerCase()}.pdf`, mime_type: 'application/pdf', size: 1840, created_at: new Date().toISOString() })
  }
  request.status = 'ready'
  notifyStudentAboutRequest(school.id, request.student_id, request, 'ready')
  return ok(config, { data: serializeAdminRequest(request) })
}

function adminCreateAnnouncement(config) {
  const { user, school } = requireActiveTenant(config)
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
  const { school } = requireActiveTenant(config)
  const year = currentYearFor(school.id)
  return ok(config, { data: year ? semestersFor(school.id, year.id) : [], academic_year: year })
}

function adminCreateSemester(config) {
  const { school } = requireActiveTenant(config)
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
  const { school } = requireActiveTenant(config)
  const id = Number(match[1])
  const semester = byId(db.semesters, id)
  if (!semester || semester.school_id !== school.id) throw fail(404, 'Not found.')
  db.semesters.forEach((item) => {
    if (item.school_id === school.id && item.academic_year_id === semester.academic_year_id) item.is_current = item.id === id
  })
  return ok(config, { data: byId(db.semesters, id) })
}

function adminDeleteSemester(config, match) {
  const { school } = requireActiveTenant(config)
  const id = Number(match[1])
  db.semesters = db.semesters.filter((item) => !(item.id === id && item.school_id === school.id))
  return ok(config, { message: 'Semester removed.' })
}

function adminListGradeComponents(config) {
  const { school } = requireActiveTenant(config)
  const defaults = db.gradeComponents.filter((component) => component.school_id === school.id && component.subject_id == null)
  const bySubject = {}
  for (const component of db.gradeComponents.filter((component) => component.school_id === school.id && component.subject_id != null)) {
    ;(bySubject[component.subject_id] ??= []).push(component)
  }
  return ok(config, { default: defaults, by_subject: bySubject })
}

function adminCreateGradeComponent(config) {
  const { school } = requireActiveTenant(config)
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
  const { school } = requireActiveTenant(config)
  const component = db.gradeComponents.find((item) => item.id === Number(match[1]) && item.school_id === school.id)
  if (!component) throw fail(404, 'Not found.')
  const body = readBody(config)
  component.name = body.name ?? component.name
  component.weight = body.weight !== undefined ? Number(body.weight) : component.weight
  component.subject_id = body.subject_id !== undefined ? body.subject_id : component.subject_id
  return ok(config, { data: component })
}

function adminDeleteGradeComponent(config, match) {
  const { school } = requireActiveTenant(config)
  const id = Number(match[1])
  db.gradeComponents = db.gradeComponents.filter((item) => !(item.id === id && item.school_id === school.id))
  return ok(config, { message: 'Component removed.' })
}

function adminListExams(config) {
  const { school } = requireActiveTenant(config)
  const classId = config.params?.class_id
  const exams = db.exams
    .filter((exam) => exam.school_id === school.id && (!classId || exam.class_id === Number(classId)))
    .sort((a, b) => a.date.localeCompare(b.date) || a.start.localeCompare(b.start))
    .map(serializeExam)
  return ok(config, { data: exams })
}

function adminCreateExam(config) {
  const { school } = requireActiveTenant(config)
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
  const { school } = requireActiveTenant(config)
  const id = Number(match[1])
  db.exams = db.exams.filter((item) => !(item.id === id && item.school_id === school.id))
  return ok(config, { message: 'Exam session removed.' })
}

function adminExamRanking(config) {
  const { school } = requireActiveTenant(config)
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

function adminImport(config) {
  const { school } = requireActiveTenant(config)
  const body = readBody(config)
  const type = body.type
  const rows = body.rows ?? []
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
  ['delete', /^\/admin\/timetable\/entries\/(\d+)$/, adminDeleteTimetableEntry],
  ['get', /^\/admin\/requests$/, adminListRequests],
  ['post', /^\/admin\/requests\/(\d+)\/status$/, adminUpdateRequest],
  ['post', /^\/admin\/requests\/(\d+)\/generate-document$/, adminGenerateDocument],
  ['post', /^\/admin\/announcements$/, adminCreateAnnouncement],
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
]

export function installMockAdapter(client) {
  client.defaults.adapter = async (config) => {
    await delay()

    const base = client.defaults.baseURL ?? ''
    const rawUrl = String(config.url ?? '').replace(base, '')
    const [path, queryString] = rawUrl.split('?')
    const params = Object.fromEntries(new URLSearchParams(queryString ?? ''))
    config.params = params

    const method = String(config.method ?? 'get').toLowerCase()

    for (const [routeMethod, pattern, handler] of ROUTES) {
      if (routeMethod !== method) continue
      const match = path.match(pattern)
      if (match) return handler(config, match)
    }

    throw fail(404, `Mock route not found: ${method.toUpperCase()} ${path}`)
  }
}
