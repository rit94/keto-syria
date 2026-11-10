<?php

return [
    'title' => 'Today',
    'added' => ':name added to :meal.',
    'updated' => 'Entry updated.',
    'moved' => 'Entry moved to :date.',
    'deleted' => 'Entry deleted.',

    'date' => [
        'nav' => 'Choose a day',
        'previous' => 'Previous day',
        'next' => 'Next day',
        'pick' => 'Pick a date',
    ],

    'hero' => [
        'label' => 'Remaining macros',
        'net_left' => 'Net carbs left',
        'net_over' => 'Net carbs over target',
        'net_eaten' => 'Net carbs eaten',
        'eaten_of' => 'Eaten',
        'no_targets' => 'Set up your profile to get daily targets.',
        'total_carbs' => 'Total carbs',
        'saturated_fat' => 'Saturated fat',
    ],

    'incomplete' => [
        'title' => 'These totals are a minimum',
        'fiber' => ':count logged food has no fiber figure, so its carbs are counted in full and the real net carbs may be lower.|:count logged foods have no fiber figure, so their carbs are counted in full and the real net carbs may be lower.',
        'saturated_fat' => ':count logged food has no saturated fat figure, so the real total may be higher.|:count logged foods have no saturated fat figure, so the real total may be higher.',
    ],

    'meal' => [
        'empty' => 'Nothing logged yet',
        'add_to' => 'Add food to :meal',
    ],

    'entry' => [
        'edit' => 'Edit :name',
        'cooked_weight' => 'Cooked weight',
    ],

    'next' => [
        'title' => 'What can I eat next?',
        'no_targets' => 'Set up your profile to get suggestions.',
        'calories_reached' => 'You have reached your calories for the day.',
        'nothing_fits' => 'Nothing in your usual foods fits what is left. Try a smaller portion or ask the coach.',
        'fits' => 'Fits your :carbs g net carbs and :kcal kcal left.',
        'recipe' => 'Recipe',
        'ask_coach' => 'Ask the coach',
        'smart_log' => 'Log with text or photo',
    ],

    'quick' => [
        'title' => 'Quick add',
        'recent' => 'Recent',
        'frequent' => 'Frequent',
        'empty_title' => 'Your foods will appear here',
        'empty_text' => 'Log your first food and it is one tap away next time.',
        'first' => 'Add food',
        'repeat' => 'Add :name again with the same amount',
        'repeat_hint' => 'Add the same amount again',
    ],

    'add' => ['title' => 'Add food'],

    'recipe' => ['servings' => 'Servings'],

    'edit' => [
        'title' => 'Edit entry',
        'meal' => 'Meal',
        'date' => 'Date',
        'cooked_note' => 'This amount was weighed after cooking.',
        'source_deleted' => 'The original food was deleted. You can still change the quantity, meal and date.',
    ],

    'delete' => [
        'title' => 'Delete this entry?',
        'text' => '":name" will be removed from your diary.',
    ],

    'errors' => [
        'future_date' => 'You cannot log food for a future date.',
    ],
];
