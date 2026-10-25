<?php

return [
    'meta' => [
        'title' => 'Uzingatiaji na usalama',
        'description' => 'Jinsi VendaPOS inavyoshughulikia KRA eTIMS, VAT, uthibitisho wa malipo ya M-Pesa, data ya kadi na Sheria ya Ulinzi wa Data ya Kenya.',
    ],

    'hero' => [
        'eyebrow' => 'Uzingatiaji na usalama',
        'title' => 'Imeundwa kuwaridhisha KRA, wateja wako na wakaguzi wako.',
        'lead' => 'Utii wa kodi na ulinzi wa data ni sehemu ya msingi wa VendaPOS: sawa kwa kila biashara, kwenye kila mpango.',
    ],

    'status' => [
        'title' => 'Uthibitisho wa KRA wa kuunganisha eTIMS',
        'value' => 'Unaendelea',
        'certified' => 'Imethibitishwa',
        'note' => 'Tutachapisha taarifa zetu za kuunganishwa na KRA hapa uthibitisho ukikamilika.',
    ],

    'etims' => [
        'eyebrow' => 'KRA eTIMS',
        'title' => 'Kila mauzo hufika KRA, hata yale yaliyofanywa bila mtandao.',
        'steps' => [
            'sale' => ['title' => 'Mauzo huhifadhiwa kwanza', 'body' => 'Oda huhifadhiwa kwenye mashine kabla ya kitu kingine chochote, hivyo hakuna kinachopotea.'],
            'send' => ['title' => 'Hutumwa kwa eTIMS', 'body' => 'Huwasilishwa kwa KRA chinichini, bila kupunguza kasi ya mashine.'],
            'receipt' => ['title' => 'QR kwenye risiti', 'body' => 'Risiti iliyochapishwa huwa na msimbo wa QR wa uthibitisho wa KRA.'],
            'offline' => ['title' => 'Mauzo ya bila mtandao hufuatiliwa', 'body' => 'Mauzo yaliyofanywa bila mtandao hutumwa kiotomatiki mashine ikiunganishwa tena.'],
            'retry' => ['title' => 'Hakuna kinachopita bila kuonekana', 'body' => 'Uwasilishaji ulioshindwa hujaribiwa tena na kuwekwa alama kwa ukaguzi, kamwe hautupwi.'],
        ],
    ],

    'vat' => [
        'title' => 'VAT, inayokokotolewa kwa usahihi',
        'items' => [
            'categories' => 'Kila bidhaa ina aina ya VAT: kiwango cha kawaida 16%, kiwango sifuri au imesamehewa.',
            'cents' => 'Kiasi hukokotolewa kwa senti kamili, kamwe si kwa desimali zisizo sahihi, na hukadiriwa tu kinapoonyeshwa.',
            'inclusive' => 'Bei zinaweza kujumuisha VAT, kama kwenye menyu na lebo za rafu za Kenya, huku VAT ikionyeshwa kando kwenye risiti.',
        ],
    ],

    'payments' => [
        'eyebrow' => 'Malipo',
        'title' => 'Malipo unayoweza kuyaamini.',
        'items' => [
            'verify' => [
                'title' => 'Uthibitisho wa M-Pesa hukaguliwa',
                'body' => 'Kabla mauzo hayajawekwa kuwa yamelipwa, uthibitisho wa Safaricom hukaguliwa dhidi ya anwani ya siri ya kupokea majibu, anwani za IP zilizochapishwa na Safaricom na ombi la awali la malipo.',
            ],
            'query' => [
                'title' => 'Uthibitisho uliochelewa hufuatiliwa',
                'body' => 'Uthibitisho ukichelewa au usipofika kabisa, VendaPOS huiuliza Safaricom moja kwa moja hali ya malipo hayo.',
            ],
            'idempotent' => [
                'title' => 'Hakuna kutozwa mara mbili',
                'body' => 'Kila oda na malipo yana kitambulisho cha kipekee, hivyo kujaribu tena baada ya mtandao kusumbua hakumtozi mteja mara mbili.',
            ],
            'cards' => [
                'title' => 'Hatugusi data ya kadi',
                'body' => 'Malipo ya kadi hupitia Pesapal na DPO, hivyo namba za kadi hazifiki kwenye mifumo yetu na wajibu wa PCI-DSS unabaki kwao.',
            ],
        ],
    ],

    'data' => [
        'eyebrow' => 'Ulinzi wa data',
        'title' => 'Data yako inabaki kuwa yako.',
        'items' => [
            'isolation' => [
                'title' => 'Sehemu tofauti ya hifadhidata kwa kila biashara',
                'body' => 'Data ya kila biashara iko kwenye sehemu yake ya hifadhidata iliyojitenga, na majaribio ya kiotomatiki huhakikisha biashara moja haiwezi kamwe kusoma data ya nyingine.',
            ],
            'devices' => [
                'title' => 'Kila mashine inatambulika',
                'body' => 'Kila mashine huingia kwa kitambulisho chake cha kifaa, kinachoweza kufutwa kifaa kikipotea au kuibiwa.',
            ],
            'encryption' => [
                'title' => 'Imelindwa kwenye mashine',
                'body' => 'Mashine hutumia usimbaji fiche wa diski nzima, na taarifa nyeti husimbwa fiche ndani ya hifadhidata.',
            ],
            'dpa' => [
                'title' => 'Sheria ya Ulinzi wa Data ya Kenya, 2019',
                'body' => 'Tunakusanya tu tunachohitaji, tunakitumia tu kwa madhumuni kilichotolewa, na tunakifuta kisipohitajika tena.',
            ],
            'audit' => [
                'title' => 'Kumbukumbu kamili za ukaguzi',
                'body' => 'Kufuta oda, kurejesha pesa na kufungua droo ya pesa hurekodiwa pamoja na aliyefanya na wakati.',
            ],
            'controller' => [
                'title' => 'Anayewajibika',
                'body' => 'VendaPOS imetengenezwa na inaendeshwa na Danamo Tech Limited ya Mombasa, Kenya, inayowajibika kwa jinsi data ya mfumo inavyoshughulikiwa.',
            ],
        ],
    ],

    'cta' => [
        'title' => 'Unahitaji maelezo kwa ajili ya wakaguzi wako?',
        'lead' => 'Panga kikao nasi tutazieleza timu zako za fedha na TEHAMA jinsi VendaPOS inavyotimiza mahitaji yenu.',
        'button' => 'Panga kikao',
        'photo_alt' => 'Mfamasia akimweleza mteja kuhusu dawa aliyoandikiwa',
    ],
];
