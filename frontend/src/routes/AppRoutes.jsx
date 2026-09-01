import { Route, Routes } from 'react-router-dom'
import LandingPage from '../pages/LandingPage.jsx'
import LoginPage from '../pages/auth/LoginPage.jsx'
import RegisterPage from '../pages/auth/RegisterPage.jsx'
import ForgotPasswordPage from '../pages/auth/ForgotPasswordPage.jsx'
import ResetPasswordPage from '../pages/auth/ResetPasswordPage.jsx'
import ChangePasswordPage from '../pages/auth/ChangePasswordPage.jsx'
import ProfilePage from '../pages/account/ProfilePage.jsx'
import VerifyDocumentPage from '../pages/public/VerifyDocumentPage.jsx'
import AdminAuditLogPage from '../pages/admin/AuditLogPage.jsx'
import SchoolLoginPage from '../pages/school/SchoolLoginPage.jsx'
import OnboardingPage from '../pages/onboarding/OnboardingPage.jsx'
import StudentDashboardPage from '../pages/student/DashboardPage.jsx'
import StudentGradesPage from '../pages/student/GradesPage.jsx'
import StudentReportCardPage from '../pages/student/ReportCardPage.jsx'
import StudentTimetablePage from '../pages/student/TimetablePage.jsx'
import StudentRequestsPage from '../pages/student/RequestsPage.jsx'
import StudentDocumentsPage from '../pages/student/DocumentsPage.jsx'
import StudentAttendancePage from '../pages/student/AttendancePage.jsx'
import StudentTranscriptPage from '../pages/student/TranscriptPage.jsx'
import StudentExamsPage from '../pages/student/ExamsPage.jsx'
import StudentHomeworkPage from '../pages/student/HomeworkPage.jsx'
import StudentMaterialsPage from '../pages/student/MaterialsPage.jsx'
import StudentQuizzesPage from '../pages/student/QuizzesPage.jsx'
import StudentInsightsPage from '../pages/student/InsightsPage.jsx'
import AnnouncementsPage from '../pages/AnnouncementsPage.jsx'
import MessagesPage from '../pages/MessagesPage.jsx'
import CalendarPage from '../pages/CalendarPage.jsx'
import TeacherDashboardPage from '../pages/teacher/DashboardPage.jsx'
import TeacherAssignmentsPage from '../pages/teacher/AssignmentsPage.jsx'
import TeacherClassPage from '../pages/teacher/ClassPage.jsx'
import TeacherGradeEntryPage from '../pages/teacher/GradeEntryPage.jsx'
import TeacherGradebookPage from '../pages/teacher/GradebookPage.jsx'
import TeacherAttendancePage from '../pages/teacher/AttendancePage.jsx'
import TeacherExamsPage from '../pages/teacher/ExamsPage.jsx'
import TeacherHomeworkPage from '../pages/teacher/HomeworkPage.jsx'
import TeacherMaterialsPage from '../pages/teacher/MaterialsPage.jsx'
import TeacherQuizzesPage from '../pages/teacher/QuizzesPage.jsx'
import TeacherInsightsPage from '../pages/teacher/InsightsPage.jsx'
import TeacherCommentPage from '../pages/teacher/CommentPage.jsx'
import TeacherTimetablePage from '../pages/teacher/TimetablePage.jsx'
import AdminDashboardPage from '../pages/admin/DashboardPage.jsx'
import AdminStructurePage from '../pages/admin/StructurePage.jsx'
import AdminStudentsPage from '../pages/admin/StudentsPage.jsx'
import AdminTeachersPage from '../pages/admin/TeachersPage.jsx'
import AdminTimetablePage from '../pages/admin/TimetablePage.jsx'
import AdminAttendancePage from '../pages/admin/AttendancePage.jsx'
import AdminExamsPage from '../pages/admin/ExamsPage.jsx'
import AdminImportPage from '../pages/admin/ImportPage.jsx'
import AdminRequestsPage from '../pages/admin/RequestsPage.jsx'
import AdminAnnouncementsPage from '../pages/admin/AnnouncementsPage.jsx'
import AdminEventsPage from '../pages/admin/EventsPage.jsx'
import AdminAnalyticsPage from '../pages/admin/AnalyticsPage.jsx'
import AdminBillingPage from '../pages/admin/BillingPage.jsx'
import AdminSettingsPage from '../pages/admin/SettingsPage.jsx'
import SuperAdminDashboardPage from '../pages/super-admin/DashboardPage.jsx'
import SuperAdminSchoolsPage from '../pages/super-admin/SchoolsPage.jsx'
import SuperAdminSchoolDetailPage from '../pages/super-admin/SchoolDetailPage.jsx'
import SuperAdminPlansPage from '../pages/super-admin/PlansPage.jsx'
import SuperAdminSubscriptionsPage from '../pages/super-admin/SubscriptionsPage.jsx'
import SuperAdminPaymentsPage from '../pages/super-admin/PaymentsPage.jsx'
import ForbiddenPage from '../pages/errors/ForbiddenPage.jsx'
import NotFoundPage from '../pages/errors/NotFoundPage.jsx'
import { ProtectedRoute, RoleGuard } from './roleGuards.jsx'

function teacherRoute(element) {
  return (
    <ProtectedRoute>
      <RoleGuard allowed={['teacher']}>{element}</RoleGuard>
    </ProtectedRoute>
  )
}

function adminRoute(element) {
  return (
    <ProtectedRoute>
      <RoleGuard allowed={['admin']}>{element}</RoleGuard>
    </ProtectedRoute>
  )
}

function studentRoute(element) {
  return (
    <ProtectedRoute>
      <RoleGuard allowed={['student']}>{element}</RoleGuard>
    </ProtectedRoute>
  )
}

function superAdminRoute(element) {
  return (
    <ProtectedRoute>
      <RoleGuard allowed={['super_admin']}>{element}</RoleGuard>
    </ProtectedRoute>
  )
}

export default function AppRoutes() {
  return (
    <Routes>
      <Route path="/" element={<LandingPage />} />
      <Route path="/login" element={<LoginPage />} />
      <Route path="/register" element={<RegisterPage />} />
      <Route path="/onboarding" element={<OnboardingPage />} />
      <Route path="/school/:slug" element={<SchoolLoginPage />} />
      <Route path="/forgot-password" element={<ForgotPasswordPage />} />
      <Route path="/reset-password" element={<ResetPasswordPage />} />
      <Route path="/verify" element={<VerifyDocumentPage />} />
      <Route path="/verify/:code" element={<VerifyDocumentPage />} />
      <Route path="/forbidden" element={<ForbiddenPage />} />

      <Route
        path="/account/password"
        element={
          <ProtectedRoute>
            <ChangePasswordPage />
          </ProtectedRoute>
        }
      />
      <Route
        path="/account/profile"
        element={
          <ProtectedRoute>
            <ProfilePage />
          </ProtectedRoute>
        }
      />

      <Route path="/student" element={studentRoute(<StudentDashboardPage />)} />
      <Route path="/student/grades" element={studentRoute(<StudentGradesPage />)} />
      <Route path="/student/report-card" element={studentRoute(<StudentReportCardPage />)} />
      <Route path="/student/timetable" element={studentRoute(<StudentTimetablePage />)} />
      <Route path="/student/requests" element={studentRoute(<StudentRequestsPage />)} />
      <Route path="/student/documents" element={studentRoute(<StudentDocumentsPage />)} />
      <Route path="/student/attendance" element={studentRoute(<StudentAttendancePage />)} />
      <Route path="/student/transcript" element={studentRoute(<StudentTranscriptPage />)} />
      <Route path="/student/exams" element={studentRoute(<StudentExamsPage />)} />
      <Route path="/student/homework" element={studentRoute(<StudentHomeworkPage />)} />
      <Route path="/student/materials" element={studentRoute(<StudentMaterialsPage />)} />
      <Route path="/student/quizzes" element={studentRoute(<StudentQuizzesPage />)} />
      <Route path="/student/announcements" element={studentRoute(<AnnouncementsPage />)} />
      <Route path="/student/messages" element={studentRoute(<MessagesPage />)} />
      <Route path="/student/calendar" element={studentRoute(<CalendarPage />)} />
      <Route path="/student/insights" element={studentRoute(<StudentInsightsPage />)} />

      <Route path="/teacher" element={teacherRoute(<TeacherDashboardPage />)} />
      <Route path="/teacher/assignments" element={teacherRoute(<TeacherAssignmentsPage />)} />
      <Route path="/teacher/timetable" element={teacherRoute(<TeacherTimetablePage />)} />
      <Route path="/teacher/grades" element={teacherRoute(<TeacherGradeEntryPage />)} />
      <Route path="/teacher/attendance" element={teacherRoute(<TeacherAttendancePage />)} />
      <Route path="/teacher/exams" element={teacherRoute(<TeacherExamsPage />)} />
      <Route path="/teacher/homework" element={teacherRoute(<TeacherHomeworkPage />)} />
      <Route path="/teacher/materials" element={teacherRoute(<TeacherMaterialsPage />)} />
      <Route path="/teacher/quizzes" element={teacherRoute(<TeacherQuizzesPage />)} />
      <Route path="/teacher/announcements" element={teacherRoute(<AnnouncementsPage />)} />
      <Route path="/teacher/messages" element={teacherRoute(<MessagesPage />)} />
      <Route path="/teacher/calendar" element={teacherRoute(<CalendarPage />)} />
      <Route path="/teacher/insights" element={teacherRoute(<TeacherInsightsPage />)} />
      <Route
        path="/teacher/students/:studentId/comment"
        element={teacherRoute(<TeacherCommentPage />)}
      />
      <Route path="/teacher/classes/:classId/subjects/:subjectId" element={teacherRoute(<TeacherClassPage />)} />
      <Route
        path="/teacher/classes/:classId/subjects/:subjectId/grades"
        element={teacherRoute(<TeacherGradebookPage />)}
      />

      <Route path="/admin" element={adminRoute(<AdminDashboardPage />)} />
      <Route path="/admin/structure" element={adminRoute(<AdminStructurePage />)} />
      <Route path="/admin/students" element={adminRoute(<AdminStudentsPage />)} />
      <Route path="/admin/teachers" element={adminRoute(<AdminTeachersPage />)} />
      <Route path="/admin/timetable" element={adminRoute(<AdminTimetablePage />)} />
      <Route path="/admin/attendance" element={adminRoute(<AdminAttendancePage />)} />
      <Route path="/admin/exams" element={adminRoute(<AdminExamsPage />)} />
      <Route path="/admin/import" element={adminRoute(<AdminImportPage />)} />
      <Route path="/admin/requests" element={adminRoute(<AdminRequestsPage />)} />
      <Route path="/admin/announcements" element={adminRoute(<AdminAnnouncementsPage />)} />
      <Route path="/admin/events" element={adminRoute(<AdminEventsPage />)} />
      <Route path="/admin/messages" element={adminRoute(<MessagesPage />)} />
      <Route path="/admin/calendar" element={adminRoute(<CalendarPage />)} />
      <Route path="/admin/analytics" element={adminRoute(<AdminAnalyticsPage />)} />
      <Route path="/admin/billing" element={adminRoute(<AdminBillingPage />)} />
      <Route path="/admin/settings" element={adminRoute(<AdminSettingsPage />)} />
      <Route path="/admin/audit-logs" element={adminRoute(<AdminAuditLogPage />)} />

      <Route path="/super-admin" element={superAdminRoute(<SuperAdminDashboardPage />)} />
      <Route path="/super-admin/schools" element={superAdminRoute(<SuperAdminSchoolsPage />)} />
      <Route path="/super-admin/schools/:id" element={superAdminRoute(<SuperAdminSchoolDetailPage />)} />
      <Route path="/super-admin/plans" element={superAdminRoute(<SuperAdminPlansPage />)} />
      <Route path="/super-admin/subscriptions" element={superAdminRoute(<SuperAdminSubscriptionsPage />)} />
      <Route path="/super-admin/payments" element={superAdminRoute(<SuperAdminPaymentsPage />)} />

      <Route path="*" element={<NotFoundPage />} />
    </Routes>
  )
}
