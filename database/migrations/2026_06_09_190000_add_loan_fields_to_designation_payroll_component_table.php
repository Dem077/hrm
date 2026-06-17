<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('designation_payroll_component', function (Blueprint $table) {
            $table->unsignedSmallInteger('loan_months')->nullable()->after('amount');
            $table->string('loan_bank', 10)->nullable()->after('loan_months');
        });
    }

    public function down(): void
    {
        Schema::table('designation_payroll_component', function (Blueprint $table) {
            $table->dropColumn(['loan_months', 'loan_bank']);
        });
    }
};
