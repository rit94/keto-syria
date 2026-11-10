<?php

return [

    'title' => 'Getting started',
    'progress' => 'Setup progress',
    'step_of' => 'Step :step of :total',
    'start' => 'Get started',
    'finish' => 'Confirm and start',
    'done' => 'Your daily targets are ready.',

    'units' => [
        'cm' => 'cm',
    ],

    'welcome' => [
        'heading' => 'Welcome, :name',
        'text' => 'A few short questions and we will work out daily targets that fit you.',
        'language' => 'Which language do you prefer?',
        'points' => [
            'time' => 'It takes about two minutes.',
            'private' => 'Your answers stay in your account and are only used to calculate your targets.',
            'change' => 'You can change anything later from your profile.',
        ],
    ],

    'about' => [
        'heading' => 'About you',
        'text' => 'Energy needs differ with sex and age, so we start here.',
        'sex' => 'Sex',
        'sex_hint' => 'Used only in the energy equation.',
        'birth_date' => 'Date of birth',
    ],

    'body' => [
        'heading' => 'Your measurements',
        'text' => 'An honest number today gives you a better target. You can update your weight any time.',
        'height' => 'Height',
        'weight' => 'Current weight',
        'goal_weight' => 'Goal weight',
        'goal_weight_hint' => 'Leave it empty if you do not have a number in mind.',
    ],

    'activity' => [
        'heading' => 'How active is a normal day?',
        'text' => 'Pick the description closest to your usual week. If you are between two, choose the lower one.',
        'levels' => [
            'sedentary' => 'Desk work or sitting most of the day, little walking, no regular exercise.',
            'light' => 'On your feet part of the day (teaching, a shop, housework) or light exercise 1–3 days a week.',
            'moderate' => 'Moving most of the day, or moderate exercise 3–5 days a week.',
            'active' => 'Physical work (construction, farming, deliveries on foot) or hard training 6–7 days a week.',
            'very_active' => 'Heavy physical work plus daily training, or two training sessions a day.',
        ],
    ],

    'goal' => [
        'heading' => 'What are you aiming for?',
        'goal_label' => 'Your goal',
        'goals' => [
            'lose' => 'A moderate calorie deficit — steady, sustainable loss.',
            'maintain' => 'Eat what you burn and keep your weight stable.',
            'gain' => 'A small calorie surplus to gain gradually.',
        ],
        'approach_label' => 'How low-carb do you want to go?',
        'approach_text' => 'Net carbs are total carbs minus fibre. You can switch approach later.',
        'approaches' => [
            'strict_keto' => 'The quickest way into ketosis. Carbs come almost only from leafy vegetables.',
            'standard_keto' => 'The classic keto balance that most people can keep up day to day.',
            'liberal_low_carb' => 'More room for vegetables, yogurt and a little fruit. Gentler, but you may not stay in ketosis.',
        ],
        'net_carbs_per_day' => ':grams g net carbs / day',
    ],

    'health' => [
        'required' => 'Tick what applies to you, or choose "None of these apply to me".',
        'advice_title' => 'Please talk to your doctor first',
        'continue_note' => 'You can still continue and see your numbers — we will keep this reminder in your profile.',
        'privacy' => 'We do not store which item you ticked — only whether medical follow-up is advised.',
    ],

    'result' => [
        'heading' => 'Your daily targets',
        'text' => 'Calculated from your answers. Net carbs are the number to watch most closely.',
        'how' => 'How this was calculated',
        'bmr' => 'Resting energy (BMR)',
        'tdee' => 'Daily energy needs (TDEE)',
        'adjustment' => 'Adjustment for your goal',
        'floor' => 'Safe minimum',
        'customise' => 'I want to set my own numbers',
        'customise_text' => 'Start from the calculated values and change what you need. Calories cannot go below your safe minimum.',
    ],

];
