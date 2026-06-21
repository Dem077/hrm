<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('zkt_location_group_role');
    }

    public function down(): void
    {
        Schema::create('zkt_location_group_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zkt_location_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['zkt_location_group_id', 'role_id'], 'location_group_role_unique');
        });
    }
};
