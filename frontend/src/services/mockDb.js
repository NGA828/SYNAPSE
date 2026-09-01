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
  auditLogs: [
    { id: 1, school_id: 2, user_id: 2, action: 'student.created', entity_type: 'App\\Models\\Student', entity_id: 3, metadata: { matricule: 'AICS-2026-003' }, created_at: '2026-08-17T08:12:00' },
    { id: 2, school_id: 2, user_id: 2, action: 'request.updated', entity_type: 'App\\Models\\DocumentRequest', entity_id: 4, metadata: { status: 'approved' }, created_at: '2026-08-17T11:04:00' },
    { id: 3, school_id: 2, user_id: 3, action: 'grade.updated', entity_type: 'App\\Models\\Grade', entity_id: 12, metadata: { average: 15.5 }, created_at: '2026-08-18T09:41:00' },
    { id: 4, school_id: 2, user_id: 2, action: 'document.created', entity_type: 'App\\Models\\Document', entity_id: 1, metadata: { verification_code: 'SYN-4KQ2-9WPT' }, created_at: '2026-08-19T15:20:00' },
    { id: 5, school_id: 2, user_id: 6, action: 'password.changed', entity_type: 'App\\Models\\User', entity_id: 6, metadata: {}, created_at: '2026-08-20T07:05:00' },
  ],

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
    // Level 2A: Mr. David also teaches English here, so a teacher's calendar
    // must show both classes rather than only the first they were assigned.
    { id: 10, school_id: 2, class_id: 3, academic_year_id: 2, subject_id: 1, day: 1, start: '12:00', end: '13:00' },
    { id: 11, school_id: 2, class_id: 3, academic_year_id: 2, subject_id: 1, day: 3, start: '12:00', end: '13:00' },
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
  reportCardComments: [],
  documents: [
    { id: 1, school_id: 2, request_id: 2, student_id: 1, title: 'Transcript Request', type: 'transcript', verification_code: 'SYN-4KQ2-9WPT', file_name: 'transcript-req-1030.pdf', mime_type: 'application/pdf', size: 1840, created_at: '2026-08-10T09:00:00' },
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
  homeworkAssignments: [],
  homeworkSubmissions: [],
  attachments: [],
  lessons: [],
  quizzes: [],
  quizQuestions: [],
  quizAttempts: [],
  conversations: [],
  messages: [],
  events: [],
}

/**
 * In-memory bytes for uploaded attachments, keyed by attachment id.
 *
 * The mock has no filesystem, so uploads are read into memory and handed back
 * as real Blobs on download — the demo genuinely round-trips a file.
 */
export const fileStore = new Map()

export const storeFile = (id, blob) => fileStore.set(id, blob)

export const readFile = (id) => fileStore.get(id) ?? null


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

// Seed demo homework for AICS Level 3A. Mirrors the rows the Laravel seeder
// creates so both modes show the same thing.
{
  const iso = (offset, time = '23:59') => {
    const date = new Date()
    date.setDate(date.getDate() + offset)
    return `${date.toISOString().slice(0, 10)}T${time}:00`
  }

  db.homeworkAssignments = [
    {
      id: 1, school_id: 2, teacher_id: 1, subject_id: 1, class_id: 5, academic_year_id: 2, semester_id: 1,
      title: 'Essay: The Role of Technology in Education', instructions: 'Write a 500-word argumentative essay. Use at least three examples and end with your own position.',
      max_score: 20, due_at: iso(-2), is_published: true, published_at: iso(-9, '08:00'), created_at: iso(-9, '08:00'),
    },
    {
      id: 2, school_id: 2, teacher_id: 2, subject_id: 2, class_id: 5, academic_year_id: 2, semester_id: 1,
      title: 'Quadratic Equations — Exercise 4B', instructions: 'Solve questions 1 to 10 of Exercise 4B. Show every step of your working, not only the final answer.',
      max_score: 20, due_at: iso(3), is_published: true, published_at: iso(-3, '09:00'), created_at: iso(-3, '09:00'),
    },
    {
      id: 3, school_id: 2, teacher_id: 1, subject_id: 7, class_id: 5, academic_year_id: 2, semester_id: 1,
      title: 'Research: Causes of the First World War', instructions: 'Summarise the four long-term causes in your own words, one paragraph each.',
      max_score: 20, due_at: iso(7), is_published: true, published_at: iso(-1, '10:00'), created_at: iso(-1, '10:00'),
    },
    {
      id: 4, school_id: 2, teacher_id: 1, subject_id: 1, class_id: 5, academic_year_id: 2, semester_id: 1,
      title: 'Comprehension: Draft Questions', instructions: null,
      max_score: 20, due_at: iso(14), is_published: false, published_at: null, created_at: iso(0, '11:00'),
    },
  ]

  db.homeworkSubmissions = [
    {
      id: 1, school_id: 2, homework_assignment_id: 1, student_id: 1,
      content: 'Technology has reshaped how knowledge reaches the classroom. First, digital libraries remove the cost barrier to reference material...',
      attempts: 1, submitted_at: iso(-3, '19:20'), is_late: false,
      score: 16.5, feedback: 'Well structured with a clear position. Develop your second example further.',
      graded_by: 1, graded_at: iso(-1, '08:30'), created_at: iso(-3, '19:20'),
    },
    {
      id: 2, school_id: 2, homework_assignment_id: 1, student_id: 2,
      content: 'In my view technology helps students learn faster because information is available everywhere...',
      attempts: 1, submitted_at: iso(-1, '22:05'), is_late: false,
      score: null, feedback: null, graded_by: null, graded_at: null, created_at: iso(-1, '22:05'),
    },
    {
      id: 3, school_id: 2, homework_assignment_id: 2, student_id: 1,
      content: 'Question 1: x² - 5x + 6 = 0, so (x - 2)(x - 3) = 0, giving x = 2 or x = 3.',
      attempts: 2, submitted_at: iso(0, '07:45'), is_late: false,
      score: null, feedback: null, graded_by: null, graded_at: null, created_at: iso(-1, '20:00'),
    },
  ]
}

// Seed demo course materials for AICS Level 3A, grouped by subject and topic.
{
  const iso = (offset, time = '08:00') => {
    const date = new Date()
    date.setDate(date.getDate() + offset)
    return `${date.toISOString().slice(0, 10)}T${time}:00`
  }

  const paragraph = (text, times) => Array.from({ length: times }, (_, i) => `${text} (${i + 1})`).join(' ')

  db.lessons = [
    {
      id: 1, school_id: 2, teacher_id: 1, subject_id: 1, class_id: 5, academic_year_id: 2, semester_id: 1,
      title: 'Argumentative Writing: Building a Thesis',
      topic: 'Essay Writing',
      summary: 'How to turn an opinion into a defensible thesis, and how to support it with evidence.',
      body: paragraph('A thesis is a claim a reader could reasonably disagree with, plus the reason it holds. Start from your conclusion and work backwards to the strongest evidence you have. Then test it: if nobody could disagree, it is a topic, not a thesis. Revise until the sentence carries both the claim and the reason. Practise by rewriting three weak statements from the worksheet into full theses, then exchange them with a partner and try to argue the opposite position.', 2),
      minutes: 15, sequence: 1, is_published: true, published_at: iso(-6), created_at: iso(-7),
    },
    {
      id: 2, school_id: 2, teacher_id: 1, subject_id: 1, class_id: 5, academic_year_id: 2, semester_id: 1,
      title: 'Citing Sources Without Plagiarising',
      topic: 'Essay Writing',
      summary: 'Quotation, paraphrase and summary — and when to use each.',
      body: paragraph('Quoting preserves exact wording, paraphrase restates an idea in your own words, and summary compresses several paragraphs into one. Plagiarism is not only copying: presenting a paraphrase without attribution counts too. Cite the author and year at the point of use, and keep a working reference list as you write rather than reconstructing it at the end.', 1),
      minutes: null, sequence: 2, is_published: true, published_at: iso(-2), created_at: iso(-3),
    },
    {
      id: 3, school_id: 2, teacher_id: 1, subject_id: 1, class_id: 5, academic_year_id: 2, semester_id: 1,
      title: 'Reading Comprehension Strategies',
      topic: 'Close Reading',
      summary: 'Skimming, scanning and annotation — three passes over the same text.',
      body: paragraph('First pass for gist, second for structure, third for detail. Annotate the margins rather than highlighting everything: a highlight tells you nothing when you return to the page a week later. Mark claims, evidence and the words that signal a shift in the argument.', 1),
      minutes: 10, sequence: 3, is_published: true, published_at: iso(-9), created_at: iso(-9),
    },
    {
      id: 4, school_id: 2, teacher_id: 1, subject_id: 7, class_id: 5, academic_year_id: 2, semester_id: 1,
      title: 'Long-term Causes of the First World War',
      topic: 'The World Wars',
      summary: 'Militarism, alliances, imperialism and nationalism — the MAIN framework.',
      body: paragraph('Militarism meant arms races and war plans that assumed mobilisation. Alliances turned a regional dispute into a continental one. Imperialism created rivalries over territory and markets. Nationalism supplied both the will to fight and the grievances that made it feel justified. Use MAIN as a structure, but argue which factor mattered most rather than listing all four equally.', 1),
      minutes: null, sequence: 1, is_published: true, published_at: iso(-1), created_at: iso(-1),
    },
    {
      id: 5, school_id: 2, teacher_id: 2, subject_id: 2, class_id: 5, academic_year_id: 2, semester_id: 1,
      title: 'Solving Quadratic Equations by Factorisation',
      topic: 'Algebra',
      summary: 'Factorising ax² + bx + c, and recognising when the method will not work.',
      body: paragraph('Factorisation works when the quadratic has rational roots. Move every term to one side so the equation equals zero, then find two numbers that multiply to ac and add to b. Split the middle term, factor by grouping, and set each bracket to zero. If no such pair exists, use the completing-the-square or formula method instead.', 1),
      minutes: 20, sequence: 1, is_published: true, published_at: iso(-4), created_at: iso(-4),
    },
    {
      id: 6, school_id: 2, teacher_id: 1, subject_id: 1, class_id: 3, academic_year_id: 2, semester_id: 1,
      title: 'Persuasive Speech Structure',
      topic: 'Speech Writing',
      summary: 'Hook, argument, counter-argument and close — for Level 2A.',
      body: paragraph('A speech has one job: to be followed out loud. Signpost every turn, repeat the central claim three times in different words, and leave the strongest point for last. Time yourself reading it aloud, because a paragraph that looks short on the page can take a minute to say.', 1),
      minutes: 12, sequence: 1, is_published: true, published_at: iso(-5), created_at: iso(-5),
    },
    {
      id: 7, school_id: 2, teacher_id: 1, subject_id: 1, class_id: 5, academic_year_id: 2, semester_id: 1,
      title: 'Draft: Poetry Analysis Framework',
      topic: 'Poetry',
      summary: null, body: null,
      minutes: null, sequence: 4, is_published: false, published_at: null, created_at: iso(0, '11:00'),
    },
  ]
}

// Seed demo quizzes for AICS. Question options are stored in canonical order
// and `correct_option` is the index of the right one — never sent to a student.
{
  const iso = (offset, time = '08:00') => {
    const date = new Date()
    date.setDate(date.getDate() + offset)
    return `${date.toISOString().slice(0, 10)}T${time}:00`
  }

  db.quizzes = [
    {
      id: 1, school_id: 2, teacher_id: 1, subject_id: 1, class_id: 5, academic_year_id: 2, semester_id: 1,
      title: 'Grammar Check: Tenses and Agreement',
      instructions: 'Choose the one correct option for each sentence. There is no negative marking.',
      max_score: 20, closes_at: iso(5, '23:59'), time_limit_minutes: 20, attempts_allowed: 1,
      is_published: true, is_locked: false, published_at: iso(-2), created_at: iso(-3),
    },
    {
      id: 2, school_id: 2, teacher_id: 2, subject_id: 2, class_id: 5, academic_year_id: 2, semester_id: 1,
      title: 'Algebra Drill: Linear Equations',
      instructions: 'Work without a calculator. Show your reasoning where the option allows it.',
      max_score: 20, closes_at: iso(9, '23:59'), time_limit_minutes: 30, attempts_allowed: 2,
      is_published: true, is_locked: false, published_at: iso(-1), created_at: iso(-1),
    },
    {
      id: 3, school_id: 2, teacher_id: 1, subject_id: 7, class_id: 5, academic_year_id: 2, semester_id: 1,
      title: 'History Quiz: The World Wars',
      instructions: null,
      max_score: 20, closes_at: iso(-1, '23:59'), time_limit_minutes: null, attempts_allowed: 1,
      is_published: true, is_locked: true, published_at: iso(-14), created_at: iso(-15),
    },
    {
      id: 4, school_id: 2, teacher_id: 1, subject_id: 1, class_id: 5, academic_year_id: 2, semester_id: 1,
      title: 'Draft: Comprehension Skills',
      instructions: null,
      max_score: 20, closes_at: null, time_limit_minutes: 15, attempts_allowed: 1,
      is_published: false, is_locked: false, published_at: null, created_at: iso(0, '09:30'),
    },
  ]

  db.quizQuestions = [
    { id: 1, quiz_id: 1, school_id: 2, sequence: 1, points: 4, prompt: 'Choose the sentence with correct subject-verb agreement.',
      options: ['The list of items are on the desk.', 'The list of items is on the desk.', 'The list of items were on the desk.', 'The list of items have been on the desk.'],
      correct_option: 1 },
    { id: 2, quiz_id: 1, school_id: 2, sequence: 2, points: 4, prompt: 'Which sentence uses the past perfect correctly?',
      options: ['She had left before we arrived.', 'She has left before we arrived.', 'She have left before we arrived.', 'She leaving before we arrived.'],
      correct_option: 0 },
    { id: 3, quiz_id: 1, school_id: 2, sequence: 3, points: 4, prompt: 'Select the correct passive form: "The teacher marked the papers."',
      options: ['The papers was marked by the teacher.', 'The papers were marked by the teacher.', 'The papers are marked by the teacher.', 'The papers marked by the teacher.'],
      correct_option: 1 },
    { id: 4, quiz_id: 1, school_id: 2, sequence: 4, points: 4, prompt: 'Which word is a subordinating conjunction?',
      options: ['and', 'but', 'although', 'or'],
      correct_option: 2 },
    { id: 5, quiz_id: 1, school_id: 2, sequence: 5, points: 4, prompt: 'Identify the sentence with no comma splice.',
      options: ['It was late, we went home.', 'It was late; we went home.', 'It was late, we went home, we were tired.', 'It was late we went home.'],
      correct_option: 1 },

    { id: 6, quiz_id: 2, school_id: 2, sequence: 1, points: 5, prompt: 'Solve for x: 3x + 7 = 22',
      options: ['x = 3', 'x = 5', 'x = 7', 'x = 15'],
      correct_option: 1 },
    { id: 7, quiz_id: 2, school_id: 2, sequence: 2, points: 5, prompt: 'Solve for y: 2(y - 4) = 10',
      options: ['y = 3', 'y = 7', 'y = 9', 'y = 12'],
      correct_option: 2 },
    { id: 8, quiz_id: 2, school_id: 2, sequence: 3, points: 5, prompt: 'Which line is parallel to y = 2x + 1?',
      options: ['y = -2x + 1', 'y = 2x - 5', 'y = x/2 + 1', 'y = 2x² + 1'],
      correct_option: 1 },
    { id: 9, quiz_id: 2, school_id: 2, sequence: 4, points: 5, prompt: 'If 5a - 3 = 2a + 9, then a =',
      options: ['a = 2', 'a = 3', 'a = 4', 'a = 6'],
      correct_option: 2 },

    { id: 10, quiz_id: 3, school_id: 2, sequence: 1, points: 5, prompt: 'In which year did the First World War begin?',
      options: ['1912', '1914', '1916', '1918'],
      correct_option: 1 },
    { id: 11, quiz_id: 3, school_id: 2, sequence: 2, points: 5, prompt: 'The "MAIN" framework of long-term causes stands for:',
      options: ['Money, Arms, Industry, Nations', 'Militarism, Alliances, Imperialism, Nationalism', 'Monarchy, Army, Invasion, Negotiation', 'Markets, Agreements, Interests, Neutrality'],
      correct_option: 1 },
    { id: 12, quiz_id: 3, school_id: 2, sequence: 3, points: 5, prompt: 'Which event is generally taken as the immediate trigger of the First World War?',
      options: ['The sinking of the Lusitania', 'The assassination at Sarajevo', 'The Treaty of Versailles', 'The invasion of Poland'],
      correct_option: 1 },
    { id: 13, quiz_id: 3, school_id: 2, sequence: 4, points: 5, prompt: 'The Second World War ended in:',
      options: ['1943', '1944', '1945', '1946'],
      correct_option: 2 },
  ]

  // One completed attempt from John (student 1) on the closed History quiz, so
  // the results view and the review screen both have something to show.
  db.quizAttempts = [
    {
      id: 1, quiz_id: 3, student_id: 1, school_id: 2,
      answers: { 10: 1, 11: 1, 12: 0, 13: 2 },
      correct_count: 3, total_questions: 4, score: 15, attempt: 1,
      started_at: iso(-10, '09:00'), submitted_at: iso(-10, '09:18'),
      feedback: 'Strong on dates. Revisit the Sarajevo trigger before the exam.',
      is_reviewed: true, reviewed_at: iso(-9, '14:00'), reviewed_by: 1,
    },
  ]
}

// Seed school events for AICS. Dates are relative to today so the calendar
// always has something upcoming regardless of when the demo is opened.
{
  const at = (dayOffset, time) => {
    const date = new Date()
    date.setDate(date.getDate() + dayOffset)
    return `${date.toISOString().slice(0, 10)}T${time}:00`
  }
  db.events = [
    { id: 1, school_id: 2, user_id: 2, title: 'First Semester Examinations Begin', description: 'Examinations run for two weeks. Arrive 30 minutes early.', type: 'exam', starts_at: at(7, '08:00'), ends_at: at(18, '14:00'), all_day: false, location: 'Hall A', audience: 'all', is_published: true, published_at: at(-3, '09:00') },
    { id: 2, school_id: 2, user_id: 2, title: 'Monday Assembly', description: 'Whole school assembly on the main field.', type: 'assembly', starts_at: at(1, '07:30'), ends_at: at(1, '08:15'), all_day: false, location: 'Main Field', audience: 'students', is_published: true, published_at: at(-1, '09:00') },
    { id: 3, school_id: 2, user_id: 2, title: 'Staff Meeting', description: 'Assessment moderation and the new marking policy.', type: 'meeting', starts_at: at(3, '15:00'), ends_at: at(3, '16:30'), all_day: false, location: 'Staff Room', audience: 'teachers', is_published: true, published_at: at(-1, '09:00') },
    { id: 4, school_id: 2, user_id: 2, title: 'Inter-house Sports Day', description: null, type: 'sports', starts_at: at(25, '08:00'), ends_at: at(25, '16:00'), all_day: false, location: 'Sports Complex', audience: 'all', is_published: true, published_at: at(-2, '09:00') },
    { id: 5, school_id: 2, user_id: 2, title: 'Mid-term Break', description: 'School closed.', type: 'holiday', starts_at: at(30, '00:00'), ends_at: at(36, '23:59'), all_day: true, location: null, audience: 'all', is_published: true, published_at: at(-2, '09:00') },
    // A draft: admins see it, nobody else does.
    { id: 6, school_id: 2, user_id: 2, title: 'Parent-Teacher Consultation', description: null, type: 'meeting', starts_at: at(40, '09:00'), ends_at: at(40, '13:00'), all_day: false, location: 'Classrooms', audience: 'all', is_published: false, published_at: null },
    { id: 7, school_id: 3, user_id: 9, title: 'Founders Day', description: null, type: 'assembly', starts_at: at(12, '09:00'), ends_at: at(12, '12:00'), all_day: false, location: 'Auditorium', audience: 'all', is_published: true, published_at: at(-4, '09:00') },
  ]
}

// Seed one conversation: a student asking a teacher about homework. The pair is
// stored ordered (lower id first) exactly as the Laravel migration enforces.
{
  const at = (dayOffset, time) => {
    const date = new Date()
    date.setDate(date.getDate() + dayOffset)
    return `${date.toISOString().slice(0, 10)}T${time}:00`
  }
  // user 6 (John Doe, student) and user 3 (Mr. David, teacher) -> a=3, b=6
  db.conversations = [
    { id: 1, school_id: 2, participant_a_id: 3, participant_b_id: 6, last_message_at: at(-1, '14:32') },
  ]
  db.messages = [
    { id: 1, conversation_id: 1, school_id: 2, sender_id: 6, body: 'Good afternoon sir. Is the essay due this Friday or next Monday?', read_at: at(-1, '14:30'), created_at: at(-1, '14:28') },
    { id: 2, conversation_id: 1, school_id: 2, sender_id: 3, body: 'This Friday. Submit it through the homework page so it is recorded.', read_at: null, created_at: at(-1, '14:32') },
  ]
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/** Morph type strings, matching what Laravel's `getMorphClass()` returns. */
/**
 * Grading scale, mirroring `backend/config/synapse.php → grading`.
 */
export const GRADING = {
  scale: 20,
  pass_mark: 10,
  mentions: [
    { min: 18, label: 'Excellent' },
    { min: 16, label: 'Very Good' },
    { min: 14, label: 'Good' },
    { min: 12, label: 'Fairly Good' },
    { min: 10, label: 'Average' },
    { min: 0, label: 'Insufficient' },
  ],
}

/**
 * Pastoral-register thresholds, mirroring `synapse.at_risk`. Kept next to the
 * grading scale because both are school policy, and because a threshold that
 * lives in one layer only is how the mock and the API start to disagree.
 */
/**
 * Mirrors DocumentRequest::TYPES / TYPE_SLUGS / AUTO_GENERATABLE_TYPES.
 *
 * A recommendation letter needs an author who knows the student and "Other" is
 * unspecified, so neither can be produced by a template.
 */
export const DOCUMENT_TYPES = [
  { label: 'Certificate of Enrollment', slug: 'enrollment_certificate', auto_generatable: true },
  { label: 'Transcript Request', slug: 'academic_transcript', auto_generatable: true },
  { label: 'Recommendation Letter', slug: 'recommendation_letter', auto_generatable: false },
  { label: 'Certificate of Good Conduct', slug: 'good_conduct_certificate', auto_generatable: true },
  { label: 'Transfer Certificate', slug: 'transfer_certificate', auto_generatable: true },
  { label: 'School Leaving Certificate', slug: 'school_leaving_certificate', auto_generatable: true },
  { label: 'Other', slug: 'other', auto_generatable: false },
]

export const AT_RISK = {
  critical_margin: 2,
  failing_subjects: 2,
  decline_points: 2,
  missing_homework: 2,
  submission_rate: 60,
  attendance_rate: 80,
  quiz_average: 50,
}

export const HOMEWORK_TYPE = 'App\\Models\\HomeworkAssignment'
export const SUBMISSION_TYPE = 'App\\Models\\HomeworkSubmission'
export const LESSON_TYPE = 'App\\Models\\Lesson'
export const QUIZ_TYPE = 'App\\Models\\Quiz'

/** Mirrors AttachmentResource. */
export const serializeAttachment = (attachment) => ({
  id: attachment.id,
  file_name: attachment.file_name,
  mime_type: attachment.mime_type,
  size: attachment.size,
  size_label: formatBytes(attachment.size),
  visibility: attachment.visibility,
  uploaded_by_role: attachment.uploaded_by_role,
  created_at: attachment.created_at ?? null,
  download_url: `/attachments/${attachment.id}/download`,
})

export const formatBytes = (bytes) => {
  const value = Number(bytes) || 0
  if (value >= 1048576) return `${Math.round((value / 1048576) * 10) / 10} MB`
  if (value >= 1024) return `${Math.round(value / 1024)} KB`
  return `${value} B`
}

export const attachmentsFor = (type, id) =>
  db.attachments
    .filter((row) => row.attachable_type === type && row.attachable_id === Number(id))
    .map(serializeAttachment)

/** Mirrors LessonResource, including the reading-time estimate. */
export const serializeLesson = (lesson, { includeBody = false } = {}) => ({
  id: lesson.id,
  title: lesson.title,
  topic: lesson.topic ?? null,
  summary: lesson.summary ?? null,
  ...(includeBody ? { body: lesson.body ?? null } : {}),
  minutes: lesson.minutes ?? estimateMinutes(lesson.body),
  sequence: lesson.sequence ?? 0,
  is_published: Boolean(lesson.is_published),
  published_at: lesson.published_at ?? null,
  created_at: lesson.created_at ?? null,
  subject: byId(db.subjects, lesson.subject_id) ?? null,
  class: byId(db.classes, lesson.class_id) ?? null,
  semester: byId(db.semesters, lesson.semester_id) ?? null,
  attachments: attachmentsFor(LESSON_TYPE, lesson.id),
})

/** Mirrors Lesson::estimatedMinutes() — 200 wpm, minimum one minute. */
export const estimateMinutes = (body) => {
  const words = String(body ?? '').trim().split(/\s+/).filter(Boolean).length
  return words > 0 ? Math.max(1, Math.ceil(words / 200)) : null
}

const quizQuestionsFor = (quizId) =>
  db.quizQuestions
    .filter((question) => question.quiz_id === Number(quizId))
    .sort((a, b) => (a.sequence ?? 0) - (b.sequence ?? 0) || a.id - b.id)

/**
 * Mirrors QuizResource. `withKey` decides whether the answer key is included —
 * the student paper must be serialised with it false.
 */
export const serializeQuiz = (quiz, { withKey = true } = {}) => {
  const questions = quizQuestionsFor(quiz.id)
  const submitted = db.quizAttempts.filter(
    (attempt) => attempt.quiz_id === quiz.id && attempt.submitted_at,
  )

  return {
    id: quiz.id,
    title: quiz.title,
    instructions: quiz.instructions ?? null,
    max_score: quiz.max_score,
    closes_at: quiz.closes_at ?? null,
    time_limit_minutes: quiz.time_limit_minutes ?? null,
    attempts_allowed: quiz.attempts_allowed ?? 1,
    is_published: Boolean(quiz.is_published),
    is_locked: Boolean(quiz.is_locked),
    published_at: quiz.published_at ?? null,
    created_at: quiz.created_at ?? null,
    questions_count: questions.length,
    attempts_count: submitted.length,
    is_open: Boolean(quiz.is_published) && !quizClosed(quiz),
    is_closed: quizClosed(quiz),
    subject: byId(db.subjects, quiz.subject_id) ?? null,
    class: byId(db.classes, quiz.class_id) ?? null,
    semester: byId(db.semesters, quiz.semester_id) ?? null,
    questions: withKey ? questions.map(serializeQuizQuestion) : [],
    attachments: attachmentsFor(QUIZ_TYPE, quiz.id),
  }
}

export const quizClosed = (quiz) => Boolean(quiz.closes_at && new Date(quiz.closes_at).getTime() < Date.now())

/** Mirrors QuizQuestionResource — always includes the key. */
export const serializeQuizQuestion = (question) => ({
  id: question.id,
  prompt: question.prompt,
  options: question.options,
  correct_option: question.correct_option,
  correct_answer: question.options?.[question.correct_option] ?? null,
  points: question.points ?? 1,
  sequence: question.sequence ?? 0,
})

/** Mirrors QuizAttemptResource. */
export const serializeQuizAttempt = (attempt) => {
  const quiz = byId(db.quizzes, attempt.quiz_id)
  return {
    id: attempt.id,
    quiz_id: attempt.quiz_id,
    attempt: attempt.attempt ?? 1,
    score: attempt.score ?? null,
    max_score: quiz?.max_score ?? null,
    correct_count: attempt.correct_count ?? 0,
    total_questions: attempt.total_questions ?? 0,
    percentage: attempt.total_questions
      ? Math.round((attempt.correct_count / attempt.total_questions) * 1000) / 10
      : null,
    submitted_at: attempt.submitted_at ?? null,
    feedback: attempt.feedback ?? null,
    is_reviewed: Boolean(attempt.is_reviewed),
    reviewed_at: attempt.reviewed_at ?? null,
    answers: attempt.answers ?? {},
    student: (() => {
      const student = byId(db.students, attempt.student_id)
      return student ? { id: student.id, name: userName(student.user_id), matricule: student.matricule } : null
    })(),
    quiz: quiz ? { id: quiz.id, title: quiz.title, max_score: quiz.max_score, subject: byId(db.subjects, quiz.subject_id)?.name ?? null } : null,
  }
}

/** Serialises a homework row into the API shape HomeworkAssignmentResource emits. */
export const serializeHomework = (homework) => {
  const dueAt = new Date(homework.due_at)
  const isPastDue = dueAt.getTime() < Date.now()
  return {
    id: homework.id,
    title: homework.title,
    instructions: homework.instructions ?? null,
    max_score: homework.max_score,
    due_at: homework.due_at,
    is_published: Boolean(homework.is_published),
    is_past_due: isPastDue,
    is_open: Boolean(homework.is_published) && !isPastDue,
    published_at: homework.published_at ?? null,
    created_at: homework.created_at ?? null,
    subject: byId(db.subjects, homework.subject_id) ?? null,
    class: byId(db.classes, homework.class_id) ?? null,
    semester: byId(db.semesters, homework.semester_id) ?? null,
    academic_year: byId(db.academicYears, homework.academic_year_id) ?? null,
    attachments: attachmentsFor(HOMEWORK_TYPE, homework.id),
  }
}

/** Serialises a submission into the API shape HomeworkSubmissionResource emits. */
export const serializeSubmission = (submission, { includeContent = true } = {}) => {
  const student = byId(db.students, submission.student_id)
  const graded = submission.score !== null && submission.score !== undefined
  return {
    id: submission.id,
    homework_assignment_id: submission.homework_assignment_id,
    student_id: submission.student_id,
    student: student ? { id: student.id, name: userName(student.user_id), matricule: student.matricule } : null,
    ...(includeContent ? { content: submission.content } : {}),
    attempts: submission.attempts,
    status: graded ? 'graded' : submission.is_late ? 'late' : 'submitted',
    submitted_at: submission.submitted_at,
    is_late: Boolean(submission.is_late),
    score: graded ? submission.score : null,
    feedback: submission.feedback ?? null,
    graded_at: submission.graded_at ?? null,
    attachments: attachmentsFor(SUBMISSION_TYPE, submission.id),
  }
}

export const byId = (list, id) => list.find((item) => Number(item.id) === Number(id))
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

// ---------------------------------------------------------------------------
// Messaging, events and calendar serializers
//
// These mirror the Laravel resources field-for-field, so the frontend cannot
// tell which backend answered.
// ---------------------------------------------------------------------------

export const serializeUserBrief = (user) =>
  user ? { id: user.id, name: user.name, role: user.role } : null

export const serializeMessage = (message, viewerId) => {
  const sender = byId(db.users, message.sender_id)
  return {
    id: message.id,
    conversation_id: message.conversation_id,
    body: message.body,
    read_at: message.read_at ?? null,
    created_at: message.created_at ?? null,
    is_own: Number(message.sender_id) === Number(viewerId),
    sender: serializeUserBrief(sender),
  }
}

export const serializeConversation = (conversation, viewerId) => ({
  id: conversation.id,
  last_message_at: conversation.last_message_at ?? null,
  participant: serializeUserBrief(
    Number(conversation.participant_a_id) === Number(viewerId)
      ? byId(db.users, conversation.participant_b_id)
      : byId(db.users, conversation.participant_a_id),
  ),
  unread_count: db.messages.filter(
    (message) => message.conversation_id === conversation.id
      && message.read_at == null
      && Number(message.sender_id) !== Number(viewerId),
  ).length,
})

export const serializeEvent = (event, { withAuthor = false } = {}) => ({
  id: event.id,
  title: event.title,
  description: event.description ?? null,
  type: event.type,
  starts_at: event.starts_at,
  ends_at: event.ends_at ?? null,
  all_day: Boolean(event.all_day),
  location: event.location ?? null,
  audience: event.audience,
  is_published: Boolean(event.is_published),
  published_at: event.published_at ?? null,
  created_at: event.created_at ?? null,
  ...(withAuthor
    ? { author: serializeUserBrief(byId(db.users, event.user_id)) }
    : {}),
})

/**
 * Which events a role may see: its own audience, or anything aimed at everyone.
 * Admins see all of them.
 */
export const eventVisibleToRole = (event, role) => {
  if (role === 'admin' || role === 'super_admin') return true
  const audience = role === 'student' ? 'students' : 'teachers'
  return event.audience === 'all' || event.audience === audience
}
