<?php

// Sample copy for the style guide at /_ui (local only).

return [

    'title' => 'Style guide',
    'intro' => 'Every UI component, rendered in Arabic (RTL) and English (LTR). Usage notes are at the top of each file in resources/views/components.',
    'panel' => 'English · LTR',

    'sections' => [
        'colors' => 'Colour tokens',
        'type' => 'Type and numbers',
        'buttons' => 'Buttons',
        'icons' => 'Icons',
        'forms' => 'Form controls',
        'badges' => 'Badges',
        'surfaces' => 'Cards, rows and stats',
        'tabs' => 'Tabs',
        'feedback' => 'Alerts and toasts',
        'overlays' => 'Modal and bottom sheet',
        'states' => 'Empty and loading states',
        'macros' => 'Macro ring and bar',
        'charts' => 'Charts',
    ],

    'heading' => 'Today’s meals',
    'body' => 'Calm, readable text with generous spacing. Numbers always use Western digits.',
    'muted' => 'Secondary text for hints and metadata.',

    'primary' => 'Save meal',
    'secondary' => 'Cancel',
    'ghost' => 'Add food',
    'danger' => 'Delete',
    'subtle' => 'More',
    'disabled' => 'Disabled',
    'small' => 'Small',
    'large' => 'Large',

    'food_name' => 'Food name',
    'food_placeholder' => 'e.g. Labneh',
    'search_placeholder' => 'Search foods',
    'weight' => 'Weight',
    'quantity' => 'Quantity',
    'unit' => 'Unit',
    'choose_unit' => 'Choose a unit',
    'units' => ['g' => 'Gram', 'tbsp' => 'Tablespoon', 'cup' => 'Cup', 'piece' => 'Piece'],
    'notes' => 'Notes',
    'notes_hint' => 'Anything that helps you remember this meal.',
    'error_example' => 'Food name is required.',
    'remember' => 'Save as a favourite',

    'category' => 'Dairy',
    'new' => 'New',
    'over_limit' => 'Over limit',

    'card_title' => 'Breakfast',
    'card_subtitle' => '3 items',
    'row_1' => 'Labneh with olive oil',
    'row_1_sub' => '2 tablespoons · 60 g',
    'row_2' => 'Boiled eggs',
    'row_2_sub' => '2 pieces · 100 g',
    'row_3' => 'Cucumber',
    'row_3_sub' => '1 medium · 120 g',
    'net_carbs_short' => 'net carbs',

    'stat_weight' => 'Current weight',
    'stat_calories' => 'Calories today',
    'stat_hint' => 'since last week',
    'stat_of' => 'of 1,800',

    'tab_macros' => 'Macros',
    'tab_weight' => 'Weight',
    'tab_history' => 'History',
    'tab_content' => 'Content of the “:tab” tab.',

    'alert_info' => 'Net carbs = total carbs minus fibre.',
    'alert_warning_title' => 'Check with your doctor',
    'alert_warning' => 'If you take diabetes or blood-pressure medication, talk to your doctor before starting keto.',
    'alert_danger' => 'We could not save your meal. Please try again.',
    'toast_success' => 'Meal saved.',
    'toast_error' => 'Could not connect. Check your internet.',
    'show_success' => 'Show success toast',
    'show_error' => 'Show error toast',

    'open_modal' => 'Open modal',
    'open_sheet' => 'Open bottom sheet',
    'modal_title' => 'Delete this entry?',
    'modal_text' => 'It will be removed from today’s log. You cannot undo this.',
    'sheet_title' => 'Add to breakfast',

    'empty_title' => 'No meals logged yet',
    'empty_text' => 'Log your first meal to see your day take shape.',
    'empty_action' => 'Log a meal',

    'weight_trend' => 'Weight, last 8 weeks',
    'carbs_week' => 'Net carbs, last 7 days',
    'week' => 'W:n',
    'days' => ['Sat', 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri'],

];
