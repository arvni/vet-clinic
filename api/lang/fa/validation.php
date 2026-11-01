<?php

// Minimal Persian validation messages for rules used by the API.
return [
    'required' => 'فیلد :attribute الزامی است.',
    'string' => 'فیلد :attribute باید متن باشد.',
    'array' => 'فیلد :attribute باید آرایه باشد.',
    'email' => 'فیلد :attribute باید ایمیل معتبر باشد.',
    'in' => 'مقدار :attribute معتبر نیست.',
    'digits' => 'فیلد :attribute باید :digits رقم باشد.',
    'size' => ['string' => 'فیلد :attribute باید :size کاراکتر باشد.'],
    'max' => ['string' => 'فیلد :attribute نباید بیش از :max کاراکتر باشد.', 'array' => 'فیلد :attribute نباید بیش از :max مورد داشته باشد.'],
    'min' => ['string' => 'فیلد :attribute باید حداقل :min کاراکتر باشد.', 'array' => 'فیلد :attribute باید حداقل :min مورد داشته باشد.'],
    'regex' => 'قالب :attribute معتبر نیست.',
    'unique' => ':attribute قبلاً ثبت شده است.',
    'password' => [
        'letters' => 'رمز عبور باید حداقل یک حرف داشته باشد.',
        'numbers' => 'رمز عبور باید حداقل یک عدد داشته باشد.',
    ],
    'attributes' => [
        'mobile' => 'شماره موبایل',
        'code' => 'کد تأیید',
        'name' => 'نام',
        'email' => 'ایمیل',
        'password' => 'رمز عبور',
        'roles' => 'نقش‌ها',
        'locale' => 'زبان',
        'national_code' => 'کد ملی',
    ],
];
