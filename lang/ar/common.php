<?php

// Strings shared by the UI components and by every feature area.

return [

    // Actions
    'save' => 'حفظ',
    'cancel' => 'إلغاء',
    'close' => 'إغلاق',
    'delete' => 'حذف',
    'edit' => 'تعديل',
    'add' => 'إضافة',
    'confirm' => 'تأكيد',
    'back' => 'رجوع',
    'next' => 'التالي',
    'previous' => 'السابق',
    'search' => 'بحث',
    'retry' => 'إعادة المحاولة',
    'done' => 'تم',
    'view_all' => 'عرض الكل',
    'increase' => 'زيادة',
    'decrease' => 'إنقاص',

    // States
    'loading' => 'جارٍ التحميل…',
    'optional' => 'اختياري',
    'required' => 'مطلوب',
    'none' => 'لا يوجد',
    'unknown' => 'غير معروف',

    // Dates
    'today' => 'اليوم',
    'yesterday' => 'أمس',
    'tomorrow' => 'غداً',

    // Macros / progress
    'remaining' => 'المتبقي',
    'left' => 'متبقٍ :value',
    'over' => 'زيادة :value',
    'over_target' => 'تجاوزت الهدف',
    'of_target' => 'من :target',
    'target' => 'الهدف',
    'no_target' => 'بدون هدف',
    'at_least' => 'على الأقل',
    'at_least_hint' => 'بعض الأطعمة ينقصها هذه القيمة، فالمجموع الفعلي قد يكون أعلى.',

    // Data quality
    'estimate' => 'تقديري',
    'estimate_hint' => 'قيمة تقديرية وليست من مصدر موثّق.',
    'confidence' => 'دقة البيانات',

    // Units (display)
    'units' => [
        'g' => 'غ',
        'kcal' => 'سعرة',
        'kg' => 'كغ',
        'percent' => '%',
    ],

    // Macro names
    'macros' => [
        'calories' => 'السعرات',
        'net_carbs' => 'الكربوهيدرات الصافية',
        'carbs' => 'الكربوهيدرات',
        'protein' => 'البروتين',
        'fat' => 'الدهون',
        'fiber' => 'الألياف',
    ],

    // Toast types (screen-reader prefix)
    'toast' => [
        'success' => 'تم',
        'error' => 'خطأ',
        'warning' => 'تنبيه',
        'info' => 'معلومة',
        'dismiss' => 'إخفاء الإشعار',
    ],

    // Charts
    'chart' => [
        'empty' => 'لا توجد بيانات كافية للعرض بعد.',
        'target_line' => 'الهدف: :value',
        'range' => 'من :min إلى :max',
    ],

    'errors' => [
        'generic' => 'حدث خطأ غير متوقع. حاول مرة أخرى.',
    ],

];
