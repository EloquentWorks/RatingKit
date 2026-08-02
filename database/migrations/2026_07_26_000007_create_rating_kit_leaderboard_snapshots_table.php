<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(config('rating-kit.tables.leaderboard_snapshots', 'rating_kit_leaderboard_snapshots'), function (Blueprint $table): void {
            // Primary key for the leaderboard snapshots table
            $table->id();

            // Unique identifier for the leaderboard snapshot, using UUIDs for better uniqueness across distributed systems
            $table->string('pool')->index();

            // The algorithm used for the leaderboard snapshot, with indexing for efficient querying
            $table->string('algorithm')->index();

            // Foreign key to the seasons table, allowing for nullable values and indexing for efficient querying
            $table->foreignId('season_id')->nullable()->index();

            // Timestamp indicating when the leaderboard snapshot was captured
            $table->timestamp('captured_at')->index();

            // Store the number of entries in the leaderboard snapshot
            $table->unsignedInteger('entry_count');

            // Store the leaderboard entries as a JSON array
            $table->json('entries');

            // Additional metadata for the leaderboard snapshot
            $table->json('metadata')->nullable();

            // Timestamps for created_at and updated_at
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('rating-kit.tables.leaderboard_snapshots', 'rating_kit_leaderboard_snapshots'));
    }
};
