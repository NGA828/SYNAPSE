/**
 * SYNAPSE — contract stub for the Postman collection.
 *
 * This is NOT the API. It is a zero-dependency Node stub that answers the
 * endpoints with the same JSON shapes the Laravel controllers return, so the
 * collection can be executed where PHP/MySQL are not installed (CI smoke run,
 * a laptop with no Docker, this repo's screenshot pipeline).
 *
 *   node docs/postman/mock-server.js       # listens on http://127.0.0.1:8099
 *
 * Rules it does implement, because they are the ones worth testing:
 *   - no token on a protected route  -> 401 {"message":"Unauthenticated."}
 *   - unknown credentials            -> 422 with the real error keys
 *   - duplicate student              -> 422 on email + matricule
 *   - everything else                -> the documented success shape
 *
 * Rules it does NOT implement (use the real backend for these): tenant
 * isolation, subscription gating, plan limits, at-risk maths, PDF rendering.
 */

const http = require('http');

const PORT = Number(process.env.PORT || 8099);

const TOKEN = `14|${'Jk9dS2pQxLm4TvBn8WqZrE7yUc1aHfGtPvN3sRwYb'}`;
const SCHOOL = { id: 1, name: 'AICS Cameroon', slug: 'aics', logo_url: null };
const YEAR = { id: 2, name: '2026/2027' };

const PUBLIC = [/^\/api\/login$/, /^\/api\/forgot-password$/, /^\/api\/reset-password$/,
  /^\/api\/verify\//, /^\/api\/onboarding\//, /^\/api\/school\//];

const USERS = {
  'admin@synapse.test': { id: 12, name: 'Mrs. Chen', role: 'admin', school: SCHOOL },
  'teacher@synapse.test': { id: 14, name: 'Mr. David', role: 'teacher', school: SCHOOL },
  'student@synapse.test': { id: 9, name: 'John Doe', role: 'student', school: SCHOOL },
};

const envelope = (items) => ({
  data: items,
  links: { first: `http://127.0.0.1:${PORT}${''}`, last: null, prev: null, next: null },
  meta: { current_page: 1, from: 1, last_page: 1, per_page: 15, to: items.length, total: items.length },
});

const STUDENT_ROW = {
  id: 58,
  name: 'Mary Bih',
  email: 'mary.bih@synapse.test',
  matricule: 'ST2026101',
  user_id: 214,
  class: { id: 3, name: 'Level 2A' },
  academic_year: YEAR,
  created_at: '2026-09-19T10:04:12.000000Z',
};

const GRADEBOOK = {
  class: { id: 3, name: 'Level 2A' },
  subject: { id: 2, name: 'Mathematics', code: 'MAT' },
  academic_year: YEAR,
  semester: { id: 1, name: 'Semester 1', sequence: 1 },
  components: [
    { id: 1, name: 'Assignments', weight: 30 },
    { id: 2, name: 'Quizzes', weight: 20 },
    { id: 3, name: 'Midterm', weight: 20 },
    { id: 4, name: 'Exam', weight: 30 },
  ],
  students: [
    { id: 4, name: 'John Doe', matricule: 'ST2026045', test1: 15.0, test2: 13.5, exam: 14.0,
      scores: { 1: 16.0, 2: 12.0, 3: 14.5, 4: 14.0 }, average: 14.2 },
    { id: 5, name: 'Mary Smith', matricule: 'ST2026031', test1: 17.5, test2: 16.0, exam: 15.5,
      scores: { 1: 18.0, 2: 16.5, 3: 15.0, 4: 15.5 }, average: 16.1 },
    { id: 6, name: 'Peter Paul', matricule: 'ST2026028', test1: 9.0, test2: 8.5, exam: 10.0,
      scores: { 1: 9.5, 2: 7.0, 3: 10.5, 4: 10.0 }, average: 9.3 },
  ],
};

const AT_RISK = {
  id: 6,
  student: { id: 6, name: 'Peter Paul', matricule: 'ST2026028', class: { id: 3, name: 'Level 2A' } },
  average: 9.3,
  severity: 'critical',
  attendance: 78.4,
  signals: [
    { code: 'average_below_threshold', severity: 'critical', message: 'Average 9.30/20 is below the 10.00 pass mark.' },
    { code: 'attendance_drop', severity: 'warning', message: 'Attendance 78.4% is below the 85% threshold.' },
    { code: 'failing_subject', severity: 'warning', message: 'Below 10/20 in Physics (8.50) and History (9.00).' },
  ],
};

const DASHBOARD = {
  student: { id: 4, matricule: 'ST2026045', user_id: 9, created_at: '2026-08-14T09:12:41.000000Z' },
  class: { id: 3, name: 'Level 2A' },
  academic_year: YEAR,
  summary: { average: 13.75, subjects: 6, pending_requests: 1, announcements: 3 },
  grades: [
    { subject: { id: 2, name: 'Mathematics', code: 'MAT' }, sequence: 14.5, average: 13.8 },
    { subject: { id: 1, name: 'English', code: 'ENG' }, sequence: 12.0, average: 12.6 },
    { subject: { id: 4, name: 'Computer Science', code: 'CSC' }, sequence: 16.0, average: 15.9 },
  ],
  timetable: [
    { day: 'monday', starts_at: '08:00', ends_at: '09:00', subject: { name: 'Mathematics' }, room: 'B12' },
  ],
  announcements: [
    { id: 31, title: 'End of term assessment', audience: 'students', published_at: '2026-09-14T07:30:00.000000Z' },
  ],
};

const ROUTES = [
  // method, pattern, handler(req, body)
  ['POST', /^\/api\/login$/, (req, body) => {
    const user = USERS[body.email];
    if (!user) {
      return [422, { message: 'The given data was invalid.',
        errors: { email: ['These credentials do not match our records.'] } }];
    }
    return [200, { token: TOKEN, must_change_password: false, user }];
  }],
  ['GET', /^\/api\/user$/, () => [200, { user: { ...USERS['admin@synapse.test'], student: null, teacher: null } }]],
  ['GET', /^\/api\/tenant$/, () => [200, { school: SCHOOL, academic_year: YEAR }]],
  ['POST', /^\/api\/logout$/, () => [200, { message: 'Logged out successfully.' }]],
  ['GET', /^\/api\/student\/dashboard$/, () => [200, DASHBOARD]],
  ['GET', /^\/api\/teacher\/classes\/\d+\/subjects\/\d+\/gradebook$/, () => [200, GRADEBOOK]],
  ['POST', /^\/api\/teacher\/classes\/\d+\/subjects\/\d+\/grades$/, () => [200, GRADEBOOK]],
  ['GET', /^\/api\/admin\/students$/, () => [200, envelope([STUDENT_ROW])]],
  ['POST', /^\/api\/admin\/students$/, (req, body) => {
    if (body.email === 'john@synapse.test' || body.matricule === 'ST2026045') {
      return [422, { message: 'The given data was invalid.', errors: {
        email: ['The email has already been taken.'],
        matricule: ['The matricule has already been taken.'],
      } }];
    }
    return [201, { data: STUDENT_ROW }];
  }],
  ['GET', /^\/api\/admin\/analytics\/at-risk$/, () => [200, envelope([AT_RISK])]],
  ['GET', /^\/api\/(admin|teacher)\/analytics$/, () => [200, { data: {
    academic_year: { id: YEAR.id, name: YEAR.name },
    scope: { type: 'school' },
    counts: { students: 128, teachers: 14, classes: 8, subjects: 12 },
  } }]],
  ['GET', /^\/api\/onboarding\/plans$/, () => [200, { data: [
    { id: 1, slug: 'starter', name: 'Starter', price: 15000 },
    { id: 2, slug: 'professional', name: 'Professional', price: 45000 },
    { id: 3, slug: 'enterprise', name: 'Enterprise', price: 120000 },
  ] }]],
];

// POSTs that are actions (publish, activate, login…) answer 200; only a POST
// to a collection URI is a create, and only a create answers 201.
const ACTION_VERBS = new Set(['upgrade', 'renew', 'publish', 'unpublish', 'activate', 'status',
  'draft', 'grade', 'chat', 'preview', 'read', 'read-all', 'send', 'logout',
  'password', 'generate-document', 'sign-out-others', 'import', 'review', 'report-cards',
  'forgot-password', 'reset-password', 'grades', 'attendance', 'report-cards']);

// A handful of actions answer something other than 200 (bulk PDF generation is
// queued, so it answers 202 Accepted).
const STATUS_BY_VERB = { 'report-cards': 202 };

function fallback(method, path) {
  if (method === 'GET') return [200, envelope([])];
  if (method === 'POST') {
    const last = path.split('/').filter(Boolean).pop() || '';
    if (STATUS_BY_VERB[last]) return [STATUS_BY_VERB[last], { message: 'Accepted.' }];
    const isCreate = !ACTION_VERBS.has(last) && !/^\d+$/.test(last)
      && !last.includes(':') && !last.includes('{{');
    return isCreate ? [201, { data: { id: 101 } }] : [200, { message: 'OK' }];
  }
  if (method === 'DELETE') return [200, { message: 'Deleted.' }];
  return [200, { data: { id: 1 } }];
}

const server = http.createServer((req, res) => {
  let raw = '';
  req.on('data', (c) => { raw += c; });
  req.on('end', () => {
    const url = new URL(req.url, `http://127.0.0.1:${PORT}`);
    const path = url.pathname;
    let body = {};
    try { body = raw ? JSON.parse(raw) : {}; } catch { body = {}; }

    const send = (status, payload) => {
      const json = JSON.stringify(payload);
      res.writeHead(status, {
        'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(json),
        'X-Powered-By': 'synapse-contract-stub',
      });
      res.end(json);
    };

    if (!PUBLIC.some((p) => p.test(path)) && !req.headers.authorization) {
      return send(401, { message: 'Unauthenticated.' });
    }

    for (const [method, pattern, handler] of ROUTES) {
      if (req.method === method && pattern.test(path)) {
        const [status, payload] = handler(req, body);
        return send(status, payload);
      }
    }
    const [status, payload] = fallback(req.method, path);
    send(status, payload);
  });
});

server.listen(PORT, '127.0.0.1', () => {
  console.log(`SYNAPSE contract stub listening on http://127.0.0.1:${PORT}`);
});
