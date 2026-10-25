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
];
