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

    'accepted' => 'Fusha :attribute duhet të pranohet.',
    'accepted_if' => 'Fusha :attribute duhet të pranohet kur :other është :value.',
    'active_url' => 'Fusha :attribute duhet të jetë një URL e vlefshme.',
    'after' => 'Fusha :attribute duhet të jetë një datë pas :date.',
    'after_or_equal' => 'Fusha :attribute duhet të jetë një datë pas ose e barabartë me :date.',
    'alpha' => 'Fusha :attribute duhet të përmbajë vetëm shkronja.',
    'alpha_dash' => 'Fusha :attribute duhet të përmbajë vetëm shkronja, numra, shënja të theksuara dhe nënvizime.',
    'alpha_num' => 'Fusha :attribute duhet të përmbajë vetëm shkronja dhe numra.',
    'array' => 'Fusha :attribute duhet të jetë një array.',
    'ascii' => 'Fusha :attribute duhet të përmbajë vetëm karaktere dhe simbole të një byte.',
    'before' => 'Fusha :attribute duhet të jetë një datë para :date.',
    'before_or_equal' => 'Fusha :attribute duhet të jetë një datë para ose e barabartë me :date.',
    'between' => [
        'array' => 'Fusha :attribute duhet të ketë midis :min dhe :max elemente.',
        'file' => 'Fusha :attribute duhet të jetë midis :min dhe :max kilobajtë.',
        'numeric' => 'Fusha :attribute duhet të jetë midis :min dhe :max.',
        'string' => 'Fusha :attribute duhet të ketë midis :min dhe :max karaktere.',
    ],
    'boolean' => 'Fusha :attribute duhet të jetë e vërtetë ose e gabuar.',
    'can' => 'Fusha :attribute përmban një vlerë të paautorizuar.',
    'confirmed' => 'Konfirmimi i fushës :attribute nuk përputhet.',
    'current_password' => 'Fjalëkalimi është i gabuar.',
    'date' => 'Fusha :attribute duhet të jetë një datë e vlefshme.',
    'date_equals' => 'Fusha :attribute duhet të jetë një datë e barabartë me :date.',
    'date_format' => 'Fusha :attribute duhet të përputhet me formatin :format.',
    'decimal' => 'Fusha :attribute duhet të ketë :decimal vendosje decimal.',
    'declined' => 'Fusha :attribute duhet të refuzohet.',
    'declined_if' => 'Fusha :attribute duhet të refuzohet kur :other është :value.',
    'different' => 'Fusha :attribute dhe :other duhet të jenë të ndryshme.',
    'digits' => 'Fusha :attribute duhet të ketë :digits digita.',
    'digits_between' => 'Fusha :attribute duhet të ketë midis :min dhe :max digita.',
    'dimensions' => 'Fusha :attribute ka dimensione të pamjaftueshme të imazhit.',
    'distinct' => 'Fusha :attribute ka një vlerë të dyfishtë.',
    'doesnt_end_with' => 'Fusha :attribute nuk duhet të përfundojë me një nga këto: :values.',
    'doesnt_start_with' => 'Fusha :attribute nuk duhet të fillojë me një nga këto: :values.',
    'email' => 'Fusha :attribute duhet të jetë një adresë emaili e vlefshme.',
    'ends_with' => 'Fusha :attribute duhet të përfundojë me një nga këto: :values.',
    'enum' => 'Përzgjedhja :attribute është e pavlefshme.',
    'exists' => 'Përzgjedhja :attribute është e pavlefshme.',
    'file' => 'Fusha :attribute duhet të jetë një file.',
    'filled' => 'Fusha :attribute duhet të ketë një vlerë.',
    'gt' => [
        'array' => 'Fusha :attribute duhet të ketë më shumë se :value elemente.',
        'file' => 'Fusha :attribute duhet të jetë më e madhe se :value kilobajtë.',
        'numeric' => 'Fusha :attribute duhet të jetë më e madhe se :value.',
        'string' => 'Fusha :attribute duhet të ketë më shumë se :value karaktere.',
    ],
    'gte' => [
        'array' => 'Fusha :attribute duhet të ketë :value elemente ose më shumë.',
        'file' => 'Fusha :attribute duhet të jetë më e madhe ose e barabartë me :value kilobajtë.',
        'numeric' => 'Fusha :attribute duhet të jetë më e madhe ose e barabartë me :value.',
        'string' => 'Fusha :attribute duhet të ketë më shumë ose e barabartë me :value karaktere.',
    ],
    'image' => 'Fusha :attribute duhet të jetë një imazh.',
    'in' => 'Përzgjedhja :attribute është e pavlefshme.',
    'in_array' => 'Fusha :attribute duhet të ekzistojë në :other.',
    'integer' => 'Fusha :attribute duhet të jetë një numër i plotë.',
    'ip' => 'Fusha :attribute duhet të jetë një adresë IP e vlefshme.',
    'ipv4' => 'Fusha :attribute duhet të jetë një adresë IPv4 e vlefshme.',
    'ipv6' => 'Fusha :attribute duhet të jetë një adresë IPv6 e vlefshme.',
    'json' => 'Fusha :attribute duhet të jetë një varg JSON i vlefshëm.',
    'lowercase' => 'Fusha :attribute duhet të jetë me shkronja të vogla.',
    'lt' => [
        'array' => 'Fusha :attribute duhet të ketë më pak se :value elemente.',
        'file' => 'Fusha :attribute duhet të jetë më pak se :value kilobajtë.',
        'numeric' => 'Fusha :attribute duhet të jetë më pak se :value.',
        'string' => 'Fusha :attribute duhet të ketë më pak se :value karaktere.',
    ],
    'lte' => [
        'array' => 'Fusha :attribute nuk duhet të ketë më shumë se :value elemente.',
        'file' => 'Fusha :attribute duhet të jetë më pak ose e barabartë me :value kilobajtë.',
        'numeric' => 'Fusha :attribute duhet të jetë më pak ose e barabartë me :value.',
        'string' => 'Fusha :attribute duhet të ketë më pak ose e barabartë me :value karaktere.',
    ],
    'mac_address' => 'Fusha :attribute duhet të jetë një adresë MAC e vlefshme.',
    'max' => [
        'array' => 'Fusha :attribute nuk duhet të ketë më shumë se :max elemente.',
        'file' => 'Fusha :attribute nuk duhet të jetë më e madhe se :max kilobajtë.',
        'numeric' => 'Fusha :attribute nuk duhet të jetë më e madhe se :max.',
        'string' => 'Fusha :attribute nuk duhet të ketë më shumë se :max karaktere.',
    ],
    'max_digits' => 'Fusha :attribute nuk duhet të ketë më shumë se :max digita.',
    'mimes' => 'Fusha :attribute duhet të jetë një file i llojit: :values.',
    'mimetypes' => 'Fusha :attribute duhet të jetë një file i llojit: :values.',
    'min' => [
        'array' => 'Fusha :attribute duhet të ketë të paktën :min elemente.',
        'file' => 'Fusha :attribute duhet të jetë të paktën :min kilobajtë.',
        'numeric' => 'Fusha :attribute duhet të jetë të paktën :min.',
        'string' => 'Fusha :attribute duhet të ketë të paktën :min karaktere.',
    ],
    'min_digits' => 'Fusha :attribute duhet të ketë të paktën :min digita.',
    'missing' => 'Fusha :attribute duhet të mungojë.',
    'missing_if' => 'Fusha :attribute duhet të mungojë kur :other është :value.',
    'missing_unless' => 'Fusha :attribute duhet të mungojë përveç nëse :other është :value.',
    'missing_with' => 'Fusha :attribute duhet të mungojë kur :values është e pranishme.',
    'missing_with_all' => 'Fusha :attribute duhet të mungojë kur :values janë të pranishme.',
    'multiple_of' => 'Fusha :attribute duhet të jetë një shumëfishe e :value.',
    'not_in' => 'Përzgjedhja :attribute është e pavlefshme.',
    'not_regex' => 'Formatimi i fushës :attribute është i pavlefshëm.',
    'numeric' => 'Fusha :attribute duhet të jetë një numër.',
    'password' => [
        'letters' => 'Fusha :attribute duhet të përmbajë letra.',
        'mixed' => 'Fusha :attribute duhet të përmbajë letra të mëdha dhe të vogla.',
        'numbers' => 'Fusha :attribute duhet të përmbajë numra.',
        'symbols' => 'Fusha :attribute duhet të përmbajë simbole.',
        'uncompromised' => 'Fusha :attribute është ekspozuar në një brech të të dhënave. Ju lutemi zgjidhni një :attribute të ndryshme.',
    ],
    'present' => 'Fusha :attribute duhet të jetë e pranishme.',
    'prohibited' => 'Fusha :attribute është e ndaluar.',
    'prohibited_if' => 'Fusha :attribute është e ndaluar kur :other është :value.',
    'prohibited_unless' => 'Fusha :attribute është e ndaluar përveç nëse :other është në :values.',
    'prohibits' => 'Fusha :attribute ndalon :other që të jetë i pranishëm.',
    'regex' => 'Formatimi i fushës :attribute është i pavlefshëm.',
    'required' => 'Fusha :attribute është e nevojshme.',
    'required_array_keys' => 'Fusha :attribute duhet të përmbajë hyrjet për: :values.',
    'required_if' => 'Fusha :attribute është e nevojshme kur :other është :value.',
    'required_if_accepted' => 'Fusha :attribute është e nevojshme kur :other është pranuar.',
    'required_unless' => 'Fusha :attribute është e nevojshme përveç nëse :other është në :values.',
    'required_with' => 'Fusha :attribute është e nevojshme kur :values është e pranishme.',
    'required_with_all' => 'Fusha :attribute është e nevojshme kur :values janë të pranishme.',
    'required_without' => 'Fusha :attribute është e nevojshme kur :values nuk është e pranishme.',
    'required_without_all' => 'Fusha :attribute është e nevojshme kur asnjë nga :values nuk është e pranishme.',
    'same' => 'Fusha :attribute dhe :other duhet të përputhen.',
    'size' => [
        'array' => 'Fusha :attribute duhet të ketë :size elemente.',
        'file' => 'Fusha :attribute duhet të jetë :size kilobajtë.',
        'numeric' => 'Fusha :attribute duhet të jetë :size.',
        'string' => 'Fusha :attribute duhet të ketë :size karaktere.',
    ],
    'starts_with' => 'Fusha :attribute duhet të fillojë me një nga këto: :values.',
    'string' => 'Fusha :attribute duhet të jetë një varg.',
    'timezone' => 'Fusha :attribute duhet të jetë një zonë e vlefshme.',
    'unique' => 'Fusha :attribute tashmë është marrë.',
    'uploaded' => 'Fusha :attribute dështoi në ngarkim.',
    'url' => 'Fusha :attribute duhet të jetë një URL e vlefshme.',
    'uuid' => 'Fusha :attribute duhet të jetë një UUID i vlefshëm.',

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
            'rule-name' => 'Mesazhi i personalizuar',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap the attribute placeholder
    | with something more reader friendly such as E-Mail Address instead of
    | "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [],


    'phone' => 'Fusha :attribute duhet të jetë një numër i vlefshëm.',
    'uppercase' => 'Fusha :attribute duhet të jetë e madhe.',
    'ulid' => 'Fusha :attribute duhet të jetë një ULID e vlefshme.',
    'weight_over' => 'Ju lutemi sigurohuni që pesha totale të mos kalojë 100%. Aktualisht, është :attribute%.',
];
