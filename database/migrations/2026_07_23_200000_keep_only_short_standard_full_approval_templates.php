<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $systemIds = [
            'leave' => [],
            'overtime' => [],
        ];
        $systemByFingerprint = [
            'leave' => [],
            'overtime' => [],
        ];
        $fullIds = [
            'leave' => null,
            'overtime' => null,
        ];

        foreach (DB::table('approval_templates')->where('is_system', true)->get() as $template) {
            $kind = $template->kind;
            $id = (int) $template->id;
            $systemIds[$kind][] = $id;
            $systemByFingerprint[$kind][$this->fingerprint($template->steps)] = $id;

            if (strtolower($template->name) === 'full') {
                $fullIds[$kind] = $id;
            }
        }

        $extras = DB::table('approval_templates')->where('is_system', false)->get();

        foreach ($extras as $extra) {
            $kind = $extra->kind;
            $replacement = $systemByFingerprint[$kind][$this->fingerprint($extra->steps)]
                ?? $fullIds[$kind];

            if (! $replacement) {
                continue;
            }

            if ($kind === 'leave') {
                DB::table('app_settings')
                    ->where('leave_approval_template_id', $extra->id)
                    ->update(['leave_approval_template_id' => $replacement]);

                DB::table('structure_nodes')
                    ->where('leave_approval_template_id', $extra->id)
                    ->update(['leave_approval_template_id' => $replacement]);
            }

            if ($kind === 'overtime') {
                DB::table('app_settings')
                    ->where('overtime_approval_template_id', $extra->id)
                    ->update(['overtime_approval_template_id' => $replacement]);

                DB::table('structure_nodes')
                    ->where('overtime_approval_template_id', $extra->id)
                    ->update(['overtime_approval_template_id' => $replacement]);
            }
        }

        $extraIds = $extras->pluck('id')->all();

        if ($extraIds !== []) {
            if ($fullIds['leave']) {
                DB::table('app_settings')
                    ->whereIn('leave_approval_template_id', $extraIds)
                    ->update(['leave_approval_template_id' => $fullIds['leave']]);

                DB::table('structure_nodes')
                    ->whereIn('leave_approval_template_id', $extraIds)
                    ->update(['leave_approval_template_id' => $fullIds['leave']]);
            }

            if ($fullIds['overtime']) {
                DB::table('app_settings')
                    ->whereIn('overtime_approval_template_id', $extraIds)
                    ->update(['overtime_approval_template_id' => $fullIds['overtime']]);

                DB::table('structure_nodes')
                    ->whereIn('overtime_approval_template_id', $extraIds)
                    ->update(['overtime_approval_template_id' => $fullIds['overtime']]);
            }

            DB::table('approval_templates')->whereIn('id', $extraIds)->delete();
        }

        if ($fullIds['leave'] || $fullIds['overtime']) {
            $settings = DB::table('app_settings')->orderBy('id')->first();

            if ($settings) {
                DB::table('app_settings')->where('id', $settings->id)->update([
                    'leave_approval_template_id' => $settings->leave_approval_template_id ?: $fullIds['leave'],
                    'overtime_approval_template_id' => $settings->overtime_approval_template_id ?: $fullIds['overtime'],
                ]);
            }
        }
    }

    public function down(): void
    {
        // Irreversible cleanup of migrated/custom templates.
    }

    protected function fingerprint(mixed $raw): string
    {
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        $steps = [];
        if (is_array($raw) && isset($raw['steps']) && is_array($raw['steps'])) {
            $steps = $raw['steps'];
        } elseif (is_array($raw) && array_is_list($raw)) {
            $steps = $raw;
        }

        $payload = [];
        foreach ($steps as $step) {
            if (! is_array($step)) {
                continue;
            }
            $ids = $step['head_grade_ids'] ?? [];
            if (! is_array($ids)) {
                $ids = [];
            }
            sort($ids);
            $payload[] = [
                'key' => (string) ($step['key'] ?? ''),
                'enabled' => (bool) ($step['enabled'] ?? false),
                'head_grade_ids' => array_values($ids),
            ];
        }

        return hash('sha256', json_encode($payload));
    }
};
