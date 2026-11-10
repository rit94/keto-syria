<?php

return [

    /*
    |--------------------------------------------------------------------------
    | رسائل التحقق من صحة البيانات
    |--------------------------------------------------------------------------
    */

    'accepted' => 'يجب قبول :attribute.',
    'accepted_if' => 'يجب قبول :attribute عندما يكون :other هو :value.',
    'active_url' => ':attribute يجب أن يكون رابطاً صالحاً.',
    'after' => ':attribute يجب أن يكون تاريخاً بعد :date.',
    'after_or_equal' => ':attribute يجب أن يكون تاريخاً بعد أو يساوي :date.',
    'alpha' => ':attribute يجب أن يحتوي على أحرف فقط.',
    'alpha_dash' => ':attribute يجب أن يحتوي على أحرف وأرقام وشرطات فقط.',
    'alpha_num' => ':attribute يجب أن يحتوي على أحرف وأرقام فقط.',
    'any_of' => 'قيمة :attribute غير صالحة.',
    'array' => ':attribute يجب أن يكون قائمة.',
    'ascii' => ':attribute يجب أن يحتوي على أحرف وأرقام ورموز لاتينية فقط.',
    'before' => ':attribute يجب أن يكون تاريخاً قبل :date.',
    'before_or_equal' => ':attribute يجب أن يكون تاريخاً قبل أو يساوي :date.',
    'between' => [
        'array' => ':attribute يجب أن يحتوي على ما بين :min و :max عنصراً.',
        'file' => 'حجم :attribute يجب أن يكون بين :min و :max كيلوبايت.',
        'numeric' => ':attribute يجب أن يكون بين :min و :max.',
        'string' => ':attribute يجب أن يكون بين :min و :max حرفاً.',
    ],
    'boolean' => ':attribute يجب أن يكون نعم أو لا.',
    'can' => ':attribute يحتوي على قيمة غير مسموح بها.',
    'confirmed' => 'تأكيد :attribute غير مطابق.',
    'contains' => ':attribute ينقصه قيمة مطلوبة.',
    'current_password' => 'كلمة المرور غير صحيحة.',
    'date' => ':attribute يجب أن يكون تاريخاً صالحاً.',
    'date_equals' => ':attribute يجب أن يكون تاريخاً مطابقاً لـ :date.',
    'date_format' => ':attribute يجب أن يطابق الصيغة :format.',
    'decimal' => ':attribute يجب أن يحتوي على :decimal منازل عشرية.',
    'declined' => 'يجب رفض :attribute.',
    'declined_if' => 'يجب رفض :attribute عندما يكون :other هو :value.',
    'different' => ':attribute و :other يجب أن يكونا مختلفين.',
    'digits' => ':attribute يجب أن يتكون من :digits أرقام.',
    'digits_between' => ':attribute يجب أن يكون بين :min و :max رقماً.',
    'dimensions' => 'أبعاد صورة :attribute غير صالحة.',
    'distinct' => ':attribute يحتوي على قيمة مكررة.',
    'doesnt_contain' => ':attribute يجب ألا يحتوي على أي من القيم التالية: :values.',
    'doesnt_end_with' => ':attribute يجب ألا ينتهي بأحد القيم التالية: :values.',
    'doesnt_start_with' => ':attribute يجب ألا يبدأ بأحد القيم التالية: :values.',
    'email' => ':attribute يجب أن يكون بريداً إلكترونياً صالحاً.',
    'encoding' => ':attribute يجب أن يكون بترميز :encoding.',
    'ends_with' => ':attribute يجب أن ينتهي بأحد القيم التالية: :values.',
    'enum' => 'القيمة المختارة لـ :attribute غير صالحة.',
    'exists' => 'القيمة المختارة لـ :attribute غير صالحة.',
    'extensions' => ':attribute يجب أن يكون بأحد الامتدادات التالية: :values.',
    'file' => ':attribute يجب أن يكون ملفاً.',
    'filled' => ':attribute يجب أن يحتوي على قيمة.',
    'gt' => [
        'array' => ':attribute يجب أن يحتوي على أكثر من :value عنصراً.',
        'file' => 'حجم :attribute يجب أن يكون أكبر من :value كيلوبايت.',
        'numeric' => ':attribute يجب أن يكون أكبر من :value.',
        'string' => ':attribute يجب أن يكون أطول من :value حرفاً.',
    ],
    'gte' => [
        'array' => ':attribute يجب أن يحتوي على :value عنصراً أو أكثر.',
        'file' => 'حجم :attribute يجب أن يكون :value كيلوبايت أو أكثر.',
        'numeric' => ':attribute يجب أن يكون :value أو أكثر.',
        'string' => ':attribute يجب أن يكون :value حرفاً أو أكثر.',
    ],
    'hex_color' => ':attribute يجب أن يكون لوناً بصيغة hex صالحة.',
    'image' => ':attribute يجب أن يكون صورة.',
    'in' => 'القيمة المختارة لـ :attribute غير صالحة.',
    'in_array' => ':attribute يجب أن يكون موجوداً في :other.',
    'in_array_keys' => ':attribute يجب أن يحتوي على أحد المفاتيح التالية: :values.',
    'integer' => ':attribute يجب أن يكون عدداً صحيحاً.',
    'ip' => ':attribute يجب أن يكون عنوان IP صالحاً.',
    'ipv4' => ':attribute يجب أن يكون عنوان IPv4 صالحاً.',
    'ipv6' => ':attribute يجب أن يكون عنوان IPv6 صالحاً.',
    'json' => ':attribute يجب أن يكون نص JSON صالحاً.',
    'list' => ':attribute يجب أن يكون قائمة.',
    'lowercase' => ':attribute يجب أن يكون بأحرف صغيرة.',
    'lt' => [
        'array' => ':attribute يجب أن يحتوي على أقل من :value عنصراً.',
        'file' => 'حجم :attribute يجب أن يكون أقل من :value كيلوبايت.',
        'numeric' => ':attribute يجب أن يكون أقل من :value.',
        'string' => ':attribute يجب أن يكون أقصر من :value حرفاً.',
    ],
    'lte' => [
        'array' => ':attribute يجب ألا يحتوي على أكثر من :value عنصراً.',
        'file' => 'حجم :attribute يجب أن يكون :value كيلوبايت أو أقل.',
        'numeric' => ':attribute يجب أن يكون :value أو أقل.',
        'string' => ':attribute يجب أن يكون :value حرفاً أو أقل.',
    ],
    'mac_address' => ':attribute يجب أن يكون عنوان MAC صالحاً.',
    'max' => [
        'array' => ':attribute يجب ألا يحتوي على أكثر من :max عنصراً.',
        'file' => 'حجم :attribute يجب ألا يتجاوز :max كيلوبايت.',
        'numeric' => ':attribute يجب ألا يكون أكبر من :max.',
        'string' => ':attribute يجب ألا يتجاوز :max حرفاً.',
    ],
    'max_digits' => ':attribute يجب ألا يتجاوز :max رقماً.',
    'mimes' => ':attribute يجب أن يكون ملفاً من نوع: :values.',
    'mimetypes' => ':attribute يجب أن يكون ملفاً من نوع: :values.',
    'min' => [
        'array' => ':attribute يجب أن يحتوي على :min عنصراً على الأقل.',
        'file' => 'حجم :attribute يجب أن يكون :min كيلوبايت على الأقل.',
        'numeric' => ':attribute يجب أن يكون :min على الأقل.',
        'string' => ':attribute يجب أن يكون :min أحرف على الأقل.',
    ],
    'min_digits' => ':attribute يجب أن يتكون من :min رقماً على الأقل.',
    'missing' => ':attribute يجب ألا يكون موجوداً.',
    'missing_if' => ':attribute يجب ألا يكون موجوداً عندما يكون :other هو :value.',
    'missing_unless' => ':attribute يجب ألا يكون موجوداً إلا إذا كان :other هو :value.',
    'missing_with' => ':attribute يجب ألا يكون موجوداً عند وجود :values.',
    'missing_with_all' => ':attribute يجب ألا يكون موجوداً عند وجود :values.',
    'multiple_of' => ':attribute يجب أن يكون من مضاعفات :value.',
    'not_in' => 'القيمة المختارة لـ :attribute غير صالحة.',
    'not_regex' => 'صيغة :attribute غير صالحة.',
    'numeric' => ':attribute يجب أن يكون رقماً.',
    'password' => [
        'letters' => ':attribute يجب أن يحتوي على حرف واحد على الأقل.',
        'mixed' => ':attribute يجب أن يحتوي على حرف كبير وحرف صغير على الأقل.',
        'numbers' => ':attribute يجب أن يحتوي على رقم واحد على الأقل.',
        'symbols' => ':attribute يجب أن يحتوي على رمز واحد على الأقل.',
        'uncompromised' => ':attribute ظهر في تسريب بيانات. الرجاء اختيار قيمة أخرى.',
    ],
    'present' => ':attribute يجب أن يكون موجوداً.',
    'present_if' => ':attribute يجب أن يكون موجوداً عندما يكون :other هو :value.',
    'present_unless' => ':attribute يجب أن يكون موجوداً إلا إذا كان :other هو :value.',
    'present_with' => ':attribute يجب أن يكون موجوداً عند وجود :values.',
    'present_with_all' => ':attribute يجب أن يكون موجوداً عند وجود :values.',
    'prohibited' => ':attribute غير مسموح به.',
    'prohibited_if' => ':attribute غير مسموح به عندما يكون :other هو :value.',
    'prohibited_if_accepted' => ':attribute غير مسموح به عند قبول :other.',
    'prohibited_if_declined' => ':attribute غير مسموح به عند رفض :other.',
    'prohibited_unless' => ':attribute غير مسموح به إلا إذا كان :other ضمن :values.',
    'prohibits' => 'وجود :attribute يمنع وجود :other.',
    'regex' => 'صيغة :attribute غير صالحة.',
    'required' => ':attribute مطلوب.',
    'required_array_keys' => ':attribute يجب أن يحتوي على مدخلات لـ: :values.',
    'required_if' => ':attribute مطلوب عندما يكون :other هو :value.',
    'required_if_accepted' => ':attribute مطلوب عند قبول :other.',
    'required_if_declined' => ':attribute مطلوب عند رفض :other.',
    'required_unless' => ':attribute مطلوب إلا إذا كان :other ضمن :values.',
    'required_with' => ':attribute مطلوب عند وجود :values.',
    'required_with_all' => ':attribute مطلوب عند وجود :values.',
    'required_without' => ':attribute مطلوب عند عدم وجود :values.',
    'required_without_all' => ':attribute مطلوب عند عدم وجود أي من :values.',
    'same' => ':attribute يجب أن يطابق :other.',
    'size' => [
        'array' => ':attribute يجب أن يحتوي على :size عنصراً.',
        'file' => 'حجم :attribute يجب أن يكون :size كيلوبايت.',
        'numeric' => ':attribute يجب أن يكون :size.',
        'string' => ':attribute يجب أن يكون :size حرفاً.',
    ],
    'starts_with' => ':attribute يجب أن يبدأ بأحد القيم التالية: :values.',
    'string' => ':attribute يجب أن يكون نصاً.',
    'timezone' => ':attribute يجب أن يكون منطقة زمنية صالحة.',
    'unique' => ':attribute مستخدم من قبل.',
    'uploaded' => 'فشل رفع :attribute.',
    'uppercase' => ':attribute يجب أن يكون بأحرف كبيرة.',
    'url' => ':attribute يجب أن يكون رابطاً صالحاً.',
    'ulid' => ':attribute يجب أن يكون ULID صالحاً.',
    'uuid' => ':attribute يجب أن يكون UUID صالحاً.',

    /*
    |--------------------------------------------------------------------------
    | رسائل مخصصة
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | أسماء الحقول
    |--------------------------------------------------------------------------
    |
    | Shared field names. Feature areas pass their own names with
    | validationAttributes() / the third argument of validate().
    |
    */

    'attributes' => [
        'name' => 'الاسم',
        'email' => 'البريد الإلكتروني',
        'password' => 'كلمة المرور',
        'password_confirmation' => 'تأكيد كلمة المرور',
        'current_password' => 'كلمة المرور الحالية',
        'locale' => 'اللغة',
        'quantity' => 'الكمية',
        'unit' => 'الوحدة',
        'date' => 'التاريخ',
        'meal' => 'الوجبة',
        'note' => 'الملاحظة',
        'description' => 'الوصف',
        'weight_kg' => 'الوزن',
        'height_cm' => 'الطول',
        'birth_date' => 'تاريخ الميلاد',
        'servings' => 'عدد الحصص',
    ],

];
