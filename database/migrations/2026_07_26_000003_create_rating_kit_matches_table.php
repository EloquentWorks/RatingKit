<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('rating-kit.tables.matches', 'rating_kit_matches'), function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('external_id')->nullable()->unique();
            $table->string('pool')->default('default')->index();
            $table->string('algorithm')->index();
            $table->json('algorithm_options')->nullable();
            $table->foreignId('season_id')->nullable()->index();
            $table->string('status')->default('pending')->index();
            $table->timestamp('occurred_at')->index();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('rating-kit.tables.matches', 'rating_kit_matches'));
    }
};
