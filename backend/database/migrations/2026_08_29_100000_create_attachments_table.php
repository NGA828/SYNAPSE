<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uploaded files, attached to whatever owns them.
 *
 * Polymorphic on purpose: homework briefs and student submissions use it in
 * this phase, and course materials / lesson resources will reuse the same
 * table and download path rather than growing a parallel one.
 *
 * The table holds metadata only — bytes live on the configured disk, exactly
 * like `documents`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->morphs('attachable');

            // Who put it there: a teacher's brief, or a student's submission.
            $table->string('uploaded_by_role')->default('teacher');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('file_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size')->default(0);
            $table->string('disk')->default('local');
            $table->string('path');

            /*
             * `class`  — every student enrolled in the owning homework can
             *            download it (a teacher's brief).
             * `private`— only the uploader and the teacher who set the work
             *            can (a student's own submission).
             */
            $table->string('visibility')->default('class')->index();

            $table->timestamps();

            $table->index(['school_id', 'visibility']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
