<?php

namespace App\Http\Controllers\Api\Admin;

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

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $class = SchoolClass::findOrFail($validated['class_id']);

        return response()->json(
            $this->attendanceService->roster($class, $validated['date'] ?? now()->toDateString()),
        );
    }

    public function store(StoreAttendanceRequest $request): JsonResponse
    {
        $data = $request->validated();

        $classId = $request->validate(['class_id' => ['required', 'integer', 'exists:classes,id']])['class_id'];
        $class = SchoolClass::findOrFail($classId);

        return response()->json(
            $this->attendanceService->saveAsAdmin($class, $data['date'], $data['records']),
        );
    }
}
