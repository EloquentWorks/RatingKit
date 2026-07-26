<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('rating-kit.tables.histories', 'rating_kit_histories'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rating_id')->index();
            $table->foreignId('match_id')->nullable()->index();
            $table->string('reason')->index();
            $table->decimal('rating_before', 14, 6);
            $table->decimal('rating_after', 14, 6);
            $table->decimal('deviation_before', 14, 6);
            $table->decimal('deviation_after', 14, 6);
            $table->decimal('volatility_before', 14, 10);
            $table->decimal('volatility_after', 14, 10);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('rating-kit.tables.histories', 'rating_kit_histories'));
    }
};
