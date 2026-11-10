<?php

// Smart food logging screen (app/Livewire/SmartLog). Draft status / issue texts live in coach.php (draft.*).

return [
    'title' => 'Log a meal',
    'ask_coach' => 'Ask the coach',
    'footer' => 'Prefer to chat about it?',
    'logged_toast' => 'Meal logged.',

    'not_configured' => [
        'title' => 'Smart logging is not set up yet',
        'text' => 'The assistant that understands descriptions and photos is not available on this server yet. You can still log your food by hand.',
        'manual' => 'Log food manually',
        'foods' => 'Browse foods',
    ],

    'mode' => [
        'label' => 'How do you want to describe it?',
        'text' => 'Text',
        'voice' => 'Voice',
        'photo' => 'Photo',
    ],

    'text' => [
        'label' => 'What did you eat?',
        'placeholder' => 'e.g. two eggs with a little cheese and a spoon of ghee',
        'hint' => 'Say it the way you would to a friend, with amounts if you know them.',
    ],

    'voice' => [
        'tap' => 'Tap the microphone and say what you ate',
        'listening' => 'Listening… tap again to stop',
        'unsupported' => 'This browser cannot take voice input. Type what you ate below instead.',
        'transcript' => 'What we heard',
        'transcript_placeholder' => 'Your words appear here. You can correct them before continuing.',
        'hint' => 'Check the text and fix any mistakes. Nothing is logged until you confirm.',
    ],

    'photo' => [
        'intro' => 'Take a photo of your plate or choose one from your gallery. Amounts from a photo are estimates, so you will review them next. The photo is only used for this analysis and is not stored.',
        'take' => 'Take a photo',
        'choose' => 'Choose from gallery',
        'uploading' => 'Uploading the photo…',
        'ready' => 'Photo ready',
        'remove' => 'Remove',
        'preview_alt' => 'Preview of the photo you chose',
        'required' => 'Add a photo first.',
        'note' => 'Note about the meal',
        'note_placeholder' => 'e.g. grilled chicken, about 200 g',
    ],

    'analyze' => 'Understand and prepare the meal',
    'analyze_photo' => 'Analyse the photo',
    'analyzing' => 'Working on it, this can take a few seconds…',

    'questions' => [
        'title' => 'A few questions',
        'placeholder' => 'Your answer',
        'answer' => 'Answer',
        'hint' => 'Answering prepares the meal again. You can also fix the items directly below.',
    ],

    'meal' => 'Meal',
    'meal_guessed' => 'We chose this meal from the time of day. Change it if it is wrong.',
    'date' => 'Date',

    'items' => [
        'title' => 'Review the items',
        'you_said' => 'You said',
        'no_food' => 'No food chosen yet',
        'cooking_fat' => 'Cooking fat',
        'remove' => 'Remove :name',
        'confirm_food' => 'Yes, this is the right food',
        'did_you_mean' => 'Or did you mean',
        'pick_one' => 'Pick the closest food',
        'change_food' => 'Choose another food',
        'search_food' => 'Search for the food',
        'quantity' => 'Amount',
        'unit' => 'Unit',
        'choose_unit' => 'Choose a unit',
        'weighed_cooked' => 'Weighed after cooking',
        'weighed_cooked_hint' => 'Tick this if the weight is of the cooked food, not the raw food.',
        'add' => 'Add a food',
    ],

    'picker' => [
        'title' => 'Choose a food',
    ],

    'totals' => [
        'title' => 'Total of this meal',
        'partial' => 'The total leaves out the items that still need an amount or a food.',
        'no_targets' => 'Set your daily targets to see what is left for the day.',
    ],

    'review' => [
        'acknowledge' => 'I checked the foods and amounts',
    ],

    'errors' => [
        'confirm_ambiguous' => 'Confirm or change the foods marked "Please check the food" before logging.',
        'acknowledge' => 'Tick the box to confirm you checked the amounts.',
        'photo_gone' => 'The photo is no longer available. Fix the items by hand or start over.',
    ],

    'confirm' => 'Confirm and log',
    'start_over' => 'Start over',

    'done' => [
        'title' => 'Logged',
        'text' => ':count item(s) added to :meal, :date.',
        'open_today' => 'Open today',
        'log_another' => 'Log another meal',
    ],
];
