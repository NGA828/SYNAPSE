<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Documents are attached to a request and/or a student. Files are stored
     * on the configured filesystem disk; this table holds the metadata.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->nullable()->constrained('requests')->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('file_name');
            $table->string('mime_type')->default('application/pdf');
            $table->unsignedBigInteger('size')->default(0);
            $table->string('disk')->default('public');
            $table->string('path');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
