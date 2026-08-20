<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTeacherRequest;
use App\Models\Teacher;
use App\Services\RegistrationService;
use Illuminate\Http\JsonResponse;

class TeacherController extends Controller
{
    public function __construct(
        private readonly RegistrationService $registrationService,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Teacher::query()->with('user')->get()->map(fn (Teacher $teacher) => [
                'id' => $teacher->id,
                'name' => $teacher->user?->name,
                'email' => $teacher->user?->email,
                'staff_no' => $teacher->staff_no,
            ]),
        ]);
    }

    public function store(StoreTeacherRequest $request): JsonResponse
    {
        $teacher = $this->registrationService->registerTeacher(
            $request->user()->school,
            $request->validated(),
            $request->user(),
        );

        return response()->json([
            'data' => [
                'id' => $teacher->id,
                'name' => $teacher->user?->name,
                'email' => $teacher->user?->email,
                'staff_no' => $teacher->staff_no,
            ],
        ], 201);
    }
}
