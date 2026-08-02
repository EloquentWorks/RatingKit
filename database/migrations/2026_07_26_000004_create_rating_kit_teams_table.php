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
        Schema::create(config('rating-kit.tables.teams', 'rating_kit_teams'), function (Blueprint $table): void {
            // Primary key for the teams table
            $table->id();
            
            // Foreign key to the matches table, allowing for efficient querying
            $table->foreignId('match_id')->index();

            // The position of the team in the match, allowing for unsigned small integers
            $table->unsignedSmallInteger('position');
            $table->unsignedSmallInteger('rank')->index();

            // The score for the team, allowing for nullable values
            $table->decimal('score', 14, 4)->nullable();

            // Optional name for the team, allowing for nullable values
            $table->string('name')->nullable();

            // Additional metadata for the team
            $table->json('metadata')->nullable();

            // Timestamps for created_at and updated_at
            $table->timestamps();

            // Ensure that each match can only have one team per position
            $table->unique(['match_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('rating-kit.tables.teams', 'rating_kit_teams'));
    }
};
