<?php

return [
    'photo_title' => 'Photo',

    'errors' => [
        'not_configured' => 'The smart assistant is not set up on this server yet. You can still log your food manually.',
        'unavailable' => 'The assistant could not be reached right now. Please try again in a moment.',
        'overloaded' => 'The assistant is very busy right now. Please try again in a minute.',
        'refused' => 'Sorry, I cannot help with this request. Try asking in a different way, or ask about your food and your plan.',
        'rate_limited' => 'You are sending messages too quickly. Please try again in :seconds seconds.',
        'rate_limited_day' => 'You have reached today\'s limit for the assistant. You can keep logging manually and come back tomorrow.',
        'forbidden' => 'You do not have access to this.',
        'too_many_steps' => 'I could not finish this request in the steps I am allowed. Nothing was saved. Please try a shorter or more specific request.',
        'cut_off' => 'My reply was cut off before I finished. Nothing was saved. Please ask again, a bit more briefly.',
        'empty_reply' => 'I did not manage to produce an answer. Please try again.',
        'empty_message' => 'Write a message first.',
        'message_too_long' => 'The message is too long (maximum :max characters).',
        'image_invalid' => 'This image cannot be used. Please choose a JPEG, PNG or WebP photo.',
        'image_too_large' => 'The image is too large (maximum :max MB).',
    ],

    'proposals' => [
        'invalid' => 'This cannot be saved as it is. Please check the details.',
        'not_saved_yet' => 'Not saved yet. Review it, then confirm.',

        'summary' => [
            'log_meal' => 'Log :count item(s) to :meal on :date',
            'update_log_entry' => 'Change the entry ":name"',
            'delete_log_entry' => 'Delete the entry ":name"',
            'save_recipe' => 'Save the recipe ":name"',
            'meal_plan' => 'Save the meal plan ":name" (:days days from :date)',
        ],

        'status' => [
            'pending' => 'Waiting for your confirmation',
            'processing' => 'Saving…',
            'confirmed' => 'Saved',
            'rejected' => 'Declined',
            'failed' => 'Could not be saved',
            'expired' => 'Expired',
        ],

        'actions' => [
            'confirm' => 'Confirm',
            'reject' => 'Decline',
            'edit' => 'Edit',
        ],

        'errors' => [
            'already_resolved' => 'This suggestion has already been handled.',
            'expired' => 'This suggestion has expired. Ask the coach to prepare it again.',
            'entry_not_found' => 'This diary entry was not found.',
            'invalid_date' => 'The date is not valid.',
            'save_failed' => 'Something went wrong while saving. Nothing was saved.',
        ],
    ],

    'draft' => [
        'status' => [
            'resolved' => 'Ready',
            'ambiguous' => 'Please check the food',
            'unresolved' => 'Food not found',
            'needs_quantity' => 'Amount needed',
        ],

        'issues' => [
            'no_match' => 'We could not find this food. Pick it from the list or add it as a custom ingredient.',
            'food_not_accessible' => 'This food is no longer available. Pick another one.',
            'ambiguous_match' => 'More than one food could match. Check that the right one is selected.',
            'estimated_values' => 'The values for this food are approximate.',
            'low_confidence' => 'The data for this food is of low confidence.',
            'visual_estimate' => 'The amount was estimated from the photo. Correct it if you know the real amount.',
            'missing_quantity' => 'Enter the amount.',
            'missing_unit' => 'Choose the unit.',
            'unknown_unit' => 'This unit is not available for this food. Choose another unit.',
            'cooked_weight_unconvertible' => 'There is no cooked-weight data for this food, so it is calculated as raw weight. Enter the raw weight or pick a cooked variant.',
            'raw_or_cooked_unknown' => 'Was this weighed raw or after cooking? It is calculated as raw weight.',
            'not_calculable' => 'This amount cannot be calculated. Check the amount and unit.',
        ],

        'errors' => [
            'empty' => 'There is nothing to log yet.',
            'no_meal' => 'Choose which meal this is.',
            'unresolved_items' => 'Some items still need a food or an amount before they can be logged.',
        ],

        'photo_notice' => 'Amounts from a photo are estimates. Review them before logging.',
        'no_food_found' => 'We could not recognise any food. Describe what you ate in a few words.',
    ],
];
