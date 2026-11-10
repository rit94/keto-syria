<?php

// Labels for app/Enums/* — read by each enum's label().

return [

    'meal' => [
        'breakfast' => 'الفطور',
        'lunch' => 'الغداء',
        'dinner' => 'العشاء',
        'snack' => 'وجبة خفيفة',
    ],

    'confidence' => [
        'high' => 'موثوق',
        'medium' => 'متوسط الدقة',
        'low' => 'تقريبي',
    ],

    'activity_level' => [
        'sedentary' => 'قليل الحركة',
        'light' => 'نشاط خفيف',
        'moderate' => 'نشاط متوسط',
        'active' => 'نشيط',
        'very_active' => 'نشيط جداً',
    ],

    'carb_basis' => [
        'total' => 'كربوهيدرات كلية',
        'net' => 'كربوهيدرات صافية',
    ],

    'diet_approach' => [
        'strict_keto' => 'كيتو صارم',
        'standard_keto' => 'كيتو معتدل',
        'liberal_low_carb' => 'قليل الكربوهيدرات',
    ],

    'food_category' => [
        'vegetables' => 'خضار',
        'fruits' => 'فواكه',
        'meat' => 'لحوم',
        'poultry' => 'دواجن',
        'fish' => 'أسماك',
        'eggs' => 'بيض',
        'dairy' => 'ألبان',
        'cheese' => 'أجبان',
        'fats' => 'دهون وزيوت',
        'nuts' => 'مكسرات وبذور',
        'herbs' => 'أعشاب وبهارات',
        'condiments' => 'صلصات ومنكّهات',
        'beverages' => 'مشروبات',
        'other' => 'أخرى',
    ],

    'food_state' => [
        'raw' => 'نيء',
        'cooked' => 'مطبوخ',
        'na' => 'جاهز',
    ],

    'log_source' => [
        'manual' => 'يدوي',
        'text' => 'نص',
        'voice' => 'صوت',
        'image' => 'صورة',
        'coach' => 'المساعد',
        'plan' => 'الخطة',
    ],

    'preparation' => [
        'drained' => 'مصفّى',
        'undrained' => 'مع السائل',
    ],

    'sex' => [
        'male' => 'ذكر',
        'female' => 'أنثى',
    ],

    'weight_goal' => [
        'lose' => 'إنقاص الوزن',
        'maintain' => 'المحافظة على الوزن',
        'gain' => 'زيادة الوزن',
    ],

];
