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
];
