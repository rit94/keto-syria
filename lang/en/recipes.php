<?php

return [

    'title' => 'Recipes',
    'create' => 'New recipe',
    'create_title' => 'New recipe',
    'edit_title' => 'Edit recipe',

    'empty_title' => 'No recipes yet',
    'empty_text' => 'Build a recipe from the foods you cook with and log it by serving in one tap.',
    'search_placeholder' => 'Search your recipes',
    'no_results_title' => 'No recipe matches',
    'no_results_text' => 'Nothing found for ":query".',
    'clear_search' => 'Clear search',

    'servings_label' => 'Servings',
    'per_serving' => 'Per serving',
    'from_coach' => 'From the coach',

    'ingredients' => 'Ingredients',
    'add_ingredient' => 'Add ingredient',
    'ingredient_added' => ':name added.',
    'picker_unavailable' => 'Food search is not available right now.',
    'no_ingredients_title' => 'No ingredients yet',
    'no_ingredients_text' => 'Add the foods that go into this recipe, as you weigh them before cooking.',
    'unknown_food' => 'Food not available',
    'move_up' => 'Move :name up',
    'move_down' => 'Move :name down',
    'remove_ingredient' => 'Remove :name',
    'row_not_counted' => 'Enter a valid quantity and unit — this ingredient is not counted yet.',

    'created' => 'Recipe saved.',
    'updated' => 'Recipe updated.',
    'deleted' => 'Recipe deleted.',
    'duplicate' => 'Duplicate',
    'duplicated' => 'Copy created. Adjust it and save.',
    'copy_of' => ':name (copy)',
    'delete_title' => 'Delete this recipe?',
    'delete_text' => '":name" will be deleted. Meals you already logged from it stay in your diary.',

    'fields' => [
        'name' => 'Recipe name',
        'name_placeholder' => 'e.g. Grilled kofta',
        'description' => 'Short description',
        'instructions' => 'Method',
        'servings' => 'Number of servings',
        'cooked_weight' => 'Cooked weight',
        'cooked_weight_hint' => 'Weigh the finished dish: this lets you log it by grams and accounts for water lost in cooking.',
        'quantity' => 'Quantity',
        'unit' => 'Unit',
    ],

    'errors' => [
        'no_ingredients' => 'Add at least one ingredient.',
    ],

    'nutrition' => [
        'title' => 'Nutrition',
        'nutrient' => 'Nutrient',
        'saturated_fat' => 'Saturated fat',
        'per_serving' => 'Per serving',
        'whole' => 'Whole recipe',
        'per_100g' => 'Per 100 g',
        'for_servings' => ':count servings',
        'raw_weight' => 'Raw ingredients:',
        'cooked_weight' => 'Cooked weight:',
        'per_100g_raw_note' => 'No cooked weight was measured, so "per 100 g" is based on the raw ingredient weight.',
        'incomplete_title' => 'Some values are minimums',
        'incomplete_text' => 'At least one ingredient has no fibre or saturated-fat figure. Values marked ≥ may be higher, and net carbs are counted without subtracting the unknown fibre.',
        'rows_skipped' => 'Ingredients without a valid quantity or unit are not included.',
        'empty' => 'Add ingredients to see the nutrition of the recipe.',
    ],

    'adjust' => [
        'label' => 'Servings to cook',
        'reset' => 'Back to :count',
        'hint' => 'Ingredient amounts and totals follow the number of servings. One serving stays the same.',
    ],

    'log' => [
        'action' => 'Log this',
        'title' => 'Log recipe',
        'meal' => 'Meal',
        'date' => 'Date',
        'quantity' => 'Quantity',
        'unit' => 'Unit',
        'preview' => 'You will log',
        'submit' => 'Log',
        'logged' => ':name logged.',
        'grams_raw_note' => 'This recipe has no measured cooked weight, so grams are counted against the raw ingredient weight.',
    ],

];
