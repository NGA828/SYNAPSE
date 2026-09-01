<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Homework set by a teacher for one of their teaching assignments.
 *
 * Deliberately named `homework_assignments` — the existing
 * `teaching_assignments` table already owns the word "assignment" in this
 * codebase (a teacher's subject/class allocation), and the teacher UI already
 * has a "My Assignments" page for it. Keeping the names apart avoids an
 * avoidable collision in routes, services and navigation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homework_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->nullable()->constrained('semesters')->nullOnDelete();

            $table->string('title');
            $table->text('instructions')->nullable();
            $table->unsignedInteger('max_score')->default(20);
            $table->timestamp('due_at');
            $table->boolean('is_published')->default(false)->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            // A teacher should not be able to set the same homework twice for
            // one class/subject/term.
            $table->unique(
                ['school_id', 'class_id', 'subject_id', 'academic_year_id', 'title'],
                'homework_unique_per_class_subject_year',
            );

            $table->index(['teacher_id', 'is_published']);
            $table->index(['class_id', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homework_assignments');
    }
};
