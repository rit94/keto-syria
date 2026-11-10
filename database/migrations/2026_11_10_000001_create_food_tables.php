<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('foods', function (Blueprint $table) {
            $table->id();
            // null = shared catalogue entry, set = a user's private custom ingredient
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            // a variation (cooked / drained / different cut) of a base food
            $table->foreignId('parent_food_id')->nullable()->constrained('foods')->nullOnDelete();
            $table->string('slug')->nullable()->unique();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('category', 40)->index();
            $table->string('state', 20)->default('raw');          // App\Enums\FoodState
            $table->string('preparation', 20)->nullable();        // App\Enums\Preparation (drained/undrained)

            // Nutrition facts per 100 g of edible food. carbs_g is ALWAYS total carbohydrate
            // (fiber included); label values on a "net" basis are converted on the way in.
            $table->decimal('calories', 8, 2);
            $table->decimal('protein_g', 7, 3);
            $table->decimal('carbs_g', 7, 3);
            $table->decimal('fiber_g', 7, 3)->nullable();         // null = unknown, not zero
            $table->decimal('fat_g', 7, 3);
            $table->decimal('saturated_fat_g', 7, 3)->nullable(); // null = unknown, not zero
            $table->json('extra_nutrients')->nullable();          // e.g. {"sodium_mg": 120}
            $table->string('label_basis', 10)->default('total');  // App\Enums\CarbBasis the source used

            // cooked weight / raw weight (e.g. 0.70 for grilled meat). Lets a raw entry be
            // logged by cooked weight when no dedicated cooked variant exists.
            $table->decimal('cooked_yield', 5, 3)->nullable();

            // Provenance
            $table->string('source');                             // e.g. "USDA FoodData Central"
            $table->string('source_ref')->nullable();             // id / url within that source
            $table->string('confidence', 10)->default('medium');  // App\Enums\Confidence
            $table->boolean('is_estimate')->default(false);
            $table->text('notes_ar')->nullable();
            $table->text('notes_en')->nullable();

            $table->boolean('is_keto_friendly')->default(true);
            // normalised ar + en + aliases, maintained by the app, used for LIKE search
            $table->text('search_text')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'category']);
        });

        Schema::create('food_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('food_id')->constrained('foods')->cascadeOnDelete();
            $table->string('alias');
            $table->string('normalized')->index();
            $table->string('locale', 5)->default('ar');
            $table->unique(['food_id', 'normalized']);
        });

        // Per-food household units (piece, cup, tbsp, slice ...) expressed in grams.
        Schema::create('food_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('food_id')->constrained('foods')->cascadeOnDelete();
            $table->string('unit', 30);                           // machine key: piece, cup, tbsp, tsp, slice ...
            $table->string('label_ar');
            $table->string('label_en');
            $table->decimal('grams', 8, 2);
            $table->boolean('is_default')->default(false);
            $table->unique(['food_id', 'unit']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_units');
        Schema::dropIfExists('food_aliases');
        Schema::dropIfExists('foods');
    }
};
