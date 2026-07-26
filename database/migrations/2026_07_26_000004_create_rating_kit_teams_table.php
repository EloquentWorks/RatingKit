<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('rating-kit.tables.teams', 'rating_kit_teams'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('match_id')->index();
            $table->unsignedSmallInteger('position');
            $table->unsignedSmallInteger('rank')->index();
            $table->decimal('score', 14, 4)->nullable();
            $table->string('name')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['match_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('rating-kit.tables.teams', 'rating_kit_teams'));
    }
};
