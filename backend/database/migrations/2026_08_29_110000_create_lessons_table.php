<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Course materials: a lesson written by a teacher for one of their classes,
 * with any number of attached files (slides, notes, worksheets).
 *
 * Grouped by a free-text `topic` rather than a separate topics table — a
 * school's syllabus headings vary too much to normalise usefully, and a string
 * keeps the teacher's own wording on screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->nullable()->constrained('semesters')->nullOnDelete();

            $table->string('title');
            $table->string('topic')->nullable()->index();
            $table->text('summary')->nullable();
            $table->longText('body')->nullable();

            // Reading time is a convenience estimate shown on the student card.
            $table->unsignedInteger('minutes')->nullable();

            $table->unsignedInteger('sequence')->default(0);
            $table->boolean('is_published')->default(false)->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['school_id', 'class_id', 'subject_id', 'academic_year_id', 'title'],
                'lessons_unique_per_class_subject_year',
            );

            $table->index(['teacher_id', 'is_published']);
            $table->index(['class_id', 'subject_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
