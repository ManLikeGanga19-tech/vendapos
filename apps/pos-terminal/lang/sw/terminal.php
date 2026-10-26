<?php

declare(strict_types=1);

// Draft Swahili: for native-speaker review.
return [

    'language' => [
        'en' => 'English',
        'sw' => 'Kiswahili',
    ],

    'pair' => [
        'title' => 'Unganisha mashine hii',
        'help' => 'Kwenye kompyuta au simu, fungua paneli ya mmiliki, nenda Mashine za mauzo kisha bonyeza “Unganisha mashine”. Andika msimbo wa herufi 8 unaoonyeshwa kwenye visanduku hapa chini. Msimbo unafanya kazi mara moja tu, kwa dakika 15.',
        'not_used' => 'Misimbo haitumii 0, 1, I, L, O wala U, ili isisomwe vibaya. Vitufe hivyo vimeachwa makusudi.',
        'code' => 'Msimbo wa kuunganisha',
        'submit' => 'Unganisha mashine',
        'working' => 'Inaunganisha na kupakua menyu…',
        'backspace' => 'Futa',
        'clear' => 'Futa yote',
        'code_invalid' => 'Msimbo una herufi na namba 8, kama ABCD-2345.',
        'code_refused' => 'Msimbo huu hauwezi kutumika. Omba msimbo mpya.',
        'too_many_tries' => 'Umejaribu mara nyingi mno. Subiri dakika moja, kisha ujaribu tena.',
        'offline' => 'Hakuna mtandao. Kuunganisha kunahitaji intaneti; kuuza baadaye hakuhitaji.',
        'not_configured' => 'Mashine hii ilitengenezwa bila anwani ya seva. Wasiliana na huduma ya VendaPOS.',
        'keyring' => 'Mashine hii haiwezi kuhifadhi ufunguo wake kwa usalama. Wasiliana na huduma ya VendaPOS.',
        'removed' => 'Mashine hii imeondolewa kwenye biashara. Mwombe meneja aiunganishe tena.',
    ],

    'roles' => [
        'owner' => 'Mmiliki',
        'manager' => 'Meneja',
        'cashier' => 'Keshia',
        'waiter' => 'Mhudumu',
    ],

    'sign_in' => [
        'title' => 'Nani yuko kazini?',
        'choose' => 'Gusa jina lako.',
        'enter_pin' => 'Weka PIN yako',
        'back' => 'Rudi',
        'wrong' => 'PIN si sahihi. Imebaki nafasi :count.|PIN si sahihi. Zimebaki nafasi :count.',
        'locked' => 'PIN zisizo sahihi zimezidi. Jaribu tena saa :time.',
        'no_staff' => 'Bado hakuna anayeweza kuingia',
        'no_staff_help' => 'Kwenye paneli ya mmiliki, fungua Wafanyakazi na uwape watu PIN. Mashine itawapata ndani ya dakika moja.',
        'download_pending' => 'Menyu na wafanyakazi vitapakuliwa mara mashine itakapopata mtandao.',
        'sync_now' => 'Sawazisha sasa',
    ],

    'home' => [
        'switch' => 'Badilisha mtumiaji',
        'change_pin' => 'Badilisha PIN yangu',
    ],

    'sale' => [
        'categories' => 'Makundi',
        'all' => 'Zote',
        'no_items' => 'Bado hakuna bidhaa hapa. Menyu inatoka kwenye paneli ya mmiliki.',
        'cart' => 'Mauzo haya',
        'empty' => 'Gusa bidhaa au skani msimbopau kuanza mauzo.',
        'not_found' => 'Hakuna bidhaa yenye msimbopau :code.',
        'less' => 'Punguza moja',
        'more' => 'Ongeza moja',
        'vat' => 'Kati yake VAT',
        'total' => 'Jumla',
        'to_pay' => 'Bado kulipwa',
        'clear' => 'Futa',
        'pay' => 'Lipa',
        'back' => 'Rudi',
        'cash' => 'Pesa taslimu',
        'given' => 'Imetolewa',
        'complete' => 'Maliza mauzo',
        'cash_short' => 'Pesa haitoshi: bado :amount kulipwa.',
        'paid_in_part' => 'Sehemu ya mauzo haya imeshalipwa kwa M-Pesa, kwa hivyo hayawezi kubadilishwa tena. Pokea kilichobaki.',
        'mpesa' => [
            'title' => 'M-Pesa',
            'needs_internet' => 'inahitaji intaneti',
            'phone' => 'Nambari ya simu ya mteja',
            'amount' => 'Kiasi kwa shilingi',
            'send' => 'Tuma ombi la M-Pesa',
            'bad_phone' => 'Kagua nambari ya simu, k.m. 0712 345 678.',
            'bad_amount' => 'Weka shilingi kamili, kuanzia 1 hadi :max.',
            'offline' => 'Mashine haiwezi kufikia VendaPOS sasa hivi. Pokea pesa taslimu, au jaribu tena ikiwa mtandaoni.',
            'waiting' => 'Inamsubiri mteja',
            'waiting_help' => 'Mteja anaweka PIN yake ya M-Pesa kwenye simu yake.',
            'slow' => 'Inachukua muda mrefu. Endelea kusubiri, au acha na upokee pesa kwa njia nyingine.',
            'stop' => 'Acha kusubiri',
            'stopped' => 'Umeacha kusubiri. Mteja akilipa baadaye, mmiliki ataona malipo hayo.',
            'failed' => 'M-Pesa haikufaulu: :reason',
            'no_reason' => 'hakuna sababu iliyotolewa',
            'paid' => 'M-Pesa :reference',
            'refused' => [
                'payment_account_missing' => 'Tawi hili bado halina akaunti ya M-Pesa. Mmiliki huiongeza kwenye paneli ya mmiliki. Pokea pesa taslimu kwa sasa.',
                'tenant_suspended' => 'Akaunti hii imesimamishwa, kwa hivyo maombi ya M-Pesa yamezimwa. Pokea pesa taslimu.',
                'other' => 'Ombi la M-Pesa limekataliwa. Kagua nambari na kiasi, au pokea pesa taslimu.',
            ],
        ],
        'done' => [
            'title' => 'Mauzo :number yamekamilika',
            'total' => 'Jumla :amount',
            'change' => 'Chenji',
            'no_change' => 'Hakuna chenji',
            'new' => 'Mauzo mapya',
        ],
        'refused' => [
            'not_allowed' => 'Huwezi kupokea malipo. Muulize keshia au meneja.',
            'empty' => 'Mauzo hayana kitu.',
            'unknown_item' => 'Bidhaa moja kwenye mauzo haya haipo tena kwenye menyu. Iondoe kisha ujaribu tena.',
            'tenders' => 'Malipo hayalingani na jumla. Yakague kisha ujaribu tena.',
        ],
    ],

    'pin_change' => [
        'title' => 'Badilisha PIN yangu',
        'online_only' => 'Inahitaji intaneti',
        'steps' => [
            'current' => 'Weka PIN yako ya sasa',
            'new' => 'Chagua PIN mpya ya tarakimu :length',
            'confirm' => 'Andika PIN mpya tena',
        ],
        'mismatch' => 'PIN mpya mbili hazifanani. Chagua PIN mpya tena.',
        'done' => 'PIN yako imebadilishwa. Tumia mpya utakapoingia tena.',
        'reasons' => [
            'wrong_pin' => 'Hiyo si PIN yako ya sasa.',
            'locked' => 'PIN zisizo sahihi zimezidi. Subiri dakika chache kisha ujaribu tena.',
            'wrong_length' => 'PIN mpya ina idadi isiyo sahihi ya tarakimu.',
            'too_easy' => 'PIN hiyo ni rahisi kukisiwa. Epuka marudio kama 7777 na mfuatano kama 3456.',
            'same_pin' => 'PIN mpya ni sawa na ya sasa.',
            'too_many_tries' => 'Umejaribu mara nyingi mno. Subiri dakika chache kisha ujaribu tena.',
            'offline' => 'Hakuna mtandao. Kubadilisha PIN kunahitaji intaneti.',
            'not_on_server' => 'Seva haikukupata. Mwombe meneja aangalie paneli ya mmiliki.',
        ],
    ],

    'status' => [
        'online' => 'Mtandaoni',
        'offline' => 'Nje ya mtandao: itasawazisha mtandao ukirudi',
        'never' => 'Bado haijasawazishwa',
        'last_sync' => 'imesawazishwa :time',
        'suspended' => 'Akaunti imesimamishwa: M-Pesa imesitishwa, pokea pesa taslimu.',
    ],

];
