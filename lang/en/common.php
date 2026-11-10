<?php

// Strings shared by the UI components and by every feature area.

return [

    // Actions
    'save' => 'Save',
    'cancel' => 'Cancel',
    'close' => 'Close',
    'delete' => 'Delete',
    'edit' => 'Edit',
    'add' => 'Add',
    'confirm' => 'Confirm',
    'back' => 'Back',
    'next' => 'Next',
    'previous' => 'Previous',
    'search' => 'Search',
    'retry' => 'Try again',
    'done' => 'Done',
    'view_all' => 'View all',
    'increase' => 'Increase',
    'decrease' => 'Decrease',

    // States
    'loading' => 'Loading…',
    'optional' => 'Optional',
    'required' => 'Required',
    'none' => 'None',
    'unknown' => 'Unknown',

    // Dates
    'today' => 'Today',
    'yesterday' => 'Yesterday',
    'tomorrow' => 'Tomorrow',

    // Macros / progress
    'remaining' => 'Remaining',
    'left' => ':value left',
    'over' => ':value over',
    'over_target' => 'Over target',
    'of_target' => 'of :target',
    'target' => 'Target',
    'no_target' => 'No target',
    'at_least' => 'At least',
    'at_least_hint' => 'Some foods are missing this value, so the real total may be higher.',

    // Data quality
    'estimate' => 'Estimate',
    'estimate_hint' => 'An estimated value, not from a verified source.',
    'confidence' => 'Data accuracy',

    // Units (display)
    'units' => [
        'g' => 'g',
        'kcal' => 'kcal',
        'kg' => 'kg',
        'percent' => '%',
    ],

    // Macro names
    'macros' => [
        'calories' => 'Calories',
        'net_carbs' => 'Net carbs',
        'carbs' => 'Carbs',
        'protein' => 'Protein',
        'fat' => 'Fat',
        'fiber' => 'Fiber',
    ],

    // Toast types (screen-reader prefix)
    'toast' => [
        'success' => 'Done',
        'error' => 'Error',
        'warning' => 'Warning',
        'info' => 'Note',
        'dismiss' => 'Dismiss notification',
    ],

    // Charts
    'chart' => [
        'empty' => 'Not enough data to show yet.',
        'target_line' => 'Target: :value',
        'range' => 'From :min to :max',
    ],

    'errors' => [
        'generic' => 'Something went wrong. Please try again.',
    ],

];
