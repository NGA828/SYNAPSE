<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\StoreAttendanceRequest;
use App\Models\SchoolClass;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
    ) {}

    /**
     * The class roster with statuses for a date.
     *
     * Gated by the `class.access` middleware (route level).
     */
    public function index(Request $request, SchoolClass $schoolClass): JsonResponse
    {
        $date = $request->query('date', now()->toDateString());

        return response()->json(
            $this->attendanceService->roster($schoolClass, $date),
        );
    }

    /**
     * Save attendance for a class + date.
     */
    public function store(StoreAttendanceRequest $request, SchoolClass $schoolClass): JsonResponse
    {
        $data = $request->validated();

        $teacher = $request->user()->teacher;

        abort_unless($teacher, 403, 'No teacher profile is attached to this account.');

        return response()->json(
            $this->attendanceService->save($schoolClass, $data['date'], $data['records'], $teacher),
        );
    }
}
