<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStudentRequest;
use App\Models\AcademicYear;
use App\Models\Student;
use App\Services\RegistrationService;
use Illuminate\Http\JsonResponse;

class StudentController extends Controller
{
    public function __construct(
        private readonly RegistrationService $registrationService,
    ) {}

    public function index(): JsonResponse
    {
        $year = AcademicYear::current();

        $students = Student::query()
            ->with(['user', 'enrollments.schoolClass', 'enrollments.academicYear'])
            ->get()
            ->map(function (Student $student) use ($year) {
                $enrollment = $student->enrollments
                    ->firstWhere('academic_year_id', $year?->id);

                return [
                    'id' => $student->id,
                    'name' => $student->user?->name,
                    'email' => $student->user?->email,
                    'matricule' => $student->matricule,
                    'class' => $enrollment?->schoolClass,
                    'academic_year' => $enrollment?->academicYear,
                ];
            });

        return response()->json(['data' => $students]);
    }

    public function store(StoreStudentRequest $request): JsonResponse
    {
        $student = $this->registrationService->registerStudent(
            $request->user()->school,
            $request->validated(),
            $request->user(),
        );

        return response()->json([
            'data' => [
                'id' => $student->id,
                'name' => $student->user?->name,
                'email' => $student->user?->email,
                'matricule' => $student->matricule,
            ],
        ], 201);
    }
}
