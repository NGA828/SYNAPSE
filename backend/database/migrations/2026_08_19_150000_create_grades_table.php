<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One grade row per (student, subject, class, academic year). The
     * `teacher_id` records who entered the grade (must hold the assignment).
     */
    public function up(): void
    {
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->decimal('test1', 5, 2)->nullable();
            $table->decimal('test2', 5, 2)->nullable();
            $table->decimal('exam', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(
                ['student_id', 'subject_id', 'class_id', 'academic_year_id'],
                'grade_student_subject_class_year_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
