<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('rating-kit.tables.ratings', 'rating_kit_ratings'), function (Blueprint $table): void {
            $table->id();
            match ((string) config('rating-kit.morph_key_type', 'numeric')) {
                'uuid' => $table->uuidMorphs('rateable'),
                'ulid' => $table->ulidMorphs('rateable'),
                default => $table->morphs('rateable'),
            };
            $table->string('pool')->default('default')->index();
            $table->string('algorithm')->index();
            $table->foreignId('season_id')->nullable()->index();
            $table->unsignedBigInteger('season_key')->default(0);
            $table->decimal('rating', 14, 6)->default(1500);
            $table->decimal('deviation', 14, 6)->default(350);
            $table->decimal('volatility', 14, 10)->default(0.06);
            $table->unsignedInteger('games')->default(0);
            $table->unsignedInteger('wins')->default(0);
            $table->unsignedInteger('draws')->default(0);
            $table->unsignedInteger('losses')->default(0);
            $table->integer('streak')->default(0);
            $table->boolean('provisional')->default(true)->index();
            $table->timestamp('last_competed_at')->nullable()->index();
            $table->timestamp('decayed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['rateable_type', 'rateable_id', 'pool', 'algorithm', 'season_key'],
                'rating_kit_ratings_identity_unique',
            );
            $table->index(['pool', 'algorithm', 'season_key', 'rating'], 'rating_kit_leaderboard_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('rating-kit.tables.ratings', 'rating_kit_ratings'));
    }
};
