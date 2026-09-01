<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One student's work for one piece of homework.
 *
 * A student may replace their submission as often as they like **before the
 * deadline**; the previous attempt is kept in `attempts` so the teacher can see
 * that it was revised. After the deadline the row is locked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homework_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('homework_assignment_id')->constrained('homework_assignments')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();

            $table->text('content')->nullable();
            $table->unsignedInteger('attempts')->default(1);

            $table->timestamp('submitted_at');
            $table->boolean('is_late')->default(false);

            // Grading — null score means "submitted but not yet marked".
            $table->decimal('score', 6, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('teachers')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();
            $table->timestamp('returned_at')->nullable();

            $table->timestamps();

            // One submission per student per homework; re-submission updates it.
            $table->unique(['homework_assignment_id', 'student_id'], 'submission_unique_per_student');

            $table->index(['student_id', 'submitted_at']);
            $table->index(['homework_assignment_id', 'graded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homework_submissions');
    }
};
