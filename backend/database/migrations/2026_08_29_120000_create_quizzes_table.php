<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auto-marked quizzes.
 *
 * Scoped the same way as homework — one teacher, one class, one subject, one
 * academic year — so the same "who teaches what" guard applies and a quiz can
 * never be published into a class the teacher does not hold.
 *
 * `closes_at` is deliberately nullable: a practice quiz can stay open for the
 * whole term, while a marked test gets a hard stop.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->nullable()->constrained('semesters')->nullOnDelete();

            $table->string('title');
            $table->text('instructions')->nullable();

            /**
             * Mark awarded for a perfect paper. Kept separate from the sum of
             * the question points so a teacher can rescale the paper without
             * editing every question.
             */
            $table->unsignedInteger('max_score')->default(20);

            // Null = open until the teacher closes it.
            $table->timestamp('closes_at')->nullable();

            // Null = untimed. Otherwise minutes from the student's own start.
            $table->unsignedInteger('time_limit_minutes')->nullable();

            $table->unsignedTinyInteger('attempts_allowed')->default(1);

            $table->boolean('is_published')->default(false)->index();
            $table->timestamp('published_at')->nullable();

            /*
             * Set once the teacher has seen the first results. From then on the
             * questions are frozen: changing a question underneath students who
             * already sat the paper would make the marks incomparable.
             */
            $table->boolean('is_locked')->default(false);

            $table->timestamps();

            $table->unique(
                ['school_id', 'class_id', 'subject_id', 'academic_year_id', 'title'],
                'quizzes_unique_per_class_subject_year',
            );

            $table->index(['teacher_id', 'is_published']);
            $table->index(['class_id', 'subject_id', 'closes_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quizzes');
    }
};
