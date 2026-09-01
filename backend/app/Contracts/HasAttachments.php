<?php

namespace App\Contracts;

use App\Models\Student;
use App\Models\Teacher;

/**
 * Implemented by every model that can carry uploaded files.
 *
 * AttachmentService delegates its authorization here rather than switching on
 * concrete classes, so adding a new attachable model (course materials today,
 * announcements or events later) cannot require editing the shared download
 * path — and cannot accidentally inherit homework's rules.
 */
interface HasAttachments
{
    /**
     * Whether this teacher owns the record and may therefore read any file on
     * it, including other people's private uploads.
     */
    public function ownedByTeacher(Teacher $teacher): bool;

    /**
     * Whether this student may read the record's `class`-visibility files.
     *
     * Private files are additionally restricted to their uploader, which
     * AttachmentService enforces before this is consulted.
     */
    public function readableByStudent(Student $student): bool;
}
