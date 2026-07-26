<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('rating-kit.tables.leaderboard_snapshots', 'rating_kit_leaderboard_snapshots'), function (Blueprint $table): void {
            $table->id();
            $table->string('pool')->index();
            $table->string('algorithm')->index();
            $table->foreignId('season_id')->nullable()->index();
            $table->timestamp('captured_at')->index();
            $table->unsignedInteger('entry_count');
            $table->json('entries');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('rating-kit.tables.leaderboard_snapshots', 'rating_kit_leaderboard_snapshots'));
    }
};
