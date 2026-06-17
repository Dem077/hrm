<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->boolean('can_carry_forward')->default(false)->after('annual_limit');
            $table->unsignedSmallInteger('max_carry_forward_days')->nullable()->after('can_carry_forward');
        });
    }

    public function down(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropColumn(['can_carry_forward', 'max_carry_forward_days']);
        });
    }
};
