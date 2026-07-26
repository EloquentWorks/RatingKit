<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('rating-kit.tables.participants', 'rating_kit_participants'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('match_id')->index();
            $table->foreignId('team_id')->index();
            $table->foreignId('rating_id')->index();
            match ((string) config('rating-kit.morph_key_type', 'numeric')) {
                'uuid' => $table->uuidMorphs('rateable'),
                'ulid' => $table->ulidMorphs('rateable'),
                default => $table->morphs('rateable'),
            };
            $table->string('outcome')->index();
            $table->decimal('weight', 10, 4)->default(1);
            $table->decimal('rating_before', 14, 6);
            $table->decimal('rating_after', 14, 6);
            $table->decimal('rating_delta', 14, 6);
            $table->decimal('deviation_before', 14, 6);
            $table->decimal('deviation_after', 14, 6);
            $table->decimal('volatility_before', 14, 10);
            $table->decimal('volatility_after', 14, 10);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['match_id', 'rateable_type', 'rateable_id'], 'rating_kit_match_participant_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('rating-kit.tables.participants', 'rating_kit_participants'));
    }
};
