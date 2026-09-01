<?php

use App\Http\Controllers\Api\Admin\AcademicYearController;
use App\Http\Controllers\Api\Admin\AnnouncementController as AdminAnnouncementController;
use App\Http\Controllers\Api\Admin\AnnouncementDraftController as AdminAnnouncementDraftController;
use App\Http\Controllers\Api\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Api\Admin\AttendanceController as AdminAttendanceController;
use App\Http\Controllers\Api\Admin\BillingController;
use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\ExamController as AdminExamController;
use App\Http\Controllers\Api\Admin\EventController as AdminEventController;
use App\Http\Controllers\Api\Admin\GradeComponentController as AdminGradeComponentController;
use App\Http\Controllers\Api\Admin\ImportController as AdminImportController;
use App\Http\Controllers\Api\Admin\ReceiptController;
use App\Http\Controllers\Api\Admin\ReportCardController as AdminReportCardController;
use App\Http\Controllers\Api\Admin\RequestController as AdminRequestController;
use App\Http\Controllers\Api\Admin\SchoolClassController;
use App\Http\Controllers\Api\Admin\SemesterController as AdminSemesterController;
use App\Http\Controllers\Api\Admin\SettingsController;
use App\Http\Controllers\Api\Admin\StudentController as AdminStudentController;
use App\Http\Controllers\Api\Admin\SubjectController;
use App\Http\Controllers\Api\Admin\TeacherController as AdminTeacherController;
use App\Http\Controllers\Api\Admin\TeachingAssignmentController;
use App\Http\Controllers\Api\Admin\TimetableController as AdminTimetableController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\AttachmentController;
use App\Http\Controllers\Api\Auth\PasswordController;
use App\Http\Controllers\Api\Auth\ProfileController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\DocumentVerificationController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\PublicSchoolController;
use App\Http\Controllers\Api\Student\AcademicController as StudentAcademicController;
use App\Http\Controllers\Api\Student\AttendanceController as StudentAttendanceController;
use App\Http\Controllers\Api\Student\DocumentController as StudentDocumentController;
use App\Http\Controllers\Api\Student\HomeworkController as StudentHomeworkController;
use App\Http\Controllers\Api\Student\InsightController as StudentInsightController;
use App\Http\Controllers\Api\Student\LessonController as StudentLessonController;
use App\Http\Controllers\Api\Student\QuizController as StudentQuizController;
use App\Http\Controllers\Api\Student\RequestController as StudentRequestController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\SuperAdmin\AuditLogController as SuperAdminAuditLogController;
use App\Http\Controllers\Api\SuperAdmin\DashboardController as SuperAdminDashboardController;
use App\Http\Controllers\Api\SuperAdmin\PaymentController as SuperAdminPaymentController;
use App\Http\Controllers\Api\SuperAdmin\PlanController as SuperAdminPlanController;
use App\Http\Controllers\Api\SuperAdmin\SchoolController as SuperAdminSchoolController;
use App\Http\Controllers\Api\SuperAdmin\SubscriptionController as SuperAdminSubscriptionController;
use App\Http\Controllers\Api\Teacher\AssignmentController;
use App\Http\Controllers\Api\Teacher\AttendanceController as TeacherAttendanceController;
use App\Http\Controllers\Api\Teacher\ClassStudentsController;
use App\Http\Controllers\Api\Teacher\DashboardController as TeacherDashboardController;
use App\Http\Controllers\Api\Teacher\ExamController as TeacherExamController;
use App\Http\Controllers\Api\Teacher\GradebookController;
use App\Http\Controllers\Api\Teacher\HomeworkController as TeacherHomeworkController;
use App\Http\Controllers\Api\Teacher\HomeworkSubmissionController as TeacherHomeworkSubmissionController;
use App\Http\Controllers\Api\Teacher\LessonController as TeacherLessonController;
use App\Http\Controllers\Api\Teacher\QuizAttemptController as TeacherQuizAttemptController;
use App\Http\Controllers\Api\Teacher\QuizController as TeacherQuizController;
use App\Http\Controllers\Api\Teacher\ReportCardCommentController as TeacherReportCardCommentController;
use App\Http\Controllers\Api\Teacher\TimetableController as TeacherTimetableController;
use App\Http\Controllers\Api\TenantController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login')
    ->name('api.login');

// Password recovery — tightly throttled, and never reveals whether an
// address exists.
Route::post('/forgot-password', [PasswordController::class, 'forgot'])
    ->middleware('throttle:password')
    ->name('api.password.forgot');
Route::post('/reset-password', [PasswordController::class, 'reset'])
    ->middleware('throttle:password')
    ->name('api.password.reset');

// Public authenticity check for a printed document.
Route::get('/verify/{code}', [DocumentVerificationController::class, 'show'])
    ->middleware('throttle:60,1')
    ->name('api.documents.verify');
Route::get('/onboarding/plans', [OnboardingController::class, 'plans'])->name('api.onboarding.plans');
Route::post('/onboarding/schools', [OnboardingController::class, 'store'])->name('api.onboarding.store');
Route::get('/school/{school:slug}', [PublicSchoolController::class, 'show'])->name('api.school.show');

/*
|--------------------------------------------------------------------------
| Authenticated + tenant-resolved (every school user)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');
    Route::get('/user', [AuthController::class, 'user'])->name('api.user');
    Route::get('/tenant', [TenantController::class, 'show'])->name('api.tenant');

    // Reachable even when the account still holds a temporary password.
    Route::post('/password', [PasswordController::class, 'change'])->name('api.password.change');
    Route::get('/profile', [ProfileController::class, 'show'])->name('api.profile');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('api.profile.update');
    Route::post('/profile/sign-out-others', [ProfileController::class, 'signOutOthers'])
        ->name('api.profile.sign-out-others');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('api.notifications');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('api.notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('api.notifications.read');

    Route::get('/announcements', [AnnouncementController::class, 'index'])->name('api.announcements');

    /*
     | Messaging, calendar and events. Role-agnostic by design: who may open a
     | conversation with whom, and which events a role may see, are decisions
     | about the two people and the audience — not about the caller's role, so
     | the services decide rather than the router.
     */
    Route::get('/messages', [MessageController::class, 'index'])->name('api.messages');
    Route::post('/messages', [MessageController::class, 'store'])->name('api.messages.store');
    Route::get('/messages/recipients', [MessageController::class, 'recipients'])
        ->name('api.messages.recipients');
    Route::get('/messages/unread', [MessageController::class, 'unread'])->name('api.messages.unread');
    Route::get('/messages/{conversation}', [MessageController::class, 'show'])->name('api.messages.show');
    Route::post('/messages/{conversation}', [MessageController::class, 'send'])->name('api.messages.send');
    Route::post('/messages/{conversation}/read', [MessageController::class, 'read'])
        ->name('api.messages.read');

    Route::get('/calendar', [CalendarController::class, 'index'])->name('api.calendar');
    Route::get('/calendar/today', [CalendarController::class, 'today'])->name('api.calendar.today');

    Route::get('/events', [EventController::class, 'index'])->name('api.events');
    Route::get('/events/{event}', [EventController::class, 'show'])->name('api.events.show');

    /*
     | Attachment download. Deliberately outside the role groups: who may read
     | a given file depends on the file (a class brief vs a private
     | submission), not on the caller's role, so AttachmentService decides per
     | request. Still behind auth + tenant.
     */
    Route::get('/attachments/{attachment}/download', [AttachmentController::class, 'download'])
        ->name('api.attachments.download');
});

/*
|--------------------------------------------------------------------------
| Student — tenant + subscription enforced
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'tenant', 'role:student', 'password.rotated', 'subscription'])->group(function () {
    Route::get('/student/dashboard', [StudentController::class, 'dashboard'])
        ->name('api.student.dashboard');
    Route::get('/student/grades', [StudentAcademicController::class, 'grades'])
        ->name('api.student.grades');
    Route::get('/student/report-card', [StudentAcademicController::class, 'reportCard'])
        ->middleware('feature:report_cards')
        ->name('api.student.report-card');
    Route::get('/student/timetable', [StudentAcademicController::class, 'timetable'])
        ->name('api.student.timetable');
    Route::get('/student/requests/types', [StudentRequestController::class, 'types'])
        ->name('api.student.requests.types');
    Route::get('/student/requests', [StudentRequestController::class, 'index'])
        ->name('api.student.requests');
    Route::post('/student/requests', [StudentRequestController::class, 'store'])
        ->name('api.student.requests.store');
    Route::get('/student/documents', [StudentDocumentController::class, 'index'])
        ->middleware('feature:document_management')
        ->name('api.student.documents');
    Route::get('/student/documents/{document}/download', [StudentDocumentController::class, 'download'])
        ->middleware('feature:document_management')
        ->name('api.student.documents.download');
    Route::get('/student/attendance', [StudentAttendanceController::class, 'index'])
        ->name('api.student.attendance');
    Route::get('/student/transcript', [StudentAcademicController::class, 'transcript'])
        ->name('api.student.transcript');
    Route::get('/student/report-card/pdf', [StudentAcademicController::class, 'reportCardPdf'])
        ->middleware('feature:report_cards')
        ->name('api.student.report-card.pdf');
    Route::get('/student/transcript/pdf', [StudentAcademicController::class, 'transcriptPdf'])
        ->name('api.student.transcript.pdf');
    Route::get('/student/exams', [StudentAcademicController::class, 'exams'])
        ->name('api.student.exams');

    // Homework: published work for the student's class, and their submissions.
    Route::get('/student/homework', [StudentHomeworkController::class, 'index'])
        ->name('api.student.homework');
    Route::post('/student/homework/{homeworkAssignment}/submit', [StudentHomeworkController::class, 'submit'])
        ->name('api.student.homework.submit');

    // Course materials published by the student's teachers.
    Route::get('/student/materials', [StudentLessonController::class, 'index'])
        ->name('api.student.materials');
    Route::get('/student/materials/{lesson}', [StudentLessonController::class, 'show'])
        ->name('api.student.materials.show');

    // Auto-marked quizzes. `paper` returns the questions with the answer key
    // stripped out server-side; `review` is the only student route that carries
    // it, and only for an attempt that student has already submitted.
    Route::get('/student/insights', [StudentInsightController::class, 'mine'])
        ->name('api.student.insights');

    Route::get('/student/quizzes', [StudentQuizController::class, 'index'])
        ->name('api.student.quizzes');
    Route::get('/student/quizzes/{quiz}/paper', [StudentQuizController::class, 'paper'])
        ->name('api.student.quizzes.paper');
    Route::post('/student/quizzes/{quiz}/submit', [StudentQuizController::class, 'submit'])
        ->name('api.student.quizzes.submit');
    Route::get('/student/quiz-attempts/{quizAttempt}/review', [StudentQuizController::class, 'review'])
        ->name('api.student.quizzes.review');
});

/*
|--------------------------------------------------------------------------
| Teacher — tenant + subscription enforced, assignment scoped
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'tenant', 'role:teacher', 'password.rotated', 'subscription'])->prefix('teacher')->group(function () {
    Route::get('/dashboard', [TeacherDashboardController::class, 'index'])
        ->name('api.teacher.dashboard');
    Route::get('/assignments', [AssignmentController::class, 'index'])
        ->name('api.teacher.assignments');
    Route::get('/timetable', [TeacherTimetableController::class, 'index'])
        ->name('api.teacher.timetable');

    Route::get('/classes/{schoolClass}/subjects/{subject}/students', [ClassStudentsController::class, 'index'])
        ->middleware('teaching.assignment')
        ->name('api.teacher.class.students');

    Route::get('/classes/{schoolClass}/subjects/{subject}/gradebook', [GradebookController::class, 'index'])
        ->middleware('teaching.assignment')
        ->name('api.teacher.class.gradebook');

    Route::post('/classes/{schoolClass}/subjects/{subject}/grades', [GradebookController::class, 'store'])
        ->middleware('teaching.assignment')
        ->name('api.teacher.class.grades.store');

    Route::get('/classes/{schoolClass}/attendance', [TeacherAttendanceController::class, 'index'])
        ->middleware('class.access')
        ->name('api.teacher.class.attendance');

    Route::post('/classes/{schoolClass}/attendance', [TeacherAttendanceController::class, 'store'])
        ->middleware('class.access')
        ->name('api.teacher.class.attendance.store');

    Route::get('/exams', [TeacherExamController::class, 'index'])
        ->name('api.teacher.exams');

    Route::get('/exams/ranking', [TeacherExamController::class, 'ranking'])
        ->name('api.teacher.exams.ranking');

    /*
    | Homework — set by the teacher, submitted by the class, graded by the
    | teacher. Ownership is re-verified in HomeworkService on every action,
    | so these need no `teaching.assignment` middleware: the homework row
    | already carries the class/subject/year the teacher was assigned to.
    */
    Route::get('/homework', [TeacherHomeworkController::class, 'index'])
        ->name('api.teacher.homework');
    Route::post('/homework', [TeacherHomeworkController::class, 'store'])
        ->name('api.teacher.homework.store');
    Route::get('/homework/{homeworkAssignment}', [TeacherHomeworkController::class, 'show'])
        ->name('api.teacher.homework.show');
    Route::put('/homework/{homeworkAssignment}', [TeacherHomeworkController::class, 'update'])
        ->name('api.teacher.homework.update');
    Route::delete('/homework/{homeworkAssignment}', [TeacherHomeworkController::class, 'destroy'])
        ->name('api.teacher.homework.destroy');
    Route::post('/homework/{homeworkAssignment}/publish', [TeacherHomeworkController::class, 'publish'])
        ->name('api.teacher.homework.publish');
    Route::post('/homework/{homeworkAssignment}/unpublish', [TeacherHomeworkController::class, 'unpublish'])
        ->name('api.teacher.homework.unpublish');
    Route::get('/homework/{homeworkAssignment}/submissions', [TeacherHomeworkController::class, 'submissions'])
        ->name('api.teacher.homework.submissions');
    Route::get('/homework-submissions/{homeworkSubmission}', [TeacherHomeworkSubmissionController::class, 'show'])
        ->name('api.teacher.homework-submissions.show');
    Route::post('/homework-submissions/{homeworkSubmission}/grade', [TeacherHomeworkSubmissionController::class, 'grade'])
        ->name('api.teacher.homework-submissions.grade');

    /*
    | Course materials — same ownership rules as homework: a teacher may only
    | write for a class/subject they hold a TeachingAssignment for, enforced
    | in LessonService on every action.
    */
    Route::get('/materials', [TeacherLessonController::class, 'index'])
        ->name('api.teacher.materials');
    Route::post('/materials', [TeacherLessonController::class, 'store'])
        ->name('api.teacher.materials.store');
    Route::get('/materials/{lesson}', [TeacherLessonController::class, 'show'])
        ->name('api.teacher.materials.show');
    Route::put('/materials/{lesson}', [TeacherLessonController::class, 'update'])
        ->name('api.teacher.materials.update');
    Route::delete('/materials/{lesson}', [TeacherLessonController::class, 'destroy'])
        ->name('api.teacher.materials.destroy');
    Route::post('/materials/{lesson}/publish', [TeacherLessonController::class, 'publish'])
        ->name('api.teacher.materials.publish');
    Route::post('/materials/{lesson}/unpublish', [TeacherLessonController::class, 'unpublish'])
        ->name('api.teacher.materials.unpublish');

    /*
    | Quizzes — same ownership guard as homework and materials, enforced in
    | QuizService. Publishing requires a complete, markable paper.
    */
    Route::get('/quizzes', [TeacherQuizController::class, 'index'])
        ->name('api.teacher.quizzes');
    Route::post('/quizzes', [TeacherQuizController::class, 'store'])
        ->name('api.teacher.quizzes.store');
    Route::get('/quizzes/{quiz}', [TeacherQuizController::class, 'show'])
        ->name('api.teacher.quizzes.show');
    Route::put('/quizzes/{quiz}', [TeacherQuizController::class, 'update'])
        ->name('api.teacher.quizzes.update');
    Route::delete('/quizzes/{quiz}', [TeacherQuizController::class, 'destroy'])
        ->name('api.teacher.quizzes.destroy');
    Route::post('/quizzes/{quiz}/publish', [TeacherQuizController::class, 'publish'])
        ->name('api.teacher.quizzes.publish');
    Route::post('/quizzes/{quiz}/unpublish', [TeacherQuizController::class, 'unpublish'])
        ->name('api.teacher.quizzes.unpublish');
    Route::get('/quizzes/{quiz}/results', [TeacherQuizController::class, 'results'])
        ->name('api.teacher.quizzes.results');
    Route::get('/quiz-attempts/{quizAttempt}', [TeacherQuizAttemptController::class, 'show'])
        ->name('api.teacher.quiz-attempts.show');
    Route::post('/quiz-attempts/{quizAttempt}/review', [TeacherQuizAttemptController::class, 'review'])
        ->name('api.teacher.quiz-attempts.review');

    /*
    | Analytics — the same numbers an administrator sees, restricted to the
    | classes this teacher holds, so the two views cannot disagree.
    */
    Route::get('/analytics', [AnalyticsController::class, 'overview'])
        ->name('api.teacher.analytics');
    Route::get('/analytics/at-risk', [AnalyticsController::class, 'register'])
        ->name('api.teacher.analytics.at-risk');
    Route::get('/analytics/students/{student}', [AnalyticsController::class, 'student'])
        ->name('api.teacher.analytics.student');

    /*
    | Report-card appreciations. Drafting saves nothing; only `update` records
    | what a teacher has actually approved, so generated text never reaches a
    | PDF unreviewed.
    */
    Route::get('/students/{student}/report-card-comment', [TeacherReportCardCommentController::class, 'show'])
        ->name('api.teacher.report-card-comment');
    Route::post('/students/{student}/report-card-comment/draft', [TeacherReportCardCommentController::class, 'draft'])
        ->name('api.teacher.report-card-comment.draft');
    Route::put('/students/{student}/report-card-comment', [TeacherReportCardCommentController::class, 'update'])
        ->name('api.teacher.report-card-comment.update');
});

/*
|--------------------------------------------------------------------------
| School administrator
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'tenant', 'role:admin', 'password.rotated'])->prefix('admin')->group(function () {
    // Always available so an expired school can still renew.
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('api.admin.dashboard');
    Route::get('/billing', [BillingController::class, 'index'])->name('api.admin.billing');
    Route::post('/billing/upgrade', [BillingController::class, 'upgrade'])->name('api.admin.billing.upgrade');
    Route::post('/billing/renew', [BillingController::class, 'renew'])->name('api.admin.billing.renew');
    Route::get('/settings', [SettingsController::class, 'show'])->name('api.admin.settings');
    Route::get('/audit-logs', [AdminAuditLogController::class, 'index'])->name('api.admin.audit-logs');
    Route::get('/payments/{payment}/receipt', [ReceiptController::class, 'show'])->name('api.admin.payments.receipt');
    Route::patch('/settings', [SettingsController::class, 'update'])->name('api.admin.settings.update');

    // Academic management — subscription enforced.
    Route::middleware('subscription')->group(function () {
        Route::apiResource('academic-years', AcademicYearController::class)->only(['index', 'store']);
        Route::put('/academic-years/{academicYear}', [AcademicYearController::class, 'update'])
            ->name('api.admin.academic-years.update');
        Route::delete('/academic-years/{academicYear}', [AcademicYearController::class, 'destroy'])
            ->name('api.admin.academic-years.destroy');
        Route::post('/academic-years/{academicYear}/activate', [AcademicYearController::class, 'activate'])
            ->name('api.admin.academic-years.activate');

        Route::apiResource('semesters', AdminSemesterController::class)->only(['index', 'store', 'destroy']);
        Route::post('/semesters/{semester}/activate', [AdminSemesterController::class, 'activate'])
            ->name('api.admin.semesters.activate');

        Route::apiResource('grade-components', AdminGradeComponentController::class)->only(['index', 'store']);
        Route::put('/grade-components/{component}', [AdminGradeComponentController::class, 'update'])
            ->name('api.admin.grade-components.update');
        Route::delete('/grade-components/{component}', [AdminGradeComponentController::class, 'destroy'])
            ->name('api.admin.grade-components.destroy');

        Route::apiResource('exams', AdminExamController::class)->only(['index', 'store', 'destroy']);
        Route::get('/exams/ranking', [AdminExamController::class, 'ranking'])
            ->name('api.admin.exams.ranking');

        /*
        | Dry run first. Writes nothing; reports which column maps to which
        | field and which class each pupil resolves to, before a single account
        | is created.
        */
        Route::post('/import/preview', [AdminImportController::class, 'preview'])
            ->name('api.admin.import.preview');

        Route::post('/import', [AdminImportController::class, 'store'])
            ->name('api.admin.import');

        Route::apiResource('classes', SchoolClassController::class)->only(['index', 'store']);
        Route::apiResource('subjects', SubjectController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::apiResource('teachers', AdminTeacherController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::apiResource('students', AdminStudentController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::apiResource('teaching-assignments', TeachingAssignmentController::class)
            ->only(['index', 'store', 'destroy']);

        Route::get('/timetable', [AdminTimetableController::class, 'index'])->name('api.admin.timetable');
        Route::post('/timetable/entries', [AdminTimetableController::class, 'store'])->name('api.admin.timetable.store');
        Route::put('/timetable/entries/{timetableEntry}', [AdminTimetableController::class, 'update'])
            ->name('api.admin.timetable.update');
        Route::delete('/timetable/entries/{timetableEntry}', [AdminTimetableController::class, 'destroy'])
            ->name('api.admin.timetable.destroy');

        Route::get('/attendance', [AdminAttendanceController::class, 'index'])->name('api.admin.attendance');
        Route::post('/attendance', [AdminAttendanceController::class, 'store'])->name('api.admin.attendance.store');

        Route::get('/requests', [AdminRequestController::class, 'index'])->name('api.admin.requests');
        Route::get('/requests/triage', [AdminRequestController::class, 'triageSummary'])
            ->name('api.admin.requests.triage');
        Route::post('/requests/{documentRequest}/status', [AdminRequestController::class, 'status'])
            ->name('api.admin.requests.status');
        Route::post('/requests/{documentRequest}/generate-document', [AdminRequestController::class, 'generateDocument'])
            ->name('api.admin.requests.generate-document');

        Route::post('/announcements', [AdminAnnouncementController::class, 'store'])
            ->name('api.admin.announcements.store');

        /*
        | Drafting is separate from publishing on purpose: it persists nothing
        | and cannot reach the fan-out. See AnnouncementDraftController.
        */
        Route::post('/announcements/draft', [AdminAnnouncementDraftController::class, 'store'])
            ->name('api.admin.announcements.draft');

        /*
        | Analytics and the pastoral register. Read-only aggregation; the
        | services scope by caller, so the same controller serves teachers too.
        */
        Route::get('/analytics', [AnalyticsController::class, 'overview'])->name('api.admin.analytics');
        Route::get('/analytics/at-risk', [AnalyticsController::class, 'register'])
            ->name('api.admin.analytics.at-risk');
        Route::get('/analytics/students/{student}', [AnalyticsController::class, 'student'])
            ->name('api.admin.analytics.student');

        Route::get('/events', [AdminEventController::class, 'index'])->name('api.admin.events');
        Route::post('/events', [AdminEventController::class, 'store'])->name('api.admin.events.store');
        Route::get('/events/{event}', [AdminEventController::class, 'show'])->name('api.admin.events.show');
        Route::put('/events/{event}', [AdminEventController::class, 'update'])->name('api.admin.events.update');
        Route::post('/events/{event}/publish', [AdminEventController::class, 'publish'])
            ->name('api.admin.events.publish');
        Route::post('/events/{event}/unpublish', [AdminEventController::class, 'unpublish'])
            ->name('api.admin.events.unpublish');
        Route::delete('/events/{event}', [AdminEventController::class, 'destroy'])
            ->name('api.admin.events.destroy');

        // Report cards & transcripts as PDFs.
        Route::get('/students/{student}/report-card', [AdminReportCardController::class, 'student'])
            ->middleware('feature:report_cards')
            ->name('api.admin.students.report-card');
        Route::get('/students/{student}/transcript', [AdminReportCardController::class, 'transcript'])
            ->name('api.admin.students.transcript');
        Route::get('/students/{student}/documents', [AdminReportCardController::class, 'issued'])
            ->name('api.admin.students.documents');
        Route::post('/classes/{schoolClass}/report-cards', [AdminReportCardController::class, 'class'])
            ->middleware('feature:report_cards')
            ->name('api.admin.classes.report-cards');
    });
});

/*
|--------------------------------------------------------------------------
| Platform super administrator
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'tenant', 'role:super_admin', 'password.rotated'])->prefix('super-admin')->group(function () {
    Route::get('/dashboard', [SuperAdminDashboardController::class, 'index'])->name('api.super-admin.dashboard');

    Route::apiResource('schools', SuperAdminSchoolController::class)->only(['index', 'store', 'show', 'update']);
    Route::post('/schools/{school}/status', [SuperAdminSchoolController::class, 'status'])
        ->name('api.super-admin.schools.status');
    Route::get('/schools/{school}/users', [SuperAdminSchoolController::class, 'users'])
        ->name('api.super-admin.schools.users');

    Route::apiResource('plans', SuperAdminPlanController::class)->only(['index', 'store', 'update']);

    Route::get('/subscriptions', [SuperAdminSubscriptionController::class, 'index'])
        ->name('api.super-admin.subscriptions');
    Route::get('/payments', [SuperAdminPaymentController::class, 'index'])
        ->name('api.super-admin.payments');
    Route::get('/payments/{payment}/receipt', [ReceiptController::class, 'show'])
        ->name('api.super-admin.payments.receipt');

    Route::get('/audit-logs', [SuperAdminAuditLogController::class, 'index'])
        ->name('api.super-admin.audit-logs');
});
