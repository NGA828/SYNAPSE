<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Semester;
use App\Models\Student;
use App\Services\ExamService;
use App\Services\GradeService;
use App\Services\TimetableService;
use App\Services\DocumentService;
use App\Services\TranscriptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AcademicController extends Controller
{
    public function __construct(
        private readonly GradeService $gradeService,
        private readonly TimetableService $timetableService,
        private readonly TranscriptService $transcriptService,
        private readonly ExamService $examService,
        private readonly DocumentService $documents,
    ) {}

    /**
     * The authenticated student's subject grades (optionally per semester).
     */
    public function grades(Request $request): JsonResponse
    {
        $student = $this->student($request);
        $semester = $this->semester($request);

        $result = $this->gradeService->studentGrades($student, null, $semester);

        return response()->json([
            'class' => $result['class'],
            'academic_year' => AcademicYear::current(),
            'semester' => $semester,
            'semesters' => $this->gradeService->semesters(),
            'grades' => $result['grades'],
            'average' => $result['average'],
        ]);
    }

    /**
     * A printable report card with class rank (optionally per semester).
     */
    public function reportCard(Request $request): JsonResponse
    {
        $student = $this->student($request);
        $semester = $this->semester($request);

        return response()->json(
            $this->gradeService->reportCard($student, null, $semester),
        );
    }

    /**
     * Download the report card as a real PDF (also filed under Documents).
     */
    public function reportCardPdf(Request $request): StreamedResponse
    {
        $student = $this->student($request);

        $document = $this->documents->generateReportCard($student, $this->semester($request));

        return $this->documents->download($document);
    }

    /**
     * Download the full transcript as a PDF.
     */
    public function transcriptPdf(Request $request): StreamedResponse
    {
        $student = $this->student($request);

        $document = $this->documents->generateTranscript($student);

        return $this->documents->download($document);
    }

    /**
     * The authenticated student's weekly timetable for their current class.
     */
    public function timetable(Request $request): JsonResponse
    {
        $student = $this->student($request);
        $year = AcademicYear::current();

        $class = $student->enrollments()
            ->where('academic_year_id', $year?->id)
            ->first()?->schoolClass;

        return response()->json([
            'class' => $class,
            'academic_year' => $year,
            'entries' => $class ? $this->timetableService->entriesFor($class, $year) : [],
        ]);
    }

    /**
     * Multi-year academic history (transcript).
     */
    public function transcript(Request $request): JsonResponse
    {
        $student = $this->student($request);

        return response()->json($this->transcriptService->forStudent($student));
    }

    /**
     * Exam timetable for the student's current class.
     */
    public function exams(Request $request): JsonResponse
    {
        $student = $this->student($request);

        return response()->json([
            'class' => $student->enrollments()
                ->where('academic_year_id', AcademicYear::current()?->id)
                ->first()?->schoolClass,
            'academic_year' => AcademicYear::current(),
            'exams' => $this->examService->forStudent($student),
        ]);
    }

    private function student(Request $request): Student
    {
        $student = $request->user()->student;

        abort_unless(
            $student instanceof Student,
            403,
            'No student profile is attached to this account.',
        );

        return $student;
    }

    private function semester(Request $request): ?Semester
    {
        $id = $request->query('semester_id');

        if (! $id) {
            return null;
        }

        return Semester::query()->findOrFail((int) $id);
    }
}
