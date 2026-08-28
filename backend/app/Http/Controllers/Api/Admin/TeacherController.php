<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Concerns\HandlesPagination;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTeacherRequest;
use App\Http\Requests\Admin\UpdateTeacherRequest;
use App\Http\Resources\TeacherResource;
use App\Models\Teacher;
use App\Services\RegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TeacherController extends Controller
{
    use HandlesPagination;

    public function __construct(
        private readonly RegistrationService $registrationService,
    ) {}

    /**
     * Paginated, searchable teacher directory for the current school.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $teachers = $this->paginated(
            Teacher::query()->with('user')->withCount('teachingAssignments'),
            $request,
            searchable: ['staff_no', 'user.name', 'user.email'],
            sortable: ['id', 'staff_no', 'created_at'],
        );

        return TeacherResource::collection($teachers);
    }

    public function store(StoreTeacherRequest $request): JsonResponse
    {
        $teacher = $this->registrationService->registerTeacher(
            $request->user()->school,
            $request->validated(),
            $request->user(),
        );

        return TeacherResource::make($teacher->load('user'))->response()->setStatusCode(201);
    }

    public function update(UpdateTeacherRequest $request, \App\Models\Teacher $teacher): JsonResponse
    {
        $teacher = $this->registrationService->updateTeacher($teacher, $request->validated(), $request->user());

        return response()->json(['data' => TeacherResource::make($teacher->load('user'))]);
    }

    public function destroy(\App\Models\Teacher $teacher): JsonResponse
    {
        $this->registrationService->deleteTeacher($teacher, request()->user());

        return response()->json(['message' => 'Teacher removed.']);
    }
}
