<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->decimal('servings', 6, 2)->default(1);
            // weight of the finished dish; enables logging a recipe by grams
            $table->decimal('cooked_weight_g', 8, 1)->nullable();
            $table->string('origin', 10)->default('user');   // user | ai
            $table->timestamps();
        });

        Schema::create('recipe_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('food_id')->constrained('foods')->restrictOnDelete();
            $table->decimal('quantity', 9, 3);
            $table->string('unit', 30)->default('g');
            $table->decimal('grams', 9, 3);                  // resolved weight, the value the engine uses
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('food_log_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('logged_on');
            $table->string('meal', 15);                      // App\Enums\Meal
            $table->foreignId('food_id')->nullable()->constrained('foods')->nullOnDelete();
            $table->foreignId('recipe_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');                   // display name snapshot
            $table->decimal('quantity', 9, 3);
            $table->string('unit', 30)->default('g');        // g | serving | a food_units.unit key
            $table->decimal('grams', 9, 3)->nullable();      // null only for recipe entries logged by serving

            // Snapshot written by the nutrition engine at log time (full precision; round on display).
            $table->decimal('calories', 10, 3);
            $table->decimal('protein_g', 10, 3);
            $table->decimal('carbs_g', 10, 3);
            $table->decimal('fiber_g', 10, 3)->nullable();
            $table->decimal('fat_g', 10, 3);
            $table->decimal('saturated_fat_g', 10, 3)->nullable();
            $table->json('missing')->nullable();             // nutrient keys that were unknown for this entry
            $table->boolean('is_estimate')->default(false);

            $table->string('source', 10)->default('manual'); // App\Enums\LogSource
            $table->uuid('group_id')->nullable();            // entries logged together as one meal
            $table->timestamps();

            $table->index(['user_id', 'logged_on']);
        });

        Schema::create('meal_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('starts_on');
            $table->unsignedSmallInteger('days')->default(7);
            $table->string('origin', 10)->default('user');   // user | ai
            $table->timestamps();
        });

        Schema::create('meal_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_plan_id')->constrained()->cascadeOnDelete();
            $table->date('planned_on');
            $table->string('meal', 15);
            $table->foreignId('food_id')->nullable()->constrained('foods')->nullOnDelete();
            $table->foreignId('recipe_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 9, 3)->default(1);
            $table->string('unit', 30)->default('g');        // g | serving | food unit key
            $table->decimal('grams', 9, 3)->nullable();
            $table->string('note')->nullable();
            $table->foreignId('food_log_entry_id')->nullable()->constrained()->nullOnDelete(); // set once eaten
            $table->timestamps();

            $table->index(['meal_plan_id', 'planned_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_plan_items');
        Schema::dropIfExists('meal_plans');
        Schema::dropIfExists('food_log_entries');
        Schema::dropIfExists('recipe_ingredients');
        Schema::dropIfExists('recipes');
    }
};
