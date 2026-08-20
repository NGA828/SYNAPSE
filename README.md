# SYNAPSE — Multi-Tenant School Management SaaS

> **Phase 1 — Foundation & Strict Modular Architecture** · **Phase 2 — Database Schema & Teaching Assignment Model** · **Phase 3 — Grades, Grade Entry, Report Cards & Timetables** · **Phase 4 — Requests, Documents, Announcements & Notifications** · **SaaS Upgrade — Multi-Tenancy, Super Admin, Onboarding & Subscriptions**

SYNAPSE is a production-grade, **multi-tenant** SaaS platform. Independent schools share
one application and one MySQL database while their data stays **completely isolated** —
students, teachers, classes, subjects, grades, requests, documents, announcements and
notifications are all scoped to a school (tenant). Authorization is enforced **in Laravel**,
never in the React UI.

---

## Tenancy model

```
                SYNAPSE PLATFORM
                      │
                  SUPER ADMIN
                      │
        ┌─────────────┴─────────────┐
     SCHOOL A                    SCHOOL B
        │                            │
     ADMIN A                     ADMIN B
        │                            │
   ┌────┴─────┐                ┌────┴─────┐
Teachers   Students         Teachers   Students
```

- **Shared app, shared DB, row-level isolation** via a `school_id` foreign key on every
  tenant-owned table plus a global `TenantScope` that auto-scopes every Eloquent query.
- **`TenantContext`** holds the resolved school; `IdentifyTenant` middleware derives it
  from the **authenticated user** — never from a frontend-supplied ID.
- **Global vs tenant vs user-owned**: `subscription_plans` and `schools` are platform
  global; academic tables are tenant-owned; `grades`/`documents`/`notifications` are also
  user-owned (a student only ever sees their own rows).
- **Roles**: `super_admin` (platform) → `admin` (school) → `teacher` → `student`.
  Teacher access remains strictly governed by `TeachingAssignment` records, now
  additionally scoped to the school.

---

## Tech Stack

| Layer     | Technology                                             |
| --------- | ------------------------------------------------------ |
| Frontend  | React 19 (Vite) · Tailwind CSS v4 · Lucide Icons       |
| Backend   | Laravel 13 (PHP 8.3+) · Laravel Sanctum · MySQL        |
| Auth      | Sanctum personal access tokens (Bearer)                |
| Payments  | Provider abstraction (Mock / MTN MoMo / Orange Money / Card) |

---

## Repository Structure

```
SYNAPSE/
├── frontend/src/
│   ├── assets/               # Static assets
│   ├── components/
│   │   ├── ui/               # Atomic primitives (Button, Input, Card, Badge, Spinner)
│   │   ├── layout/           # Sidebar, TopBar, PageContainer, SuperAdminLayout
│   │   ├── forms/            # LoginForm, ErrorDisplay
│   │   ├── dashboard/        # StatCard, tables, timetable, lists
│   │   ├── tenant/           # SubscriptionBanner (trial/expired/limit states)
│   │   ├── super-admin/      # BarChart, StatusBadge
│   │   ├── landing/          # Marketing page sections
│   │   └── brand/            # Logo / LogoMark
│   ├── pages/                # Thin route handlers only
│   │   ├── auth/             # LoginPage, RegisterPage
│   │   ├── school/           # Branded per-school login
│   │   ├── onboarding/       # Multi-step school registration
│   │   ├── student|teacher|admin/   # admin/ includes billing + settings
│   │   ├── super-admin/      # Platform dashboard, schools, plans, subscriptions, payments
│   │   └── errors/           # ForbiddenPage, NotFoundPage
│   ├── hooks/                # useAuth, useTenant, useSubscription, useFeature, useAsyncList, …
│   ├── services/             # apiClient, auth, tenant, school, subscription, billing, onboarding, mockAdapter
│   ├── context/              # AuthContext, TenantContext, SubscriptionContext
│   ├── routes/               # AppRoutes, roleGuards
│   └── utils/                # cn, validators, formatters, grades, timetable, requests
│
└── backend/app/
    ├── Http/Controllers/Api/ # Auth, Tenant, Onboarding, Student, Teacher, Admin,
    │                         # SuperAdmin (Dashboard, School, Plan, Subscription, Payment)
    ├── Http/Middleware/      # EnsureRoleIs, IdentifyTenant, EnforceSubscription,
    │                         # EnsureFeature, CheckTeachingAssignment
    ├── Http/Requests/        # Validation for every write endpoint
    ├── Models/               # School, SubscriptionPlan, Subscription, Payment,
    │                         # SchoolSetting, AuditLog + all academic models
    ├── Models/Concerns/      # BelongsToSchool trait
    ├── Models/Scopes/        # TenantScope (global scope)
    ├── Services/             # TenantContext, Subscription, Billing, Payment, School,
    │                         # Onboarding, Audit + academic services
    ├── Services/Payments/    # PaymentGateway interface + Mock/MTN/Orange/Card
    └── Policies/             # School, Student, Teacher, Grade, Request, Document,
                              # Announcement, TeachingAssignment
```

---

## Design System

Inspired by modern education-SaaS dashboards on Dribbble (light theme, indigo→violet
brand, soft cards, sidebar navigation, stat cards, tables, timetable grid):

- **Brand** — indigo (`brand-*`) with a violet/teal gradient accent
- **Type** — Inter Variable, bundled locally via `@fontsource-variable/inter`
- **Surfaces** — white cards, `rounded-2xl`, 1px `slate-200` borders, soft shadows
- **Status** — emerald (success), amber (warning), rose (danger), brand (info)

The public landing page (`/`) demonstrates the full visual language: navbar, hero with a
live dashboard mockup, features, "how it works", role cards, CTA and footer.

---

## Quick Start

### Backend (Laravel)

```bash
cd backend
composer install
cp .env.example .env          # set DB_* to your MySQL credentials
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve              # http://127.0.0.1:8000
```

> `.env.example` defaults to MySQL. For a zero-config local run, switch to SQLite:
> `DB_CONNECTION=sqlite` and create `database/database.sqlite`.

### Frontend (Vite)

```bash
cd frontend
npm install
cp .env.example .env           # set VITE_API_URL and VITE_USE_MOCK=false
npm run dev                    # http://localhost:5173
```

---

## Demo Accounts

Seeded by `DatabaseSeeder` (passwords hashed — never stored in plain text). All use
`password123`:

| Role                | Email                            | School              |
| ------------------- | -------------------------------- | ------------------- |
| Super Admin         | `superadmin@synapse.test`        | Platform            |
| Administrator       | `admin@synapse.test`             | AICS Cameroon       |
| Teacher             | `teacher@synapse.test`           | AICS Cameroon       |
| Student             | `student@synapse.test`           | AICS Cameroon       |
| Administrator       | `admin.saintalbert@synapse.test` | Saint Albert (trial)|
| Teacher             | `teacher.saintalbert@synapse.test` | Saint Albert (trial)|
| Student             | `student.saintalbert@synapse.test` | Saint Albert (trial)|
| Administrator       | `admin.demo@synapse.test`        | Demo Intl (expired) |

---

## Mock Mode

When the Laravel backend is not running, set `VITE_USE_MOCK=true` in `frontend/.env`.
`services/mockAdapter.js` installs an in-browser axios adapter that reproduces the exact
JSON contract of the Laravel controllers — including **tenant isolation, subscription
enforcement and plan limits** — so the whole multi-tenant platform can be demoed
end-to-end. Set it to `false` to talk to the real API — no other code changes are required.

---

## SaaS capabilities

- **Super Admin** — platform dashboard (schools, users, subscriptions, MRR), school CRUD
  with activate/suspend/trial/expire, plan management, subscription & payment views.
- **Onboarding** — multi-step public registration: school info → admin account → plan →
  free trial (configurable `SYNAPSE_TRIAL_DAYS`, default 14).
- **Subscriptions & billing** — `subscription_plans` (configurable price, limits, features),
  per-school `subscriptions` history, `payments`, and a school-admin billing dashboard with
  upgrade/renew (mock payment in dev) and usage-vs-limit progress bars.
- **Plan limits** — enforced in Laravel (`SubscriptionService::assertCanCreate`); a 422 is
  returned with an upgrade message when a school hits a student/teacher/class limit.
- **Subscription enforcement** — `EnforceSubscription` middleware blocks academic
  operations for expired/suspended schools while leaving billing reachable so they can
  renew. The frontend shows a clear banner for trial/expired/limit states.
- **Feature flags** — plan features (e.g. `report_cards`, `document_management`) gate both
  the UI (`useFeature`) and the API (`feature:` middleware).
- **White-label branding** — per-school name/logo/primary color via `/tenant`; branded
  sign-in at `/school/{slug}`; school settings (grading scale, semesters, branding).
- **Payments (Cameroon-first)** — `PaymentGateway` abstraction with `Mock` (dev-only,
  always `sandbox`), `MTN Mobile Money`, `Orange Money` and `Card` adapters that refuse to
  charge until real credentials are configured (no fake production payments).
- **Audit logs** — tenant-scoped `audit_logs` recorded on school/onboarding/subscription
  events.

---

## Authentication Flow

1. `LoginForm` → `useAuth().login()` → `authService.login()` → `POST /login`
2. Laravel validates via `LoginRequest`, `AuthService` issues a Sanctum token
3. Token is stored in `localStorage` and injected by `apiClient` on every request
4. `useRoleRedirect` navigates to the role's dashboard (`/student`, `/teacher`, `/admin`)
5. `ProtectedRoute` blocks unauthenticated access; `RoleGuard` blocks wrong-role access
6. Logout revokes the token server-side and clears local state

**Backend enforcement:** `/student/dashboard` is guarded by `auth:sanctum` **and**
`role:student`. Teacher access is governed by `CheckTeachingAssignment`, which verifies a
`TeachingAssignment` record (teacher → subject → class → academic year) before any
class-student resource is served — the frontend never grants access on its own.

## Data Model (Phases 2–3)

```
academic_years        (name, start_date, end_date, is_current)
classes               (name)                    → SchoolClass model
subjects              (name, code)
students / teachers   (profiles on top of users)
enrollments           (student, class, academic_year)      ← preserves history
teaching_assignments  (teacher, subject, class, academic_year) ← source of truth
grades                (student, subject, class, year, teacher, test1, test2, exam)
timetable_entries     (class, year, subject, day 1–5, start, end)
```

- **One enrollment per (student, class, year)** — a student's past classes are never
  deleted, so academic history is preserved across year transitions.
- **One assignment per (teacher, subject, class, year)** — `CheckTeachingAssignment`
  middleware + service-layer checks both enforce it (defense in depth). A teacher can
  only view or save grades for classes they are assigned to.
- **One grade per (student, subject, class, year)** — `teacher_id` records who entered
  it; the average is the mean of all entered components (0–20 scale).
- Administrators manage everything via the `/admin/*` API; teachers only ever receive
  data scoped to their own assignments via `/teacher/*`; students only see their own
  grades, report card and timetable via `/student/*`.

## Requests, Documents, Announcements & Notifications (Phase 4)

```
requests        (student, reference REQ-*, type, reason, status, admin_note)
documents       (request, student, title, file metadata + storage path)
announcements   (author, title, body, audience: all|students|teachers)
notifications   (recipient user, type, title, message, data, read_at)
```

- **Request lifecycle** — `submitted → under_review → approved → ready` (or rejected).
  Administrators drive each transition; students track progress with a visual stepper.
- **Documents** — administrators generate the official document for an approved request,
  which marks it `ready` and lets the student download it.
- **Announcements** — audience-targeted (`all` / `students` / `teachers`); the listing
  endpoint is role-aware so each audience only ever sees what's meant for them.
- **Notifications** — every event (request submitted, status change, document ready,
  announcement published) notifies the right users; the bell shows an unread count and
  supports per-item and mark-all-read.

---

## Architecture Rules Enforced

- Components ≤ 150 lines; UI primitives ≤ 80 lines; pages are thin wrappers
- Components **never** import `axios` or `authService` — they use hooks
- Services contain no JSX; hooks contain business logic; utils are pure functions
- Controllers stay thin and delegate to `App\Services` when logic grows
- No inline styles — Tailwind utility classes only; no hardcoded API URLs in components
- Route files contain configuration only (no page logic)

---

## Phase Roadmap

- ✅ **Phase 1** — Foundation, strict modular architecture, auth, role redirects
- ✅ **Phase 2** — Academic year, classes, subjects, `TeachingAssignment` model,
  enrollment, student/teacher registration, teacher-access enforcement
- ✅ **Phase 3** — Grades, grade entry (assignment-scoped), report cards with rank,
  student timetable + admin timetable manager
- ✅ **Phase 4** — Request lifecycle with stepper, document generation/download,
  audience-targeted announcements, notifications with unread count
- ✅ **SaaS upgrade** — Multi-tenant schema (`school_id` + tenant scope), super admin,
  onboarding, subscription plans/subscriptions/payments, billing, plan limits, feature
  flags, white-label branding, payment abstraction, tenant-isolation test suite
