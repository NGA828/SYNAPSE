<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\School;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Notifications\TemporaryCredentialsNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegistrationService
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly AuditService $audit,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Generate a readable one-time password (e.g. "Ax7K-92Qm").
     */
    public function temporaryPassword(): string
    {
        return Str::upper(Str::random(2)).Str::random(3).'-'.random_int(10, 99).Str::random(3);
    }

    /**
     * Create a teacher account + profile within a school.
     *
     * @param  array{name: string, email: string, password: string, staff_no?: ?string}  $data
     */
    public function registerTeacher(School $school, array $data, ?User $actor = null): Teacher
    {
        $this->subscriptions->assertCanCreate($school, 'teachers');

        $temporary = $data['password'] ?? $this->temporaryPassword();

        $user = User::create([
            'school_id' => $school->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $temporary,
            'role' => User::ROLE_TEACHER,
            'must_change_password' => true,
            'notify_sms' => ! empty($data['phone']),
        ]);

        $teacher = Teacher::create([
            'school_id' => $school->id,
            'user_id' => $user->id,
            'staff_no' => $data['staff_no'] ?? null,
        ]);

        $this->audit->log($school, $actor, 'teacher.created', Teacher::class, $teacher->id);

        $this->notifications->notify(
            $user,
            new TemporaryCredentialsNotification($temporary, $school->name),
        );

        return $teacher->load('user');
    }

    /**
     * Create a student account + profile and enroll them in a class.
     *
     * @param  array{name: string, email: string, password: string, matricule: string, class_id: int, academic_year_id?: ?int}  $data
     */
    public function registerStudent(School $school, array $data, ?User $actor = null): Student
    {
        $this->subscriptions->assertCanCreate($school, 'students');

        $temporary = $data['password'] ?? $this->temporaryPassword();

        $user = User::create([
            'school_id' => $school->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $temporary,
            'role' => User::ROLE_STUDENT,
            'must_change_password' => true,
            'notify_sms' => ! empty($data['phone']),
        ]);

        $student = Student::create([
            'school_id' => $school->id,
            'user_id' => $user->id,
            'matricule' => $data['matricule'],
        ]);

        $yearId = $data['academic_year_id'] ?? AcademicYear::current()?->id;

        if ($yearId) {
            Enrollment::create([
                'school_id' => $school->id,
                'student_id' => $student->id,
                'class_id' => $data['class_id'],
                'academic_year_id' => $yearId,
            ]);
        }

        $this->audit->log($school, $actor, 'student.created', Student::class, $student->id);

        $this->notifications->notify(
            $user,
            new TemporaryCredentialsNotification($temporary, $school->name),
        );

        return $student->load('user');
    }

    public function updateTeacher(Teacher $teacher, array $data, ?User $actor = null): Teacher
    {
        return DB::transaction(function () use ($teacher, $data, $actor) {
            $teacher->user->update([
                'name' => $data['name'],
                'email' => $data['email'],
                ...array_key_exists('phone', $data) ? ['phone' => $data['phone']] : [],
                ...array_key_exists('password', $data) && $data['password']
                    ? ['password' => $data['password'], 'must_change_password' => true]
                    : [],
            ]);
            $teacher->update(['staff_no' => $data['staff_no'] ?? null]);
            $this->audit->log($teacher->school, $actor, 'teacher.updated', Teacher::class, $teacher->id);

            return $teacher->fresh('user');
        });
    }

    public function updateStudent(Student $student, array $data, ?User $actor = null): Student
    {
        return DB::transaction(function () use ($student, $data, $actor) {
            $student->user->update([
                'name' => $data['name'],
                'email' => $data['email'],
                ...array_key_exists('phone', $data) ? ['phone' => $data['phone']] : [],
                ...array_key_exists('password', $data) && $data['password']
                    ? ['password' => $data['password'], 'must_change_password' => true]
                    : [],
            ]);
            $student->update(['matricule' => $data['matricule']]);

            $yearId = $data['academic_year_id'] ?? AcademicYear::current()?->id;
            if ($yearId) {
                Enrollment::updateOrCreate(
                    ['student_id' => $student->id, 'academic_year_id' => $yearId],
                    ['school_id' => $student->school_id, 'class_id' => $data['class_id']],
                );
            }

            $this->audit->log($student->school, $actor, 'student.updated', Student::class, $student->id);

            return $student->fresh('user');
        });
    }

    public function deleteTeacher(Teacher $teacher, ?User $actor = null): void
    {
        DB::transaction(function () use ($teacher, $actor) {
            $this->audit->log($teacher->school, $actor, 'teacher.deleted', Teacher::class, $teacher->id);
            $teacher->user->delete();
        });
    }

    public function deleteStudent(Student $student, ?User $actor = null): void
    {
        DB::transaction(function () use ($student, $actor) {
            $this->audit->log($student->school, $actor, 'student.deleted', Student::class, $student->id);
            $student->user->delete();
        });
    }
}
