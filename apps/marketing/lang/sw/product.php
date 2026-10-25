<?php

return [
    'meta' => [
        'title' => 'Mfumo',
        'description' => 'Mashine ya mauzo ya VendaPOS, malipo ya M-Pesa na kadi, KRA eTIMS, usawazishaji bila mtandao, hesabu ya bidhaa, ripoti za matawi, udhibiti wa wafanyakazi na skrini ya jikoni — katika mfumo mmoja.',
    ],

    'hero' => [
        'eyebrow' => 'Mfumo',
        'title' => 'Kila kitu ambacho kaunta, jiko na ofisi kuu yako zinahitaji.',
        'lead' => 'VendaPOS inaleta pamoja mashine ya mauzo, malipo, KRA eTIMS, bidhaa na kila tawi katika mfumo mmoja unaoendelea kufanya kazi hata mtandao ukikatika.',
        'primary_cta' => 'Omba onyesho',
        'secondary_cta' => 'Angalia bei',
    ],

    'dashboard' => [
        'label' => 'Mchoro wa dashibodi ya mmiliki wa VendaPOS',
        'title' => 'Leo, matawi yote',
        'total' => 'Jumla ya mauzo',
        'live' => 'Moja kwa moja',
        'branches' => [
            'cbd' => 'Mombasa Mjini',
            'nyali' => 'Nyali',
            'diani' => 'Diani',
        ],
        'tills' => 'Mashine :count',
        'syncing' => 'inasawazisha',
        'recent' => 'Malipo ya hivi karibuni ya M-Pesa',
    ],

    'jump' => 'Nenda kwenye kipengele',

    'sections' => [
        'till' => [
            'nav' => 'Mashine',
            'eyebrow' => 'Mashine ya mauzo',
            'title' => 'Mashine ya mauzo ya haraka ambayo wafanyakazi hujifunza kwa dakika chache.',
            'body' => 'Vitufe vikubwa vya kugusa, vitufe vya haraka na kuskani barcode huweka foleni zikisonga. Kila aina ya biashara hupata skrini inayohitaji: meza kwa migahawa, akaunti wazi kwa baa, miadi kwa saluni, njia za malipo na mizani kwa supamaketi.',
            'points' => [
                'scan' => 'Barcode na misimbo ya mizani',
                'keys' => 'Vitufe vya haraka na marekebisho ya bidhaa',
                'split' => 'Kugawanya bili, kusitisha oda na akaunti wazi',
                'pin' => 'Kuingia haraka kwa PIN kwa kila keshia',
            ],
            'photo_alt' => 'Mhudumu wa baa akiandaa vinywaji nyuma ya kaunta ya baa iliyojaa bidhaa',
        ],
        'payments' => [
            'nav' => 'Malipo',
            'eyebrow' => 'Malipo',
            'title' => 'M-Pesa, kadi na pesa taslimu, zikilinganishwa kwa ajili yako.',
            'body' => 'Tuma ombi la STK na mauzo hujifunga yenyewe Safaricom ikithibitisha. Kila malipo hukaguliwa mara mbili kupitia Daraja, hivyo uthibitisho ukipotea hesabu ya mashine haipungui.',
            'points' => [
                'stk' => 'M-Pesa STK Push kutoka kwenye mashine',
                'confirm' => 'Uthibitisho na ukaguzi wa hali wa kiotomatiki',
                'cards' => 'Kadi kupitia Pesapal na DPO',
                'cash' => 'Pesa taslimu, chenji na ufungaji wa zamu',
            ],
        ],
        'etims' => [
            'nav' => 'eTIMS',
            'eyebrow' => 'KRA eTIMS',
            'title' => 'Risiti zinazotii sheria, bila makaratasi.',
            'body' => 'Kila mauzo hutumwa kwa KRA eTIMS chinichini na risiti huwa na msimbo wa QR wa uthibitisho. Mauzo yaliyofanywa bila mtandao huwekwa kwenye foleni na kutumwa kiotomatiki mtandao ukirudi.',
            'points' => [
                'vat' => 'Bidhaa za kiwango cha kawaida 16%, kiwango sifuri na zilizosamehewa',
                'qr' => 'Uthibitisho wa QR kwenye kila risiti',
                'offline' => 'Mauzo ya bila mtandao hutumwa kiotomatiki mtandao ukirudi',
                'retry' => 'Uwasilishaji ulioshindwa hujaribiwa tena na kuwekwa alama kwa ukaguzi',
            ],
        ],
        'offline' => [
            'nav' => 'Bila mtandao',
            'eyebrow' => 'Bila mtandao kwanza',
            'title' => 'Imejengwa kwa siku ambayo fiber itakatwa.',
            'body' => 'Kila mashine ina hifadhidata yake ya ndani. Mauzo, risiti na ufungaji wa zamu huendelea bila mtandao, kisha husawazishwa kwa makundi madogo mtandao unaporudi, bila oda kujirudia.',
            'points' => [
                'local' => 'Hifadhidata ya ndani kwenye kila mashine',
                'batches' => 'Usawazishaji wa kiotomatiki kwa makundi madogo',
                'ids' => 'Vitambulisho vya kipekee huzuia oda kujirudia',
                'status' => 'Hali ya mtandaoni au nje ya mtandao inaonekana wazi kwenye skrini',
            ],
        ],
        'stock' => [
            'nav' => 'Bidhaa',
            'eyebrow' => 'Hesabu ya bidhaa',
            'title' => 'Jua hasa kilichopo rafuni.',
            'body' => 'Fuatilia bidhaa kwa vipimo unavyouzia: vipande, mita, kilo, mifuko au kreti. Ufuatiliaji wa bechi huuza bidhaa za zamani kwanza, na tahadhari za bidhaa kupungua hukuambia cha kuagiza.',
            'points' => [
                'units' => 'Vipimo na ubadilishaji wake',
                'batches' => 'Bechi na tarehe za mwisho, zinazoisha kwanza hutoka kwanza',
                'transfers' => 'Kuhamisha bidhaa kati ya matawi',
                'alerts' => 'Gharama za wasambazaji na tahadhari za bidhaa kupungua',
            ],
            'photo_alt' => 'Rafu zilizojaa bidhaa katika duka la vifaa vya ujenzi',
        ],
        'branches' => [
            'nav' => 'Matawi',
            'eyebrow' => 'Matawi mengi',
            'title' => 'Kila tawi, moja kwa moja, kwenye simu yako.',
            'body' => 'Linganisha mauzo, bidhaa na wafanyakazi wa matawi yako yote yanapotokea, na chunguza tawi lolote kupitia dashibodi ya mmiliki.',
            'points' => [
                'live' => 'Mauzo ya moja kwa moja kwa kila tawi',
                'reports' => 'Ripoti zilizojumuishwa',
                'roles' => 'Ruhusa kwa kila tawi na jukumu',
                'devices' => 'Inafanya kazi kwenye simu, tableti na kompyuta',
            ],
            'photo_alt' => 'Toroli la ununuzi katika njia ya supamaketi',
        ],
        'staff' => [
            'nav' => 'Wafanyakazi',
            'eyebrow' => 'Udhibiti wa wafanyakazi',
            'title' => 'Linda faida yako kwenye kila mashine.',
            'body' => 'Mpe kila mtu ruhusa anazohitaji kwa jukumu lake. Kufuta oda na kurejesha pesa kunahitaji msimamizi, na kila ufunguzi wa droo ya pesa hurekodiwa.',
            'points' => [
                'roles' => 'Majukumu na PIN kwa kila mfanyakazi',
                'approvals' => 'Idhini ya msimamizi kwa kufuta oda na kurejesha pesa',
                'cashup' => 'Ufungaji wa zamu pamoja na tofauti za hesabu',
                'audit' => 'Kumbukumbu kamili za ukaguzi',
            ],
            'photo_alt' => 'Mfamasia akimweleza mteja kuhusu dawa aliyoandikiwa',
        ],
        'kitchen' => [
            'nav' => 'Jikoni',
            'eyebrow' => 'Skrini ya jikoni',
            'title' => 'Oda hufika jikoni papo hapo zinapowekwa.',
            'body' => 'Tiketi huonekana kwenye skrini za jikoni na baa kwa wakati halisi, kwa kila kituo. Wapishi huziondoa zikiwa tayari, na wahudumu huona hali yake.',
            'points' => [
                'routing' => 'Kupanga oda kwa kila kituo',
                'timers' => 'Saa za tiketi za moja kwa moja',
                'bump' => 'Kuondoa na kurudisha tiketi',
                'browser' => 'Inafanya kazi kwenye skrini yoyote yenye kivinjari',
            ],
        ],
    ],

    'stk' => [
        'label' => 'Mchoro wa malipo ya M-Pesa kwenye mashine ya mauzo',
        'amount' => 'Meza 04 · :amount',
        'steps' => [
            'sent' => 'Ombi la malipo limetumwa kwa 0712 ••• 678',
            'pin' => 'Mteja anaweka PIN yake ya M-Pesa',
            'confirmed' => 'Imethibitishwa na Safaricom · risiti imechapishwa',
        ],
    ],

    'receipt' => [
        'label' => 'Mchoro wa risiti ya VendaPOS yenye msimbo wa QR wa KRA eTIMS',
        'business' => 'Pwani Café, Mombasa',
        'items' => [
            'coffee' => 'Kahawa, kubwa',
            'mandazi' => 'Mandazi (2)',
            'water' => 'Maji ml 500',
        ],
        'vat' => 'VAT 16%',
        'total' => 'Jumla',
        'scan' => 'Skani ili kuthibitisha na KRA',
    ],

    'sync' => [
        'label' => 'Mchoro wa jinsi mauzo ya bila mtandao yanavyosawazishwa',
        'steps' => [
            'till' => ['title' => 'Mashine', 'body' => 'Mauzo huhifadhiwa ndani'],
            'queue' => ['title' => 'Foleni', 'body' => 'Husubiri mtandao'],
            'cloud' => ['title' => 'Wingu la VendaPOS', 'body' => 'Husawazishwa kwa makundi'],
            'kra' => ['title' => 'KRA eTIMS', 'body' => 'Hutumwa kiotomatiki'],
        ],
    ],

    'kds' => [
        'label' => 'Mchoro wa skrini ya jikoni',
        'station' => 'Kituo cha kuchoma',
        'tickets' => [
            'one' => ['table' => 'Meza 04', 'items' => '1 × Nyama choma · 1 × Kuku choma'],
            'two' => ['table' => 'Meza 09', 'items' => '2 × Minofu ya samaki · 1 × Chipsi'],
            'three' => ['table' => 'Ya kubeba 112', 'items' => '1 × Sambusa za nyama (6)'],
        ],
        'bump' => 'Ondoa',
    ],

    'hardware' => [
        'eyebrow' => 'Vifaa',
        'title' => 'Inafanya kazi na vifaa ulivyonavyo tayari.',
        'lead' => 'Huhitaji kununua mashine maalum. VendaPOS hufanya kazi kwenye vifaa vya kawaida unavyoweza kupata hapa nchini.',
        'items' => [
            'computers' => 'Kompyuta na skrini za kugusa zenye Windows, macOS au Linux',
            'printers' => 'Printa za risiti za mm 80 (ESC/POS), kupitia USB au mtandao',
            'drawers' => 'Droo za pesa, hufunguliwa kupitia printa',
            'scanners' => 'Vifaa vya kuskani barcode na mizani',
            'kds' => 'Skrini za jikoni zenye kivinjari chochote cha kisasa',
            'phones' => 'Dashibodi ya mmiliki kwenye simu janja yoyote',
        ],
    ],

    'cta' => [
        'title' => 'Ione ikifanya kazi kwenye kaunta yako mwenyewe.',
        'lead' => 'Tutaandaa onyesho kwa kutumia bidhaa zako, matawi yako na namna unavyofanya kazi.',
        'button' => 'Omba onyesho',
    ],
];
