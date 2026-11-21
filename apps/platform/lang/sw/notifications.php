<?php

declare(strict_types=1);

// Ujumbe kwa wamiliki wa biashara, kwa SMS na barua pepe. RASIMU: inasubiri ukaguzi wa mzungumzaji asilia.
return [
    'greeting' => 'Habari :name,',
    'contact' => 'Una swali? Piga :phone.',

    'tenant_suspended' => [
        'sms' => 'VendaPOS: :business imesimamishwa. Mashine za mauzo zinaendelea kurekodi mauzo, lakini kuingia na maombi mapya ya M-Pesa yamezuiwa. Isipofunguliwa tena, nakala ya data yako itatolewa na data kufutwa tarehe :date.',
        'subject' => ':business: akaunti yako ya VendaPOS imesimamishwa',
        'intro' => 'Tumesimamisha akaunti ya VendaPOS ya :business.',
        'reason' => 'Sababu: :reason',
        'still_works' => 'Mashine zako za mauzo zinaendelea kurekodi na kutuma mauzo, na malipo ya M-Pesa yaliyokwisha anzishwa yatakamilika.',
        'blocked' => 'Kuingia kwenye paneli ya mmiliki na kuanzisha malipo mapya ya M-Pesa kumezuiwa.',
        'purge' => 'Akaunti isipofunguliwa tena, nakala ya data yako itatolewa kisha data kufutwa kabisa tarehe :date.',
    ],

    'tenant_reactivated' => [
        'sms' => 'VendaPOS: :business imefunguliwa tena. Kuingia na maombi ya M-Pesa yanafanya kazi kama kawaida, na hakuna kitakachofutwa.',
        'subject' => ':business: akaunti yako ya VendaPOS imefunguliwa tena',
        'body' => 'Usimamishaji wa :business umeondolewa. Kuingia na maombi ya M-Pesa yanafanya kazi kama kawaida, na hakuna kitakachofutwa.',
    ],

    'tenant_purge_reminder' => [
        'sms' => '{1} Kumbusho la VendaPOS: :business bado imesimamishwa. Isipofunguliwa tena, nakala ya data itatolewa na data kufutwa kesho, tarehe :date.|[2,*] Kumbusho la VendaPOS: :business bado imesimamishwa. Isipofunguliwa tena, nakala ya data itatolewa na data kufutwa baada ya siku :days, tarehe :date.',
        'subject' => '{1} :business: data yako itafutwa kesho|[2,*] :business: data yako itafutwa baada ya siku :days',
        'body' => '{1} :business bado imesimamishwa. Akaunti isipofunguliwa tena, nakala ya data itatolewa kisha data kufutwa kabisa kesho, tarehe :date.|[2,*] :business bado imesimamishwa. Akaunti isipofunguliwa tena, nakala ya data itatolewa kisha data kufutwa kabisa baada ya siku :days, tarehe :date.',
    ],

    'tenant_purged' => [
        'sms' => 'VendaPOS: kama tulivyokujulisha, data ya :business imefutwa. Nakala iliyosimbwa inahifadhiwa hadi :date.',
        'subject' => ':business: data yako ya VendaPOS imefutwa',
        'body' => 'Kama tulivyokujulisha, data ya :business imefutwa kabisa kutoka VendaPOS. Nakala iliyosimbwa inahifadhiwa hadi :date; wasiliana nasi ukiihitaji.',
    ],

    'payment_account_changed' => [
        'sms' => 'VendaPOS: :name amebadilisha akaunti ya malipo ":account" ya :business (:change) tarehe :date saa :time. Kama si wewe, ingia ukague sasa hivi.',
        'subject' => ':business: akaunti ya malipo ":account" imebadilishwa',
        'body' => ':name amebadilisha akaunti ya malipo ":account" (:change) tarehe :date saa :time.',
        'not_you' => 'Kama hukutarajia hili, ingia VendaPOS ukague akaunti zako za malipo sasa hivi: huenda malipo ya wateja yanaenda kwenye paybill au till nyingine.',
        'actions' => [
            'added' => 'imeongezwa',
            'updated' => 'funguo au maelezo yamebadilishwa',
            'branches_changed' => 'matawi yamebadilishwa',
        ],
    ],

    'mpesa_checks' => [
        'sms' => '{1} VendaPOS: malipo 1 ya M-Pesa ya :business yanahitaji kukaguliwa. Fungua "M-Pesa ya kukagua" kwenye paneli ya mmiliki.|[2,*] VendaPOS: malipo :count ya M-Pesa ya :business yanahitaji kukaguliwa. Fungua "M-Pesa ya kukagua" kwenye paneli ya mmiliki.',
        'subject' => '{1} :business: malipo 1 ya M-Pesa ya kukagua|[2,*] :business: malipo :count ya M-Pesa ya kukagua',
        'intro' => 'Baadhi ya pesa za M-Pesa kwenye mashine zako bado hazilingani:',
        'kinds' => [
            'unconfirmed_code' => '{1} msimbo 1 wa M-Pesa ulioandikwa ambao Safaricom haijathibitisha|[2,*] misimbo :count ya M-Pesa iliyoandikwa ambayo Safaricom haijathibitisha',
            'amount_mismatch' => '{1} msimbo 1 wa M-Pesa uliothibitishwa kwa kiasi tofauti na mauzo|[2,*] misimbo :count ya M-Pesa iliyothibitishwa kwa kiasi tofauti na mauzo',
            'code_reused' => '{1} msimbo 1 wa M-Pesa ulioandikwa kwa mauzo zaidi ya moja|[2,*] misimbo :count ya M-Pesa iliyoandikwa kwa mauzo zaidi ya moja',
            'unclaimed_push' => '{1} malipo 1 ya M-Pesa yaliyopokelewa bila mauzo|[2,*] malipo :count ya M-Pesa yaliyopokelewa bila mauzo',
            'qr_paid_twice' => '{1} bili 1 iliyolipwa tena kwa QR baada ya kulipwa tayari|[2,*] bili :count zilizolipwa tena kwa QR baada ya kulipwa tayari',
            'ticket_paid_twice' => '{1} tiketi 1 iliyolipwa na mauzo mawili|[2,*] tiketi :count zilizolipwa na mauzo mawili',
        ],
        'where' => 'Fungua "M-Pesa ya kukagua" kwenye paneli ya mmiliki kuona kila moja na kuliweka kama limekaguliwa.',
    ],
];
