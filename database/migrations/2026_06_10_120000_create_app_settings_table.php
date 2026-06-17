<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('app_name')->default('HRM');
            $table->string('tagline')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('brand_color_400', 7)->default('#fbbf24');
            $table->string('brand_color_500', 7)->default('#f59e0b');
            $table->string('brand_color_600', 7)->default('#d97706');
            $table->string('brand_color_700', 7)->default('#b45309');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
