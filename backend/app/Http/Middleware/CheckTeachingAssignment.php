<?php

namespace App\Http\Middleware;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTeachingAssignment
{
    /**
     * Enforce that the authenticated teacher has a TeachingAssignment for the
     * requested class + subject in the current academic year. Teacher access
     * is strictly determined by TeachingAssignment records — never by the
     * frontend routing alone.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! ($user instanceof User) || ! $user->isTeacher()) {
            abort(403, 'Only teachers can access this resource.');
        }

        $teacher = $user->teacher;

        abort_unless($teacher, 403, 'No teacher profile is attached to this account.');

        $year = AcademicYear::current();

        abort_unless($year, 409, 'No active academic year is configured.');

        $class = $request->route('schoolClass');
        $subject = $request->route('subject');

        $classId = $class instanceof SchoolClass ? $class->id : $class;
        $subjectId = $subject instanceof Subject ? $subject->id : $subject;

        $assigned = $teacher->teachingAssignments()
            ->where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->where('academic_year_id', $year->id)
            ->exists();

        abort_unless($assigned, 403, 'You are not assigned to teach this subject in this class.');

        return $next($request);
    }
}
