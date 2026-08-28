<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\TimetableEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeacherTimetableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    private function teacher(): Teacher
    {
        return User::where('email', 'teacher@synapse.test')->firstOrFail()->teacher;
    }

    public function test_a_teacher_only_sees_slots_they_are_assigned_to(): void
    {
        $teacher = $this->teacher();
        $year = AcademicYear::current();

        $assignment = $teacher->teachingAssignments()->where('academic_year_id', $year->id)->firstOrFail();

        // A slot for a class the teacher teaches, but a subject they do not.
        $otherSubject = TeachingAssignment::query()
            ->where('school_id', $teacher->school_id)
            ->where('teacher_id', '!=', $teacher->id)
            ->value('subject_id');

        if ($otherSubject && $otherSubject !== $assignment->subject_id) {
            TimetableEntry::create([
                'school_id' => $teacher->school_id,
                'class_id' => $assignment->class_id,
                'academic_year_id' => $year->id,
                'subject_id' => $otherSubject,
                'day' => 5,
                'start' => '15:00',
                'end' => '16:00',
            ]);
        }

        Sanctum::actingAs($teacher->user);

        $response = $this->getJson('/api/teacher/timetable')->assertOk();

        $entries = collect($response->json('entries'));

        $this->assertNotEmpty($entries);

        $entries->each(function (array $entry) use ($teacher, $year) {
            $this->assertTrue(
                $teacher->teachingAssignments()
                    ->where('academic_year_id', $year->id)
                    ->where('class_id', $entry['class']['id'])
                    ->where('subject_id', $entry['subject']['id'])
                    ->exists(),
                'The schedule leaked a lesson the teacher is not assigned to.',
            );
        });
    }

    public function test_the_summary_counts_lessons_classes_and_contact_hours(): void
    {
        Sanctum::actingAs($this->teacher()->user);

        $response = $this->getJson('/api/teacher/timetable')->assertOk();

        $entries = collect($response->json('entries'));
        $summary = $response->json('summary');

        $this->assertSame($entries->count(), $summary['lessons']);
        $this->assertSame($entries->pluck('class.id')->unique()->count(), $summary['classes']);
        $this->assertSame($entries->pluck('subject.id')->unique()->count(), $summary['subjects']);
        $this->assertSame((int) $entries->sum('duration_minutes'), $summary['minutes_per_week']);
    }

    public function test_overlapping_lessons_are_reported_as_conflicts(): void
    {
        $teacher = $this->teacher();
        $year = AcademicYear::current();

        $assignments = $teacher->teachingAssignments()->where('academic_year_id', $year->id)->get();

        $first = $assignments->first();

        // Give the teacher a second class, then double-book them.
        $secondClass = \App\Models\SchoolClass::query()
            ->where('school_id', $teacher->school_id)
            ->where('id', '!=', $first->class_id)
            ->firstOrFail();

        TeachingAssignment::firstOrCreate([
            'school_id' => $teacher->school_id,
            'teacher_id' => $teacher->id,
            'subject_id' => $first->subject_id,
            'class_id' => $secondClass->id,
            'academic_year_id' => $year->id,
        ]);

        foreach ([$first->class_id, $secondClass->id] as $classId) {
            TimetableEntry::create([
                'school_id' => $teacher->school_id,
                'class_id' => $classId,
                'academic_year_id' => $year->id,
                'subject_id' => $first->subject_id,
                'day' => 4,
                'start' => '11:00',
                'end' => '12:00',
            ]);
        }

        Sanctum::actingAs($teacher->user);

        $conflicts = $this->getJson('/api/teacher/timetable')->assertOk()->json('conflicts');

        $this->assertCount(1, $conflicts);
        $this->assertSame(4, $conflicts[0]['day']);
        $this->assertSame('11:00', $conflicts[0]['start']);
        $this->assertCount(2, $conflicts[0]['entries']);
    }

    public function test_students_cannot_read_the_teacher_schedule(): void
    {
        Sanctum::actingAs(User::where('email', 'student@synapse.test')->firstOrFail());

        $this->getJson('/api/teacher/timetable')->assertForbidden();
    }
}
