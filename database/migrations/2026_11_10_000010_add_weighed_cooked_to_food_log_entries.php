<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_log_entries', function (Blueprint $table) {
            // true = quantity/unit were weighed after cooking and `grams` holds the raw equivalent.
            // Needed so that editing the quantity later repeats the same conversion.
            $table->boolean('weighed_cooked')->default(false)->after('grams');
        });
    }

    public function down(): void
    {
        Schema::table('food_log_entries', fn (Blueprint $table) => $table->dropColumn('weighed_cooked'));
    }
};
