<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('self_punch_sites', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('radius_meters')->default(100);
            $table->unsignedInteger('max_accuracy_meters')->default(100);
            $table->json('allowed_public_ips')->nullable();
            $table->boolean('require_public_ip')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('employee_self_punch_site', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('self_punch_site_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'self_punch_site_id'], 'employee_self_punch_site_unique');
        });

        Schema::table('zkt_attendance_logs', function (Blueprint $table) {
            $table->foreignId('self_punch_site_id')->nullable()->after('source')->constrained()->nullOnDelete();
            $table->decimal('latitude', 10, 7)->nullable()->after('self_punch_site_id');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->unsignedInteger('accuracy_meters')->nullable()->after('longitude');
            $table->string('client_ip', 45)->nullable()->after('accuracy_meters');
        });
    }

    public function down(): void
    {
        Schema::table('zkt_attendance_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('self_punch_site_id');
            $table->dropColumn(['latitude', 'longitude', 'accuracy_meters', 'client_ip']);
        });

        Schema::dropIfExists('employee_self_punch_site');
        Schema::dropIfExists('self_punch_sites');
    }
};
