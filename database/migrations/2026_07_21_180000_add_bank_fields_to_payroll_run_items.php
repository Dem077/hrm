<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_run_items', function (Blueprint $table) {
            $table->string('bank_name')->nullable()->after('national_id');
            $table->string('account_name')->nullable()->after('bank_name');
            $table->string('account_no')->nullable()->after('account_name');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_run_items', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'account_name', 'account_no']);
        });
    }
};
