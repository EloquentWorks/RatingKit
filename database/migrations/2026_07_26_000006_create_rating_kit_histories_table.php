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
        Schema::create(config('rating-kit.tables.histories', 'rating_kit_histories'), function (Blueprint $table): void {
            // Primary key for the histories table
            $table->id();

            // Polymorphic relationship to the rateable model
            $table->foreignId('rating_id')->index();
            $table->foreignId('match_id')->nullable()->index();

            // The reason for the rating change, with indexing for efficient querying
            $table->string('reason')->index();

            // Rating before and after the rating change, with precision and scale specified
            $table->decimal('rating_before', 14, 6);
            $table->decimal('rating_after', 14, 6);

            // Deviation before and after the rating change, with precision and scale specified
            $table->decimal('deviation_before', 14, 6);
            $table->decimal('deviation_after', 14, 6);

            // Volatility before the rating change, with precision and scale specified
            $table->decimal('volatility_before', 14, 10);

            // Volatility after the rating change, with precision and scale specified
            $table->decimal('volatility_after', 14, 10);

            // Additional metadata for the history entry
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
        Schema::dropIfExists(config('rating-kit.tables.histories', 'rating_kit_histories'));
    }
};
