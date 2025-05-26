<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => ':attribute maydoni qabul qilinishi kerak.',
    'accepted_if' => ':other :value bo‘lsa, :attribute maydoni qabul qilinishi kerak.',
    'active_url' => ':attribute maydoni yaroqli URL bo‘lishi kerak.',
    'after' => ':attribute maydoni :date sanasidan keyingi sana bo‘lishi kerak.',
    'after_or_equal' => ':attribute maydoni :date sanasidan keyin yoki unga teng bo‘lishi kerak.',
    'alpha' => ':attribute faqat harflardan iborat bo‘lishi kerak.',
    'alpha_dash' => ':attribute faqat harflar, raqamlar, chiziqlar va pastki chiziqlardan iborat bo‘lishi kerak.',
    'alpha_num' => ':attribute faqat harflar va raqamlardan iborat bo‘lishi kerak.',
    'array' => ':attribute maydoni massiv bo‘lishi kerak.',
    'ascii' => ':attribute faqat bir baytli alfanumerik belgilar va simvollarni o‘z ichiga olishi kerak.',
    'before' => ':attribute maydoni :date sanasidan oldingi sana bo‘lishi kerak.',
    'before_or_equal' => ':attribute maydoni :date sanasidan oldin yoki unga teng bo‘lishi kerak.',
    'between' => [
        'array' => ':attribute maydoni :min dan :max tagacha elementlarga ega bo‘lishi kerak.',
        'file' => ':attribute maydoni :min dan :max kilobaytgacha bo‘lishi kerak.',
        'numeric' => ':attribute maydoni :min va :max orasida bo‘lishi kerak.',
        'string' => ':attribute maydoni :min dan :max belgigacha bo‘lishi kerak.',
    ],

    'boolean' => ':attribute maydoni faqat true (rost) yoki false (yolg‘on) qiymatga ega bo‘lishi kerak.',
    'can' => ':attribute maydonida ruxsat etilmagan qiymat mavjud.',
    'confirmed' => ':attribute tasdiqlanishi mos kelmadi.',
    'current_password' => 'Parol noto‘g‘ri.',
    'date' => ':attribute maydoni to‘g‘ri sana bo‘lishi kerak.',
    'date_equals' => ':attribute maydoni :date sanasiga teng bo‘lishi kerak.',
    'date_format' => ':attribute maydoni :format formatiga mos kelishi kerak.',
    'decimal' => ':attribute maydoni :decimal ta kasr o‘ringa ega bo‘lishi kerak.',
    'declined' => ':attribute maydoni rad etilishi kerak.',
    'declined_if' => ':other :value bo‘lsa, :attribute maydoni rad etilishi kerak.',
    'different' => ':attribute va :other maydonlari har xil bo‘lishi kerak.',
    'digits' => ':attribute maydoni :digits raqamdan iborat bo‘lishi kerak.',
    'digits_between' => ':attribute maydoni :min dan :max gacha raqam bo‘lishi kerak.',
    'dimensions' => ':attribute maydonidagi rasm o‘lchamlari noto‘g‘ri.',
    'distinct' => ':attribute maydonida takroriy qiymat mavjud.',
    'doesnt_end_with' => ':attribute maydoni quyidagilardan biri bilan tugamasligi kerak: :values.',
    'doesnt_start_with' => ':attribute maydoni quyidagilardan biri bilan boshlanmasligi kerak: :values.',
    'email' => ':attribute maydoni to‘g‘ri email manzil bo‘lishi kerak.',
    'ends_with' => ':attribute maydoni quyidagilardan biri bilan tugashi kerak: :values.',
    'enum' => 'Tanlangan :attribute noto‘g‘ri.',
    'exists' => 'Tanlangan :attribute mavjud emas.',
    'file' => ':attribute maydoni fayl bo‘lishi kerak.',
    'filled' => ':attribute maydoni to‘ldirilishi shart.',
    'gt' => [
         'array' => ':attribute maydonida :value tadan ortiq element bo‘lishi kerak.',
         'file' => ':attribute fayli :value kilobaytdan katta bo‘lishi kerak.',
          'numeric' => ':attribute maydoni :value dan katta bo‘lishi kerak.',
          'string' => ':attribute maydoni :value belgidan ko‘p bo‘lishi kerak.',
    ],
    'gte' => [
          'array' => ':attribute maydonida kamida :value ta element bo‘lishi kerak.',
          'file' => ':attribute maydoni hajmi kamida :value kilobayt bo‘lishi kerak.',
          'numeric' => ':attribute maydoni qiymati kamida :value bo‘lishi kerak.',
          'string' => ':attribute maydoni kamida :value ta belgidan iborat bo‘lishi kerak.',
    ],
    'image' => ':attribute maydoni rasm bo‘lishi kerak.',
    'in' => 'Tanlangan :attribute noto‘g‘ri.',
    'in_array' => ':attribute maydoni :other ichida mavjud bo‘lishi kerak.',
    'integer' => ':attribute butun son bo‘lishi kerak.',
    'ip' => ':attribute haqiqiy IP manzil bo‘lishi kerak.',
    'ipv4' => ':attribute haqiqiy IPv4 manzil bo‘lishi kerak.',
    'ipv6' => ':attribute haqiqiy IPv6 manzil bo‘lishi kerak.',
    'json' => ':attribute haqiqiy JSON matni bo‘lishi kerak.',
    'lowercase' => ':attribute faqat kichik harflarda bo‘lishi kerak.',
    'lt' => [
        'array' => ':attribute maydoni :value tadan kam elementdan iborat bo‘lishi kerak.',
        'file' => ':attribute fayli hajmi :value kilobaytdan kam bo‘lishi kerak.',
        'numeric' => ':attribute qiymati :value dan kam bo‘lishi kerak.',
        'string' => ':attribute matni :value belgidan kam bo‘lishi kerak.',
    ],
    'lte' => [
        'array' => ':attribute maydonida :value tadan ko‘p element bo‘lmasligi kerak.',
        'file' => ':attribute fayli hajmi :value kilobaytdan oshmasligi kerak.',
        'numeric' => ':attribute qiymati :value dan katta bo‘lmasligi kerak.',
        'string' => ':attribute matni :value belgidan oshmasligi kerak.',
    ],
    'mac_address' => ':attribute haqiqiy MAC manzil bo‘lishi kerak.',
    'max' => [
        'array' => ':attribute maydonida :max tadan ko‘p element bo‘lmasligi kerak.',
        'file' => ':attribute fayli hajmi :max kilobaytdan oshmasligi kerak.',
        'numeric' => ':attribute qiymati :max dan katta bo‘lmasligi kerak.',
        'string' => ':attribute matni :max belgidan oshmasligi kerak.',
    ],
  'max_digits' => ':attribute kiruvi :max ta raqamdan oshmasligi kerak.',
    'mimes' => ':attribute fayli quyidagi turdagi bo‘lishi kerak: :values.',
    'mimetypes' => ':attribute fayli quyidagi turdagi bo‘lishi kerak: :values.',
    'min' => [
        'array' => ':attribute kamida :min elementdan iborat bo‘lishi kerak.',
        'file' => ':attribute kamida :min kilobayt bo‘lishi kerak.',
        'numeric' => ':attribute kamida :min bo‘lishi kerak.',
        'string' => ':attribute kamida :min ta belgi bo‘lishi kerak.',
    ],
    'min_digits' => ':attribute kamida :min ta raqamdan iborat bo‘lishi kerak.',
    'missing' => ':attribute yo‘q bo‘lishi kerak.',
    'missing_if' => ':other :value bo‘lganda :attribute yo‘q bo‘lishi kerak.',
    'missing_unless' => 'Agar :other :value bo‘lmasa, :attribute yo‘q bo‘lishi kerak.',
    'missing_with' => ':values mavjud bo‘lganda :attribute yo‘q bo‘lishi kerak.',
    'missing_with_all' => ':values lar mavjud bo‘lsa, :attribute yo‘q bo‘lishi kerak.',
    'multiple_of' => ':attribute :value ga ko‘paytmasi bo‘lishi kerak.',
    'not_in' => 'Tanlangan :attribute noto‘g‘ri.',
    'not_regex' => ':attribute formati noto‘g‘ri.',
    'numeric' => ':attribute raqam bo‘lishi kerak.',
    'password' => [
        'letters' => ':attribute kamida bitta harfni o‘z ichiga olishi kerak.',
        'mixed' => ':attribute kichik va katta harflardan kamida bittadan o‘z ichiga olishi kerak.',
        'numbers' => ':attribute kamida bitta raqamni o‘z ichiga olishi kerak.',
        'symbols' => ':attribute kamida bitta maxsus belgini o‘z ichiga olishi kerak.',
        'uncompromised' => 'Kiritilgan :attribute ma’lumotlar bazasida sizib chiqqan. Iltimos, boshqa :attribute tanlang.',
    ],
       'phone' => ':attribute maydoni haqiqiy raqam bo‘lishi kerak.',
'present' => ':attribute maydoni mavjud bo‘lishi kerak.',
'prohibited' => ':attribute maydonidan foydalanish taqiqlangan.',
'prohibited_if' => ':other :value bo‘lsa, :attribute maydonidan foydalanish taqiqlanadi.',
'prohibited_unless' => 'Faqat :other :values ichida bo‘lsa, :attribute maydoniga ruxsat beriladi.',
'prohibits' => ':attribute maydoni :other maydonining bo‘lishiga to‘sqinlik qiladi.',
'regex' => ':attribute maydonining formati noto‘g‘ri.',
'required' => ':attribute maydoni to‘ldirilishi shart.',
'required_array_keys' => ':attribute maydoni quyidagilarni o‘z ichiga olishi kerak: :values.',
'required_if' => ':other :value bo‘lsa, :attribute maydoni to‘ldirilishi shart.',
'required_if_accepted' => ':other qabul qilinganda :attribute maydoni majburiy.',
'required_unless' => ':other :values ichida bo‘lmasa, :attribute maydoni majburiy.',
'required_with' => ':values mavjud bo‘lsa, :attribute maydoni to‘ldirilishi shart.',
'required_with_all' => ':values lar mavjud bo‘lsa, :attribute maydoni kerak.',
'required_without' => ':values mavjud bo‘lmaganda :attribute maydoni kerak.',
'required_without_all' => ':values larning hech biri mavjud bo‘lmasa, :attribute kerak.',
'same' => ':attribute va :other bir xil bo‘lishi kerak.',
'size' => [
    'array' => ':attribute maydonida :size ta element bo‘lishi kerak.',
    'file' => ':attribute fayli :size kilobayt bo‘lishi kerak.',
    'numeric' => ':attribute qiymati :size bo‘lishi kerak.',
    'string' => ':attribute maydoni :size ta belgidan iborat bo‘lishi kerak.'
],
'starts_with' => ':attribute quyidagilardan biri bilan boshlanishi kerak: :values.',
'string' => ':attribute matn ko‘rinishida bo‘lishi kerak.',
'timezone' => ':attribute haqiqiy vaqt mintaqasi bo‘lishi kerak.',
'unique' => 'Bu :attribute allaqachon band.',
'uploaded' => ':attribute yuklab bo‘lmadi.',
'uppercase' => ':attribute faqat bosh harflardan iborat bo‘lishi kerak.',
'url' => ':attribute haqiqiy URL bo‘lishi kerak.',
'ulid' => ':attribute haqiqiy ULID bo‘lishi kerak.',
'uuid' => ':attribute haqiqiy UUID bo‘lishi kerak.',
     
    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

'custom' => [
    'attribute-name' => [
        'rule-name' => 'maxsus xabar',
    ],
],
    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [],

];
