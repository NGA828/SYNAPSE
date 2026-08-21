<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TimetableEntry;
use Illuminate\Support\Collection;

class TimetableService
{
    /**
     * Normalised timetable entries for a class + year.
     *
     * @return Collection<int, array{id: int, day: int, start: string, end: string, subject: array{id: int, name: string}}>
     */
    public function entriesFor(SchoolClass $class, ?AcademicYear $year = null): Collection
    {
        $year ??= AcademicYear::current();

        abort_unless($year, 409, 'No active academic year is configured.');

        return TimetableEntry::query()
            ->with('subject')
            ->where('class_id', $class->id)
            ->where('academic_year_id', $year->id)
            ->orderBy('day')
            ->orderBy('start')
            ->get()
            ->map(fn (TimetableEntry $entry) => [
                'id' => $entry->id,
                'day' => (int) $entry->day,
                'start' => substr((string) $entry->start, 0, 5),
                'end' => substr((string) $entry->end, 0, 5),
                'subject' => [
                    'id' => $entry->subject->id,
                    'name' => $entry->subject->name,
                ],
            ]);
    }

    public function create(array $data): TimetableEntry
    {
        $class = SchoolClass::findOrFail($data['class_id']);
        $year = AcademicYear::current();
        abort_unless($year, 409, 'No active academic year is configured.');
        abort_unless($class->school_id === $year->school_id, 422, 'The selected class is invalid.');
        abort_unless(Subject::query()->whereKey($data['subject_id'])->where('school_id', $class->school_id)->exists(), 422, 'The selected subject is invalid.');

        return TimetableEntry::create([
            ...$data,
            'school_id' => $class->school_id,
            'academic_year_id' => $year->id,
        ])->load('subject');
    }

    public function update(TimetableEntry $entry, array $data): TimetableEntry
    {
        $class = SchoolClass::findOrFail($data['class_id']);
        abort_unless($class->school_id === $entry->school_id, 422, 'The selected class is invalid.');
        abort_unless(Subject::query()->whereKey($data['subject_id'])->where('school_id', $entry->school_id)->exists(), 422, 'The selected subject is invalid.');

        $entry->update($data);

        return $entry->fresh('subject');
    }

    public function delete(TimetableEntry $entry): void
    {
        $entry->delete();
    }
}
