<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Collection;

class AttendanceService
{
    /**
     * The class roster with each student's status for a given date.
     *
     * @return array{class: SchoolClass, academic_year: ?AcademicYear, date: string, students: Collection}
     */
    public function roster(SchoolClass $class, string $date, ?AcademicYear $year = null): array
    {
        $year ??= AcademicYear::current();

        abort_unless($year, 409, 'No active academic year is configured.');

        $attendance = Attendance::query()
            ->where('class_id', $class->id)
            ->where('academic_year_id', $year->id)
            ->where('date', $date)
            ->get()
            ->keyBy('student_id');

        $students = $class->students()
            ->with('user')
            ->wherePivot('academic_year_id', $year->id)
            ->get()
            ->map(function (Student $student) use ($attendance) {
                $row = $attendance->get($student->id);

                return [
                    'id' => $student->id,
                    'name' => $student->user?->name,
                    'matricule' => $student->matricule,
                    'status' => $row?->status ?? null,
                    'remark' => $row?->remark,
                ];
            })
            ->sortBy('name')
            ->values();

        return [
            'class' => $class,
            'academic_year' => $year,
            'date' => $date,
            'students' => $students,
        ];
    }

    /**
     * Save attendance for a class + date (teacher).
     *
     * @param  array<int, array{student_id: int, status: string, remark?: ?string}>  $records
     * @return array{class: SchoolClass, academic_year: ?AcademicYear, date: string, students: Collection}
     */
    public function save(
        SchoolClass $class,
        string $date,
        array $records,
        Teacher $teacher,
        ?AcademicYear $year = null,
    ): array {
        $year ??= AcademicYear::current();

        abort_unless($year, 409, 'No active academic year is configured.');

        $this->assertCanManageClass($teacher->user, $class, $year);

        $enrolledIds = $class->students()
            ->wherePivot('academic_year_id', $year->id)
            ->pluck('students.id');

        foreach ($records as $record) {
            if (! in_array((int) $record['student_id'], $enrolledIds->all(), true)) {
                continue;
            }

            if (! in_array($record['status'], Attendance::STATUSES, true)) {
                continue;
            }

            Attendance::updateOrCreate(
                [
                    'school_id' => $class->school_id,
                    'class_id' => $class->id,
                    'student_id' => $record['student_id'],
                    'academic_year_id' => $year->id,
                    'date' => $date,
                ],
                [
                    'teacher_id' => $teacher->id,
                    'status' => $record['status'],
                    'remark' => $record['remark'] ?? null,
                ],
            );
        }

        return $this->roster($class, $date, $year);
    }

    /**
     * Save attendance for a class + date (administrator).
     *
     * @param  array<int, array{student_id: int, status: string, remark?: ?string}>  $records
     * @return array<class: SchoolClass, academic_year: ?AcademicYear, date: string, students: Collection>
     */
    public function saveAsAdmin(
        SchoolClass $class,
        string $date,
        array $records,
        ?AcademicYear $year = null,
    ): array {
        $year ??= AcademicYear::current();

        abort_unless($year, 409, 'No active academic year is configured.');

        $enrolledIds = $class->students()
            ->wherePivot('academic_year_id', $year->id)
            ->pluck('students.id');

        foreach ($records as $record) {
            if (! in_array((int) $record['student_id'], $enrolledIds->all(), true)) {
                continue;
            }

            if (! in_array($record['status'], Attendance::STATUSES, true)) {
                continue;
            }

            Attendance::updateOrCreate(
                [
                    'school_id' => $class->school_id,
                    'class_id' => $class->id,
                    'student_id' => $record['student_id'],
                    'academic_year_id' => $year->id,
                    'date' => $date,
                ],
                [
                    'status' => $record['status'],
                    'remark' => $record['remark'] ?? null,
                ],
            );
        }

        return $this->roster($class, $date, $year);
    }

    /**
     * A student's attendance summary for the current year.
     *
     * @return array{academic_year: ?AcademicYear, summary: array<string, mixed>, recent: Collection}
     */
    public function studentSummary(Student $student, ?AcademicYear $year = null): array
    {
        $year ??= AcademicYear::current();

        $query = $student->attendances()->with('schoolClass');

        if ($year) {
            $query->where('academic_year_id', $year->id);
        }

        $records = $query->get();

        $total = $records->count();
        $present = $records->where('status', Attendance::PRESENT)->count()
            + $records->where('status', Attendance::LATE)->count();
        $absent = $records->where('status', Attendance::ABSENT)->count();
        $excused = $records->where('status', Attendance::EXCUSED)->count();
        $late = $records->where('status', Attendance::LATE)->count();

        $percentage = $total > 0 ? round(($present / $total) * 100, 1) : null;

        $recent = $records
            ->sortByDesc('date')
            ->take(14)
            ->map(fn (Attendance $row) => [
                'id' => $row->id,
                'date' => $row->date->toDateString(),
                'status' => $row->status,
                'class' => $row->schoolClass?->name,
            ])
            ->values();

        return [
            'academic_year' => $year,
            'summary' => [
                'total' => $total,
                'present' => $records->where('status', Attendance::PRESENT)->count(),
                'absent' => $absent,
                'late' => $late,
                'excused' => $excused,
                'percentage' => $percentage,
            ],
            'recent' => $recent,
        ];
    }

    /**
     * Guard: the teacher must hold a teaching assignment in this class for
     * the year (any subject).
     */
    private function assertCanManageClass(User $user, SchoolClass $class, AcademicYear $year): void
    {
        $teacher = $user->teacher;

        abort_unless($teacher, 403, 'No teacher profile is attached to this account.');

        $assigned = $teacher->teachingAssignments()
            ->where('class_id', $class->id)
            ->where('academic_year_id', $year->id)
            ->exists();

        abort_unless($assigned, 403, 'You are not assigned to teach this class.');
    }
}
