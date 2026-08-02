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
        Schema::create(config('rating-kit.tables.seasons', 'rating_kit_seasons'), function (Blueprint $table): void {
            // Primary key
            $table->id();

            // Foreign key to the tournaments table
            $table->string('name');

            // Unique slug for the season
            $table->string('slug')->unique();

            // The pool that this season belongs to, defaulting to 'default' if not specified
            $table->string('pool')->default('default')->index();

            // The start time of the season
            $table->timestamp('starts_at')->nullable();

            // The end time of the season
            $table->timestamp('ends_at')->nullable();

            // The time when the season was closed
            $table->timestamp('closed_at')->nullable();

            // Additional metadata for the season
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
        Schema::dropIfExists(config('rating-kit.tables.seasons', 'rating_kit_seasons'));
    }
};
