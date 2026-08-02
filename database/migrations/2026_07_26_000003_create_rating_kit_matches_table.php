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
        Schema::create(config('rating-kit.tables.matches', 'rating_kit_matches'), function (Blueprint $table): void {
            // Primary key for the matches table
            $table->id();

            // Unique identifier for the match, using UUIDs for better uniqueness across distributed systems
            $table->uuid('uuid')->unique();

            // Optional external ID for the match, allowing for nullable values and unique constraint
            $table->string('external_id')->nullable()->unique();

            // Polymorphic relationship to the matchable model
            $table->string('pool')->default('default')->index();

            // The algorithm used for the match, with indexing for efficient querying
            $table->string('algorithm')->index();
            $table->json('algorithm_options')->nullable();

            // Foreign key to the seasons table, allowing for nullable values and indexing for efficient querying
            $table->foreignId('season_id')->nullable()->index();

            // The status of the match, with a default value of 'pending' and indexing for efficient querying
            $table->string('status')->default('pending')->index();

            // The timestamp when the match occurred, with indexing for efficient querying
            $table->timestamp('occurred_at')->index();
            $table->timestamp('processed_at')->nullable();

            // Voided status for the match
            $table->timestamp('voided_at')->nullable();

            // Reason for voiding the match, if applicable
            $table->text('void_reason')->nullable();

            // Additional metadata for the match
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
        Schema::dropIfExists(config('rating-kit.tables.matches', 'rating_kit_matches'));
    }
};
