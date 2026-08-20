<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payment records. `sandbox` marks development/mock payments so real
     * successful payments are never fabricated in production.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider')->default('mock'); // mock | mtn_momo | orange_money | card
            $table->string('method')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency')->default('XAF');
            $table->string('status')->default('pending')->index(); // pending | succeeded | failed | refunded
            $table->string('reference')->nullable()->unique();
            $table->json('metadata')->nullable();
            $table->boolean('sandbox')->default(true);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
