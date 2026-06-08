<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->string('record_number', 32)->nullable()->unique()->after('id');
        });

        $sequences = [];

        DB::table('leave_requests')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'created_at'])
            ->each(function (object $leaveRequest) use (&$sequences): void {
                $year = (int) date('Y', strtotime((string) $leaveRequest->created_at));
                $sequences[$year] = ($sequences[$year] ?? 0) + 1;

                DB::table('leave_requests')
                    ->where('id', $leaveRequest->id)
                    ->update([
                        'record_number' => sprintf('HR/%d/FORM/%02d', $year, $sequences[$year]),
                    ]);
            });

        Schema::table('leave_requests', function (Blueprint $table) {
            $table->string('record_number', 32)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropUnique(['record_number']);
            $table->dropColumn('record_number');
        });
    }
};
