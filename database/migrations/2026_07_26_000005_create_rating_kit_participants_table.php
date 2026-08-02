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
        Schema::create(config('rating-kit.tables.participants', 'rating_kit_participants'), function (Blueprint $table): void {
            // Primary key for the participants table
            $table->id();

            // Foreign keys to the matches, teams, and ratings tables, with indexing for efficient querying
            $table->foreignId('match_id')->index();
            $table->foreignId('team_id')->index();
            $table->foreignId('rating_id')->index();

            // Polymorphic relationship to the rateable model, with support for different key types (numeric, UUID, ULID)
            match ((string) config('rating-kit.morph_key_type', 'numeric')) {
                'uuid' => $table->uuidMorphs('rateable'),
                'ulid' => $table->ulidMorphs('rateable'),
                default => $table->morphs('rateable'),
            };

            // The outcome of the match for the participant, with indexing for efficient querying
            $table->string('outcome')->index();

            // Weight of the participant in the match, with precision and scale specified
            $table->decimal('weight', 10, 4)->default(1);

            // Rating values before and after the match, with precision and scale specified
            $table->decimal('rating_before', 14, 6);
            $table->decimal('rating_after', 14, 6);
            $table->decimal('rating_delta', 14, 6);

            // Deviation values before and after the match, with precision and scale specified
            $table->decimal('deviation_before', 14, 6);
            $table->decimal('deviation_after', 14, 6);

            // Volatility values before and after the match, with precision and scale specified
            $table->decimal('volatility_before', 14, 10);
            $table->decimal('volatility_after', 14, 10);

            // Additional metadata for the participant
            $table->json('metadata')->nullable();

            // Timestamps for created_at and updated_at
            $table->timestamps();

            // Ensure that each match can only have one participant per rateable entity
            $table->unique(['match_id', 'rateable_type', 'rateable_id'], 'rating_kit_match_participant_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('rating-kit.tables.participants', 'rating_kit_participants'));
    }
};
