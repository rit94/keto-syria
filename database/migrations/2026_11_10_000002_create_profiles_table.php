<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 5)->default('ar');
        });

        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('sex', 10);                       // App\Enums\Sex
            $table->date('birth_date');
            $table->decimal('height_cm', 5, 1);
            $table->decimal('weight_kg', 5, 1);
            $table->decimal('goal_weight_kg', 5, 1)->nullable();
            $table->string('activity_level', 20);            // App\Enums\ActivityLevel
            $table->string('goal', 20);                      // App\Enums\WeightGoal
            $table->string('approach', 30);                  // App\Enums\DietApproach
            $table->boolean('needs_medical_supervision')->default(false);

            // Daily targets. Calculated by the macro calculator, optionally overridden.
            $table->decimal('target_calories', 7, 1);
            $table->decimal('target_protein_g', 6, 1);
            $table->decimal('target_fat_g', 6, 1);
            $table->decimal('target_carbs_g', 6, 1);
            $table->decimal('target_net_carbs_g', 6, 1);
            $table->boolean('targets_customized')->default(false);
            $table->timestamps();
        });

        Schema::create('weight_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('measured_on');
            $table->decimal('weight_kg', 5, 1);
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'measured_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weight_entries');
        Schema::dropIfExists('profiles');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('locale'));
    }
};
