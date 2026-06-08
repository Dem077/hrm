<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendance_settings')) {
            $this->seedDefaultPolicy();

            return;
        }

        $settings = DB::table('attendance_settings')->first();

        if ($settings) {
            DB::table('attendance_duty_policies')->insert([
                'effective_from' => '2020-01-01',
                'duty_start_time' => $settings->duty_start_time,
                'duty_end_time' => $settings->duty_end_time,
                'grace_minutes' => $settings->grace_minutes,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $this->seedDefaultPolicy();
        }

        Schema::dropIfExists('attendance_settings');
    }

    public function down(): void
    {
        Schema::create('attendance_settings', function (Blueprint $table) {
            $table->id();
            $table->time('duty_start_time')->default('09:00:00');
            $table->time('duty_end_time')->default('18:00:00');
            $table->unsignedSmallInteger('grace_minutes')->default(15);
            $table->timestamps();
        });

        $policy = DB::table('attendance_duty_policies')
            ->orderByDesc('effective_from')
            ->first();

        if ($policy) {
            DB::table('attendance_settings')->insert([
                'duty_start_time' => $policy->duty_start_time,
                'duty_end_time' => $policy->duty_end_time,
                'grace_minutes' => $policy->grace_minutes,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::dropIfExists('attendance_duty_policies');
    }

    protected function seedDefaultPolicy(): void
    {
        if (DB::table('attendance_duty_policies')->exists()) {
            return;
        }

        DB::table('attendance_duty_policies')->insert([
            'effective_from' => '2020-01-01',
            'duty_start_time' => '09:00:00',
            'duty_end_time' => '18:00:00',
            'grace_minutes' => 15,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
