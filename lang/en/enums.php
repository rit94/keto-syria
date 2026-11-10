<?php

// Labels for app/Enums/* — read by each enum's label().

return [

    'meal' => [
        'breakfast' => 'Breakfast',
        'lunch' => 'Lunch',
        'dinner' => 'Dinner',
        'snack' => 'Snack',
    ],

    'confidence' => [
        'high' => 'Verified',
        'medium' => 'Medium accuracy',
        'low' => 'Approximate',
    ],

    'activity_level' => [
        'sedentary' => 'Sedentary',
        'light' => 'Lightly active',
        'moderate' => 'Moderately active',
        'active' => 'Active',
        'very_active' => 'Very active',
    ],

    'carb_basis' => [
        'total' => 'Total carbs',
        'net' => 'Net carbs',
    ],

    'diet_approach' => [
        'strict_keto' => 'Strict keto',
        'standard_keto' => 'Standard keto',
        'liberal_low_carb' => 'Liberal low-carb',
    ],

    'food_category' => [
        'vegetables' => 'Vegetables',
        'fruits' => 'Fruits',
        'meat' => 'Meat',
        'poultry' => 'Poultry',
        'fish' => 'Fish',
        'eggs' => 'Eggs',
        'dairy' => 'Dairy',
        'cheese' => 'Cheese',
        'fats' => 'Fats & oils',
        'nuts' => 'Nuts & seeds',
        'herbs' => 'Herbs & spices',
        'condiments' => 'Condiments',
        'beverages' => 'Beverages',
        'other' => 'Other',
    ],

    'food_state' => [
        'raw' => 'Raw',
        'cooked' => 'Cooked',
        'na' => 'Ready to eat',
    ],

    'log_source' => [
        'manual' => 'Manual',
        'text' => 'Text',
        'voice' => 'Voice',
        'image' => 'Photo',
        'coach' => 'Coach',
        'plan' => 'Plan',
    ],

    'preparation' => [
        'drained' => 'Drained',
        'undrained' => 'With liquid',
    ],

    'sex' => [
        'male' => 'Male',
        'female' => 'Female',
    ],

    'weight_goal' => [
        'lose' => 'Lose weight',
        'maintain' => 'Maintain weight',
        'gain' => 'Gain weight',
    ],

];
