<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nationalities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        $names = DB::table('employees')
            ->whereNotNull('nationality')
            ->where('nationality', '!=', '')
            ->distinct()
            ->orderBy('nationality')
            ->pluck('nationality')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique(fn (string $name) => mb_strtolower($name))
            ->values();

        if ($names->isEmpty()) {
            $names = collect(['Maldivian']);
        } elseif (! $names->contains(fn (string $name) => strcasecmp($name, 'Maldivian') === 0)) {
            $names = $names->prepend('Maldivian');
        }

        DB::table('nationalities')->insert(
            $names
                ->values()
                ->map(fn (string $name, int $index) => [
                    'name' => $name,
                    'sort_order' => $index,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all()
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('nationalities');
    }
};
