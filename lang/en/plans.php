<?php

return [

    'title' => 'Meal plans',
    'create' => 'New plan',
    'create_title' => 'New meal plan',
    'edit_title' => 'Edit meal plan',
    'default_name' => 'My keto week',

    'empty_title' => 'No meal plans yet',
    'empty_text' => 'Plan your meals day by day, check your carbs ahead of time and get a shopping list.',
    'status' => ['current' => 'Current', 'upcoming' => 'Upcoming'],
    'from_coach' => 'From the coach',
    'days_unit' => 'days',
    'days' => 'Days',
    'day_n' => 'Day :n',

    'fields' => [
        'name' => 'Plan name',
        'starts_on' => 'Start date',
        'days' => 'Number of days',
        'days_hint' => '1 to 14',
        'quantity' => 'Quantity',
        'unit' => 'Unit',
    ],

    'add_food' => 'Add food',
    'add_recipe' => 'Add recipe',
    'recipe' => 'Recipe',
    'meal_empty' => 'Nothing planned.',
    'item_added' => ':name added.',
    'items_dropped' => 'Items on the removed days were deleted.',
    'remove_item' => 'Remove :name',
    'row_not_counted' => 'Enter a valid quantity and unit — this item is not counted yet.',
    'deleted_item' => 'Deleted item',
    'no_recipes_title' => 'No recipes yet',
    'no_recipes_text' => 'Create a recipe first, then plan it here.',

    'copy_day' => 'Copy day',
    'copy_day_hint' => 'The chosen day will be replaced by a copy of this one.',
    'copy_to' => 'Copy to',
    'day_copied' => 'Copied to :day.',

    'day_totals' => 'Day totals',
    'day_empty' => 'Plan some meals to see this day\'s totals.',
    'over_carbs' => 'Over the net-carb target',
    'over_carbs_text' => 'This day is over your net-carb target. Swap a food or reduce a quantity.',

    'created' => 'Plan saved.',
    'updated' => 'Plan updated.',
    'deleted' => 'Plan deleted.',
    'delete_title' => 'Delete this plan?',
    'delete_text' => '":name" will be deleted. Meals you already logged from it stay in your diary.',

    'show' => [
        'tab_plan' => 'Plan',
        'tab_shopping' => 'Shopping list',
        'log_it' => 'Log it',
        'logged_badge' => 'Logged',
        'logged' => 'Logged to your diary.',
        'log_meal' => 'Log whole meal',
        'meal_logged' => ':meal logged to your diary.',
        'nothing_to_log' => 'Everything here is already logged.',
        'shopping_empty' => 'Nothing to buy',
        'shopping_empty_text' => 'Add foods or recipes to the plan to build a shopping list.',
        'shopping_hint' => 'Total raw amounts for the whole plan, with recipes broken down into ingredients.',
    ],

];
