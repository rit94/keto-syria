<?php

// Coach chat screen (app/Livewire/Coach). Backend messages live in coach.php.

return [
    'title' => 'Coach',
    'new_chat' => 'New conversation',
    'conversations' => 'Conversations',
    'no_conversations' => 'No conversations yet. Ask your first question.',
    'untitled' => 'New conversation',
    'thread_label' => 'Conversation with your coach',
    'you' => 'You',
    'coach' => 'Coach',
    'photo_sent' => 'Photo',
    'thinking' => 'The coach is thinking…',
    'conversation_deleted' => 'Conversation deleted.',

    'delete' => [
        'action' => 'Delete conversation',
        'title' => 'Delete this conversation?',
        'text' => 'The messages are removed for good. Food you already logged stays in your diary.',
    ],

    'empty' => [
        'title' => 'Ask your keto coach',
        'text' => 'Ask about what to eat, log a meal by describing it, or have a recipe or a meal plan prepared. Nothing is saved until you confirm it.',
    ],

    'starters' => [
        'tonight' => [
            'label' => 'What can I eat tonight with my remaining macros?',
            'prompt' => 'What can I eat tonight with my remaining macros?',
        ],
        'eggplant' => [
            'label' => 'Make me a keto recipe with eggplant',
            'prompt' => 'Make me a keto recipe with eggplant.',
        ],
        'plan' => [
            'label' => 'Plan 3 days of meals for me',
            'prompt' => 'Plan my meals for the next 3 days.',
        ],
        'carbs' => [
            'label' => 'How am I doing on carbs today?',
            'prompt' => 'How am I doing on carbs today?',
        ],
    ],

    'composer' => [
        'label' => 'Message to the coach',
        'placeholder' => 'Ask or describe what you ate…',
        'send' => 'Send',
        'attach' => 'Attach a photo',
        'remove_photo' => 'Remove the photo',
        'photo_attached' => 'Photo attached',
        'disclaimer' => 'The coach is an AI assistant, not a doctor. Numbers come from the food database.',
    ],

    'voice' => [
        'toggle' => 'Dictate with the microphone',
        'listening' => 'Listening…',
        'denied' => 'Microphone access was blocked. Allow it in your browser settings.',
        'failed' => 'Could not understand the speech. Please try again or type.',
    ],

    'not_configured' => [
        'title' => 'The AI coach is not set up yet',
        'text' => 'The coach is not available on this server yet. You can still log your meals and browse foods by hand.',
        'manual' => 'Log food manually',
        'foods' => 'Browse foods',
    ],

    'custom_food' => [
        'title' => '":name" is not in the food database',
        'text' => 'Add it as your own ingredient with the values from its package label, then ask the coach again.',
        'cta' => 'Add the ingredient',
    ],

    'proposal' => [
        'type' => [
            'log_meal' => 'Log a meal',
            'update_log_entry' => 'Change a diary entry',
            'delete_log_entry' => 'Delete a diary entry',
            'save_recipe' => 'Save a recipe',
            'meal_plan' => 'Save a meal plan',
        ],

        'editing' => 'Edit before confirming',
        'confirm_delete' => 'Delete the entry',
        'cancel_edit' => 'Cancel editing',
        'saved' => 'Saved',
        'saved_toast' => 'Saved.',
        'failed' => 'Could not be saved',
        'failed_toast' => 'It could not be saved. Nothing was changed.',
        'nothing_saved' => 'Nothing was saved or changed.',
        'rejected_note' => 'You declined this. Nothing was saved.',
        'open_today' => 'Open today',
        'open_recipe' => 'Open the recipe',
        'open_plan' => 'Open the plan',

        'estimate_note' => 'Some values are estimates.',
        'fiber_unknown' => 'Fiber is not known for some foods, so net carbs may be lower than shown.',
        'remaining_after' => 'Left for the day after this',
        'left' => 'left',
        'over' => 'over',
        'total' => 'Total',
        'before' => 'Now',
        'after' => 'After the change',
        'after_nutrition' => 'Nutrition after the change',
        'removed_nutrition' => 'Nutrition that will be removed',
        'new_total' => 'New total',
        'per_serving' => 'Per serving',
        'whole_recipe' => 'Whole recipe',
        'servings' => 'servings',
        'finished_weight' => 'Finished weight',
        'weight_estimated' => 'estimated',
        'days' => 'days from',
        'items' => 'items',
        'weighed_cooked' => 'weighed after cooking',
        'unit_serving' => 'serving',
        'unknown_item' => 'Unavailable food',
        'fix_to_preview' => 'Fix the highlighted fields to see the numbers.',

        'meal' => 'Meal',
        'date' => 'Date',
        'quantity' => 'Amount',
        'unit' => 'Unit',
        'remove_row' => 'Remove :name',
        'recipe_name' => 'Recipe name',
        'servings_label' => 'Servings',
        'cooked_weight' => 'Cooked weight (optional)',
        'description' => 'Description',
        'instructions' => 'Instructions',
        'plan_name' => 'Plan name',
        'starts_on' => 'Starts on',
        'days_label' => 'Number of days',
    ],
];
