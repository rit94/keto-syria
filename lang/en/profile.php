<?php

return [

    'title' => 'Profile',

    'medical' => [
        'title' => 'Medical follow-up advised',
    ],

    'targets' => [
        'title' => 'Daily targets',
        'kcal_per_day' => 'kcal per day',
        'total_carbs' => 'Total carbs',
        'net_carbs_hint' => 'Stay under',
        'protein_hint' => 'Aim to reach',
        'fat_hint' => 'Upper limit',
        'carbs_hint' => 'Including fibre',
        'automatic' => 'Calculated automatically',
        'automatic_text' => 'Recalculated automatically whenever your weight or details change.',
        'customised' => 'Customised',
        'customised_text' => 'You set these numbers yourself. They stay as they are when your weight changes, until you reset them.',
        'calculated_calories' => 'Calculated target',
        'reset' => 'Reset to calculated',
        'reset_done' => 'Targets are calculated automatically again.',
    ],

    'custom' => [
        'start' => 'Customise targets',
        'edit' => 'Edit my targets',
        'save' => 'Save my targets',
        'saved' => 'Your custom targets are saved.',
        'floor_hint' => 'Minimum for you: :min kcal.',
        'macro_sum' => 'Calories from your macros',
        'calorie_target' => 'Calorie target',
        'sum_hint' => 'The two should be within 10% of each other. Protein and net carbs count 4 kcal per gram, fat 9.',
    ],

    'body' => [
        'title' => 'Body data and goal',
        'age' => 'Age',
        'years' => 'years',
        'saved' => 'Saved. Your targets were recalculated.',
        'saved_custom' => 'Saved. Your custom targets were kept.',
        'preview_title' => 'Targets after saving',
        'preview_invalid' => 'Complete the fields above to see your new targets.',
        'preview_custom' => 'Your targets are customised, so they will stay as they are. Only the safe minimum follows your new data.',
        'preview_was' => 'now',
        'custom_conflict_title' => 'Your custom targets no longer fit',
        'custom_conflict_text' => 'Cancel, then adjust your targets or reset them to the calculated values before changing your body data.',
    ],

    'account' => [
        'title' => 'Account',
        'saved' => 'Account details saved.',
    ],

    'preferences' => [
        'title' => 'Language and appearance',
        'language_saved' => 'Language updated.',
    ],

    'password' => [
        'title' => 'Change password',
        'current' => 'Current password',
        'new' => 'New password',
        'submit' => 'Update password',
        'saved' => 'Password updated.',
    ],

    'delete' => [
        'title' => 'Delete account',
        'text' => 'Permanently removes your account with your diary, weigh-ins, custom foods, recipes and plans.',
        'open' => 'Delete my account',
        'confirm_title' => 'Delete your account?',
        'confirm_text' => 'This cannot be undone. Enter your password to confirm.',
        'submit' => 'Delete permanently',
        'done' => 'Your account was deleted.',
    ],

];
