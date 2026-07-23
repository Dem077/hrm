<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('structure_grades', function (Blueprint $table) {
            $table->text('requirements')->nullable()->after('title');
            $table->text('job_description')->nullable()->after('requirements');
        });
    }

    public function down(): void
    {
        Schema::table('structure_grades', function (Blueprint $table) {
            $table->dropColumn(['requirements', 'job_description']);
        });
    }
};
