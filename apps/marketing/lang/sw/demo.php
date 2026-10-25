<?php

return [
    'meta' => [
        'title' => 'Omba onyesho',
        'description' => 'Panga onyesho la VendaPOS lililoandaliwa kwa ajili ya mgahawa, baa, saluni, duka, supamaketi, duka la vifaa vya ujenzi au kemisti yako.',
    ],

    'intro' => [
        'eyebrow' => 'Onyesho la moja kwa moja',
        'title' => 'Ona VendaPOS kwenye kaunta yako.',
        'lead' => 'Onyesho lililoandaliwa kwa ajili ya biashara yako, kwa Kiingereza au Kiswahili. Hakuna wajibu wowote.',
        'benefits_title' => 'Katika onyesho lako utaweza:',
        'benefits' => [
            'offline' => 'Kuona mashine ya mauzo ikiendelea kuuza wakati mtandao umekatwa kwa majaribio, kisha ikisawazisha.',
            'mpesa' => 'Kupokea malipo halisi ya M-Pesa STK na kuyaona yakithibitishwa kwenye mashine.',
            'etims' => 'Kuona mauzo yakifika KRA eTIMS na risiti ikichapishwa na msimbo wake wa QR wa uthibitisho.',
            'branches' => 'Kuchunguza bidhaa, wafanyakazi na mauzo ya matawi yote kupitia dashibodi ya mmiliki.',
        ],
        'photo_alt' => 'Mwenye duka kwenye kaunta ya duka lake jijini Dar es Salaam',
    ],

    'form' => [
        'title' => 'Omba onyesho lako',
        'lead' => 'Tueleze kidogo kuhusu biashara yako nasi tutawasiliana nawe kupanga muda.',
        'errors_title' => 'Tafadhali kagua sehemu zilizoangaziwa.',
        'optional' => 'Si lazima',
        'choose' => 'Chagua moja',
        'any_time' => 'Wakati wowote',
        'submit' => 'Omba onyesho',
        'consent' => 'Ninakubali VendaPOS ihifadhi taarifa hizi na iwasiliane nami kuhusu onyesho hili, kama ilivyoelezwa hapa chini.',
        'honeypot' => 'Acha sehemu hii wazi',
    ],

    'hints' => [
        'phone' => 'Tutathibitisha kwa simu au WhatsApp. Namba za Kenya zinaweza kuanza na 07 au 01.',
        'message' => 'Kwa mfano: mfumo unaotumia sasa, idadi ya mashine za mauzo au tarehe ya kuanza.',
    ],

    'fields' => [
        'request_id' => 'fomu',
        'form_token' => 'fomu',
        'website' => 'tovuti',
        'name' => 'Jina kamili',
        'email' => 'Barua pepe ya kazini',
        'phone' => 'Namba ya simu',
        'business_name' => 'Jina la biashara',
        'business_type' => 'Aina ya biashara',
        'outlets' => 'Idadi ya matawi',
        'contact_window' => 'Wakati mzuri wa kupigiwa simu',
        'message' => 'Kuna jambo tunalopaswa kujua?',
        'consent' => 'Idhini',
    ],

    'business_types' => [
        'restaurant' => 'Mgahawa, mkahawa au jiko la hoteli',
        'bar' => 'Baa, klabu au lounge',
        'salon' => 'Saluni, spa au kinyozi',
        'shop' => 'Duka au kibanda',
        'supermarket' => 'Supamaketi au minimaketi',
        'hardware' => 'Duka la vifaa vya ujenzi',
        'pharmacy' => 'Kemisti au duka la dawa',
        'liquor' => 'Duka la vileo',
        'general' => 'Biashara nyingine',
    ],

    'outlets' => [
        '1' => 'Tawi 1',
        '2-5' => 'Matawi 2–5',
        '6-15' => 'Matawi 6–15',
        '16-50' => 'Matawi 16–50',
        '50+' => 'Zaidi ya matawi 50',
    ],

    'contact_windows' => [
        'morning' => 'Asubuhi (saa 3:00–6:00 mchana)',
        'afternoon' => 'Mchana (saa 8:00–11:00 jioni)',
    ],

    'privacy' => [
        'title' => 'Jinsi tunavyotumia taarifa zako',
        'controller' => 'Taarifa zako zinahifadhiwa na Danamo Tech Limited ya Mombasa, Kenya, inayotengeneza na kuendesha VendaPOS.',
        'use' => 'Tunatumia taarifa hizi tu kupanga na kufuatilia onyesho lako. Hatuziuzi wala kuzishiriki na mtu yeyote.',
        'retention' => 'Tunazihifadhi kwa miezi :months, kisha tunazifuta.',
        'rights' => 'Ili kuona, kurekebisha au kufuta taarifa zako, tuma barua pepe kwa :email.',
        'law' => 'Tunashughulikia data yako kwa mujibu wa Sheria ya Ulinzi wa Data ya Kenya, 2019.',
        'read_policy' => 'Soma sera yetu kamili ya faragha',
    ],

    'validation' => [
        'required' => ':Attribute inahitajika.',
        'email' => 'Weka barua pepe sahihi, kama jina@biashara.co.ke.',
        'max' => ':Attribute isizidi herufi :max.',
        'choice' => ':Attribute: tafadhali chagua mojawapo ya chaguo.',
        'phone' => 'Weka namba ya simu sahihi, kama 0712 345 678 au +256 772 123 456.',
        'consent' => 'Tafadhali kubali ili tuweze kuwasiliana nawe kuhusu onyesho lako.',
        'expired' => 'Muda wa fomu hii umekwisha. Tafadhali pakia upya ukurasa na ujaribu tena.',
        'unknown' => 'Fomu ilikuwa na sehemu zisizotarajiwa. Tafadhali pakia upya ukurasa na ujaribu tena.',
    ],

    'thanks' => [
        'title' => 'Asante — ombi lako limepokelewa.',
        'body' => 'Tutawasiliana nawe hivi karibuni kupanga muda unaokufaa. Pia tumekutumia uthibitisho kwa barua pepe.',
        'back' => 'Rudi mwanzo',
    ],

    'mail' => [
        'sales' => [
            'subject' => 'Ombi jipya la onyesho: :business',
            'intro' => 'Ombi jipya la onyesho limepokelewa kutoka kwenye tovuti.',
            'language' => 'Lugha',
            'reference' => 'Kumbukumbu',
        ],
        'visitor' => [
            'subject' => 'Tumepokea ombi lako la onyesho la VendaPOS',
            'greeting' => 'Habari :name,',
            'body' => 'Asante kwa kupendezwa na VendaPOS. Tumepokea ombi lako la onyesho kwa ajili ya :business.',
            'next' => 'Timu yetu itawasiliana nawe hivi karibuni kupanga muda unaokufaa.',
            'signoff' => 'Timu ya VendaPOS',
        ],
    ],
];
