<?php

use App\Models\AppSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('structure_nodes', function (Blueprint $table) {
            $table->json('leave_approval_workflow')->nullable()->after('sort_order');
            $table->json('overtime_approval_workflow')->nullable()->after('leave_approval_workflow');
        });

        Schema::table('app_settings', function (Blueprint $table) {
            $table->json('overtime_approval_workflow')->nullable()->after('leave_approval_workflow');
        });

        $defaultLeave = AppSetting::query()->value('leave_approval_workflow');

        if (is_string($defaultLeave)) {
            $decoded = json_decode($defaultLeave, true);
            $defaultLeave = is_array($decoded) ? $decoded : null;
        }

        if (! is_array($defaultLeave)) {
            $defaultLeave = [
                'steps' => [
                    ['key' => 'direct_manager', 'enabled' => true],
                    ['key' => 'unit_section', 'enabled' => true],
                    ['key' => 'department', 'enabled' => true],
                    ['key' => 'division', 'enabled' => true],
                ],
            ];
        }

        $encoded = json_encode($defaultLeave);

        DB::table('app_settings')->update([
            'overtime_approval_workflow' => $encoded,
        ]);

        // Seed each top-level branch (directly under Strategic Leadership) with the current default.
        DB::table('structure_nodes')
            ->whereNull('parent_id')
            ->update([
                'leave_approval_workflow' => $encoded,
                'overtime_approval_workflow' => $encoded,
            ]);
    }

    public function down(): void
    {
        Schema::table('structure_nodes', function (Blueprint $table) {
            $table->dropColumn(['leave_approval_workflow', 'overtime_approval_workflow']);
        });

        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn('overtime_approval_workflow');
        });
    }
};
