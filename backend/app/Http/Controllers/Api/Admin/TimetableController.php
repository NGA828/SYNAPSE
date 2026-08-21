<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTimetableEntryRequest;
use App\Http\Requests\Admin\UpdateTimetableEntryRequest;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\TimetableEntry;
use App\Services\TimetableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimetableController extends Controller
{
    public function __construct(
        private readonly TimetableService $timetableService,
    ) {}

    /**
     * Timetable entries for a class in the current academic year.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'class_id' => ['required', 'integer', 'exists:classes,id'],
        ]);

        $class = SchoolClass::findOrFail($validated['class_id']);
        $year = AcademicYear::current();

        return response()->json([
            'class' => $class,
            'academic_year' => $year,
            'entries' => $this->timetableService->entriesFor($class, $year),
        ]);
    }

    public function store(StoreTimetableEntryRequest $request): JsonResponse
    {
        $entry = $this->timetableService->create($request->validated());

        return response()->json([
            'data' => [
                'id' => $entry->id,
                'day' => (int) $entry->day,
                'start' => substr((string) $entry->start, 0, 5),
                'end' => substr((string) $entry->end, 0, 5),
                'subject' => [
                    'id' => $entry->subject->id,
                    'name' => $entry->subject->name,
                ],
            ],
        ], 201);
    }

    public function destroy(TimetableEntry $timetableEntry): JsonResponse
    {
        $this->timetableService->delete($timetableEntry);

        return response()->json(['message' => 'Timetable entry removed.']);
    }

    public function update(UpdateTimetableEntryRequest $request, TimetableEntry $timetableEntry): JsonResponse
    {
        $entry = $this->timetableService->update($timetableEntry, $request->validated());

        return response()->json(['data' => [
            'id' => $entry->id,
            'day' => (int) $entry->day,
            'start' => substr((string) $entry->start, 0, 5),
            'end' => substr((string) $entry->end, 0, 5),
            'subject' => ['id' => $entry->subject->id, 'name' => $entry->subject->name],
        ]]);
    }
}
