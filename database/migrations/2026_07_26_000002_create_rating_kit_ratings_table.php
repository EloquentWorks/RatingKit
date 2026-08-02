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
        Schema::create(config('rating-kit.tables.ratings', 'rating_kit_ratings'), function (Blueprint $table): void {
            // Primary key
            $table->id();

            // Polymorphic relationship to the rateable model
            match ((string) config('rating-kit.morph_key_type', 'numeric')) {
                'uuid' => $table->uuidMorphs('rateable'),
                'ulid' => $table->ulidMorphs('rateable'),
                default => $table->morphs('rateable'),
            };

            // The pool and algorithm for the rating, with indexes for efficient querying
            $table->string('pool')->default('default')->index();
            $table->string('algorithm')->index();

            // Foreign key to the seasons table, allowing for nullable values and indexing for efficient querying
            $table->foreignId('season_id')->nullable()->index();

            // The season key for the rating, defaulting to 0 if not specified
            $table->unsignedBigInteger('season_key')->default(0);

            // The rating, deviation, and volatility values for the rating, with default values specified
            $table->decimal('rating', 14, 6)->default(1500);
            $table->decimal('deviation', 14, 6)->default(350);
            $table->decimal('volatility', 14, 10)->default(0.06);

            // The number of games, wins, draws, and losses for the rating, with default values specified
            $table->unsignedInteger('games')->default(0);
            $table->unsignedInteger('wins')->default(0);
            $table->unsignedInteger('draws')->default(0);
            $table->unsignedInteger('losses')->default(0);

            // The streak for the rating, with a default value specified
            $table->integer('streak')->default(0);

            // The provisional status for the rating, with a default value specified and indexing for efficient querying
            $table->boolean('provisional')->default(true)->index();

            // The last competed timestamp for the rating, allowing for nullable values and indexing for efficient querying
            $table->timestamp('last_competed_at')->nullable()->index();

            // The decayed status for the rating, with a default value specified and indexing for efficient querying
            $table->timestamp('decayed_at')->nullable();

            // Additional metadata for the rating
            $table->json('metadata')->nullable();

            // Timestamps for created_at and updated_at
            $table->timestamps();

            // Unique constraint to ensure a rateable entity can have only one rating per pool, algorithm, and season key
            $table->unique(
                ['rateable_type', 'rateable_id', 'pool', 'algorithm', 'season_key'],
                'rating_kit_ratings_identity_unique',
            );

            // Index for efficient leaderboard queries based on pool, algorithm, season key, and rating
            $table->index(['pool', 'algorithm', 'season_key', 'rating'], 'rating_kit_leaderboard_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('rating-kit.tables.ratings', 'rating_kit_ratings'));
    }
};
