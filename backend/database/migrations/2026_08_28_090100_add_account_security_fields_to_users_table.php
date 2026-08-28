<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Account hardening + delivery preferences:
     *
     * - `phone` powers the SMS channel (E.164, e.g. +237…).
     * - `must_change_password` forces a rotation after an admin creates or
     *   bulk-imports an account with a temporary password.
     * - `notify_email` / `notify_sms` let a user opt out per channel.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 32)->nullable()->after('email');
            $table->boolean('must_change_password')->default(false)->after('password');
            $table->timestamp('password_changed_at')->nullable()->after('must_change_password');
            $table->timestamp('last_login_at')->nullable()->after('password_changed_at');
            $table->boolean('notify_email')->default(true)->after('last_login_at');
            $table->boolean('notify_sms')->default(false)->after('notify_email');
            $table->string('locale', 8)->default('en')->after('notify_sms');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'must_change_password',
                'password_changed_at',
                'last_login_at',
                'notify_email',
                'notify_sms',
                'locale',
            ]);
        });
    }
};
