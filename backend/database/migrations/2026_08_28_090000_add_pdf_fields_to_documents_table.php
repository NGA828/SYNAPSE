<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Generated documents are now real PDFs that can be re-issued and
     * authenticated, so we track their kind, who issued them, a public
     * verification code and free-form metadata (year, semester, …).
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('type')->default('certificate')->after('student_id')->index();
            $table->foreignId('issued_by')->nullable()->after('type')->constrained('users')->nullOnDelete();
            $table->string('verification_code', 32)->nullable()->after('path')->unique();
            $table->json('meta')->nullable()->after('verification_code');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('issued_by');
            $table->dropColumn(['type', 'verification_code', 'meta']);
        });
    }
};
