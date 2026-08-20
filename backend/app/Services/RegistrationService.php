<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\School;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;

class RegistrationService
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly AuditService $audit,
    ) {}

    /**
     * Create a teacher account + profile within a school.
     *
     * @param  array{name: string, email: string, password: string, staff_no?: ?string}  $data
     */
    public function registerTeacher(School $school, array $data, ?User $actor = null): Teacher
    {
        $this->subscriptions->assertCanCreate($school, 'teachers');

        $user = User::create([
            'school_id' => $school->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => User::ROLE_TEACHER,
        ]);

        $teacher = Teacher::create([
            'school_id' => $school->id,
            'user_id' => $user->id,
            'staff_no' => $data['staff_no'] ?? null,
        ]);

        $this->audit->log($school, $actor, 'teacher.created', Teacher::class, $teacher->id);

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

        $user = User::create([
            'school_id' => $school->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => User::ROLE_STUDENT,
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

        return $student->load('user');
    }
}
