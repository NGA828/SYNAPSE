<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Services\TeacherTimetableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimetableController extends Controller
{
    public function __construct(
        private readonly TeacherTimetableService $timetableService,
    ) {}

    /**
     * The signed-in teacher's weekly teaching schedule.
     */
    public function index(Request $request): JsonResponse
    {
        $teacher = $request->user()->teacher;

        abort_unless($teacher, 403, 'No teacher profile is attached to this account.');

        $year = null;

        if ($request->filled('academic_year_id')) {
            $year = AcademicYear::query()
                ->where('school_id', $teacher->school_id)
                ->findOrFail($request->integer('academic_year_id'));
        }

        return response()->json($this->timetableService->forTeacher($teacher, $year));
    }
}
