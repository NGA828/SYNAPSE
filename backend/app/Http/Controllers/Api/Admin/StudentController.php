<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Concerns\HandlesPagination;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStudentRequest;
use App\Http\Requests\Admin\UpdateStudentRequest;
use App\Http\Resources\StudentResource;
use App\Models\Student;
use App\Services\RegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StudentController extends Controller
{
    use HandlesPagination;

    public function __construct(
        private readonly RegistrationService $registrationService,
    ) {}

    /**
     * Paginated, searchable student directory for the current school.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $students = $this->paginated(
            Student::query()->with(['user', 'enrollments.schoolClass', 'enrollments.academicYear']),
            $request,
            searchable: ['matricule', 'user.name', 'user.email'],
            sortable: ['id', 'matricule', 'created_at'],
            defaultSort: '-id',
        );

        return StudentResource::collection($students);
    }

    public function store(StoreStudentRequest $request): JsonResponse
    {
        $student = $this->registrationService->registerStudent(
            $request->user()->school,
            $request->validated(),
            $request->user(),
        );

        return StudentResource::make($student->load(['user', 'enrollments.schoolClass', 'enrollments.academicYear']))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateStudentRequest $request, Student $student): JsonResponse
    {
        $student = $this->registrationService->updateStudent($student, $request->validated(), $request->user());

        return response()->json([
            'data' => StudentResource::make($student->load(['user', 'enrollments.schoolClass', 'enrollments.academicYear'])),
        ]);
    }

    public function destroy(Student $student): JsonResponse
    {
        $this->registrationService->deleteStudent($student, request()->user());

        return response()->json(['message' => 'Student removed.']);
    }
}
