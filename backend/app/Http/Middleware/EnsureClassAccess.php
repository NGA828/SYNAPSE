<?php

namespace App\Http\Middleware;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforce that the authenticated teacher is assigned to teach at least one
 * subject in the requested class for the current academic year — the
 * prerequisite for managing whole-class activities like attendance.
 */
class EnsureClassAccess
{
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
        $classId = $class instanceof SchoolClass ? $class->id : $class;

        $assigned = $teacher->teachingAssignments()
            ->where('class_id', $classId)
            ->where('academic_year_id', $year->id)
            ->exists();

        abort_unless($assigned, 403, 'You are not assigned to teach this class.');

        return $next($request);
    }
}
