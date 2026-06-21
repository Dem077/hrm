<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->text('current_address')->nullable()->after('profile_photo_path');
            $table->text('permanent_address')->nullable()->after('current_address');
            $table->string('ext_no', 30)->nullable()->after('permanent_address');
            $table->string('personal_email')->nullable()->after('ext_no');
            $table->string('office_email')->nullable()->after('personal_email');
            $table->string('emergency_contact_name')->nullable()->after('office_email');
            $table->string('emergency_contact_number', 30)->nullable()->after('emergency_contact_name');
            $table->string('marital_status', 20)->nullable()->after('emergency_contact_number');
            $table->string('blood_group', 20)->nullable()->after('marital_status');
            $table->date('date_of_birth')->nullable()->after('blood_group');
            $table->string('nationality', 100)->nullable()->after('date_of_birth');
            $table->string('religion', 100)->nullable()->after('nationality');
            $table->string('work_location')->nullable()->after('religion');
            $table->string('qualification')->nullable()->after('work_location');
            $table->string('employment_type', 30)->nullable()->after('qualification');
            $table->string('bank_name')->nullable()->after('employment_type');
            $table->string('account_name')->nullable()->after('bank_name');
            $table->string('account_no', 50)->nullable()->after('account_name');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'current_address',
                'permanent_address',
                'ext_no',
                'personal_email',
                'office_email',
                'emergency_contact_name',
                'emergency_contact_number',
                'marital_status',
                'blood_group',
                'date_of_birth',
                'nationality',
                'religion',
                'work_location',
                'qualification',
                'employment_type',
                'bank_name',
                'account_name',
                'account_no',
            ]);
        });
    }
};
