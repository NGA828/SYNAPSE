<?php

use App\Http\Controllers\Api\Admin\AcademicYearController;
use App\Http\Controllers\Api\Admin\AnnouncementController as AdminAnnouncementController;
use App\Http\Controllers\Api\Admin\AttendanceController as AdminAttendanceController;
use App\Http\Controllers\Api\Admin\BillingController;
use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\ExamController as AdminExamController;
use App\Http\Controllers\Api\Admin\GradeComponentController as AdminGradeComponentController;
use App\Http\Controllers\Api\Admin\ImportController as AdminImportController;
use App\Http\Controllers\Api\Admin\RequestController as AdminRequestController;
use App\Http\Controllers\Api\Admin\SchoolClassController;
use App\Http\Controllers\Api\Admin\SemesterController as AdminSemesterController;
use App\Http\Controllers\Api\Admin\SettingsController;
use App\Http\Controllers\Api\Admin\StudentController as AdminStudentController;
use App\Http\Controllers\Api\Admin\SubjectController;
use App\Http\Controllers\Api\Admin\TeacherController as AdminTeacherController;
use App\Http\Controllers\Api\Admin\TeachingAssignmentController;
use App\Http\Controllers\Api\Admin\TimetableController as AdminTimetableController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\PublicSchoolController;
use App\Http\Controllers\Api\Student\AcademicController as StudentAcademicController;
use App\Http\Controllers\Api\Student\AttendanceController as StudentAttendanceController;
use App\Http\Controllers\Api\Student\DocumentController as StudentDocumentController;
use App\Http\Controllers\Api\Student\RequestController as StudentRequestController;
use App\Http\Controllers\Api\StudentController;
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
use App\Http\Controllers\Api\TenantController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/
Route::post('/login', [AuthController::class, 'login'])->name('api.login');
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

    Route::get('/notifications', [NotificationController::class, 'index'])->name('api.notifications');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('api.notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('api.notifications.read');

    Route::get('/announcements', [AnnouncementController::class, 'index'])->name('api.announcements');
});

/*
|--------------------------------------------------------------------------
| Student — tenant + subscription enforced
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'tenant', 'role:student', 'subscription'])->group(function () {
    Route::get('/student/dashboard', [StudentController::class, 'dashboard'])
        ->name('api.student.dashboard');
    Route::get('/student/grades', [StudentAcademicController::class, 'grades'])
        ->name('api.student.grades');
    Route::get('/student/report-card', [StudentAcademicController::class, 'reportCard'])
        ->middleware('feature:report_cards')
        ->name('api.student.report-card');
    Route::get('/student/timetable', [StudentAcademicController::class, 'timetable'])
        ->name('api.student.timetable');
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
    Route::get('/student/exams', [StudentAcademicController::class, 'exams'])
        ->name('api.student.exams');
});

/*
|--------------------------------------------------------------------------
| Teacher — tenant + subscription enforced, assignment scoped
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'tenant', 'role:teacher', 'subscription'])->prefix('teacher')->group(function () {
    Route::get('/dashboard', [TeacherDashboardController::class, 'index'])
        ->name('api.teacher.dashboard');
    Route::get('/assignments', [AssignmentController::class, 'index'])
        ->name('api.teacher.assignments');

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
});

/*
|--------------------------------------------------------------------------
| School administrator
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'tenant', 'role:admin'])->prefix('admin')->group(function () {
    // Always available so an expired school can still renew.
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('api.admin.dashboard');
    Route::get('/billing', [BillingController::class, 'index'])->name('api.admin.billing');
    Route::post('/billing/upgrade', [BillingController::class, 'upgrade'])->name('api.admin.billing.upgrade');
    Route::post('/billing/renew', [BillingController::class, 'renew'])->name('api.admin.billing.renew');
    Route::get('/settings', [SettingsController::class, 'show'])->name('api.admin.settings');
    Route::patch('/settings', [SettingsController::class, 'update'])->name('api.admin.settings.update');

    // Academic management — subscription enforced.
    Route::middleware('subscription')->group(function () {
        Route::apiResource('academic-years', AcademicYearController::class)->only(['index', 'store']);
        Route::post('/academic-years/{academicYear}/activate', [AcademicYearController::class, 'activate'])
            ->name('api.admin.academic-years.activate');

        Route::apiResource('semesters', AdminSemesterController::class)->only(['index', 'store', 'destroy']);
        Route::post('/semesters/{semester}/activate', [AdminSemesterController::class, 'activate'])
            ->name('api.admin.semesters.activate');

        Route::apiResource('grade-components', AdminGradeComponentController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::apiResource('exams', AdminExamController::class)->only(['index', 'store', 'destroy']);
        Route::get('/exams/ranking', [AdminExamController::class, 'ranking'])
            ->name('api.admin.exams.ranking');

        Route::post('/import', [AdminImportController::class, 'store'])
            ->name('api.admin.import');

        Route::apiResource('classes', SchoolClassController::class)->only(['index', 'store']);
        Route::apiResource('subjects', SubjectController::class)->only(['index', 'store']);
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
        Route::post('/requests/{documentRequest}/status', [AdminRequestController::class, 'status'])
            ->name('api.admin.requests.status');
        Route::post('/requests/{documentRequest}/generate-document', [AdminRequestController::class, 'generateDocument'])
            ->name('api.admin.requests.generate-document');

        Route::post('/announcements', [AdminAnnouncementController::class, 'store'])
            ->name('api.admin.announcements.store');
    });
});

/*
|--------------------------------------------------------------------------
| Platform super administrator
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'tenant', 'role:super_admin'])->prefix('super-admin')->group(function () {
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
});
