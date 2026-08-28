<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentResource;
use App\Jobs\GenerateClassReportCardsJob;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Services\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Report cards as PDFs: one student on demand, or a whole class in the
 * background.
 */
class ReportCardController extends Controller
{
    public function __construct(
        private readonly DocumentService $documents,
    ) {}

    /**
     * Generate and immediately download one student's report card.
     */
    public function student(Request $request, Student $student): StreamedResponse
    {
        $semester = $this->semester($request);

        $document = $this->documents->generateReportCard($student, $semester, $request->user());

        return $this->documents->download($document);
    }

    /**
     * Queue generation for every student in a class.
     */
    public function class(Request $request, SchoolClass $schoolClass): JsonResponse
    {
        $semester = $this->semester($request);

        GenerateClassReportCardsJob::dispatch(
            schoolId: $schoolClass->school_id,
            classId: $schoolClass->id,
            semesterId: $semester?->id,
            actorId: $request->user()->id,
            notifyStudents: $request->boolean('notify', true),
        );

        return response()->json([
            'message' => 'Report cards are being generated. Students will be notified as each one is ready.',
            'class' => $schoolClass->name,
            'semester' => $semester?->name,
        ], 202);
    }

    /**
     * Generate and download a transcript for one student.
     */
    public function transcript(Request $request, Student $student): StreamedResponse
    {
        $document = $this->documents->generateTranscript($student, $request->user());

        return $this->documents->download($document);
    }

    /**
     * Documents already issued to a student (admin view).
     */
    public function issued(Student $student): JsonResponse
    {
        return response()->json([
            'data' => DocumentResource::collection($this->documents->forStudent($student)),
        ]);
    }

    private function semester(Request $request): ?Semester
    {
        $id = $request->query('semester_id');

        return $id ? Semester::query()->findOrFail((int) $id) : null;
    }
}
