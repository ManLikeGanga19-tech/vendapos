<?php

declare(strict_types=1);

// Draft Swahili; to be reviewed by a native speaker.
return [
    'tenant' => [
        'overview' => 'Mteja',
        'branches' => 'Matawi',
        'devices' => 'Vifaa',
        'etims' => 'eTIMS',
        'negative_stock' => 'Hisa hasi',
    ],

    'branches' => [
        'name' => 'Tawi',
        'business_type' => 'Aina ya biashara',
        'etims_branch_id' => 'Nambari ya tawi ya KRA',
        'etims_enabled' => 'eTIMS imewashwa',
        'set_etims_branch_id' => 'Weka nambari ya tawi ya KRA',
        'etims_branch_id_help' => 'Nambari ya tawi (bhfId) iliyotolewa na KRA kwa tawi hili. Acha wazi ili kuifuta.',
        'switch_etims_on' => 'Washa eTIMS',
        'switch_etims_off' => 'Zima eTIMS',
        'switch_etims_on_confirm' => 'Kuanzia sasa mauzo ya tawi hili yatatumwa kwa KRA eTIMS.',
        'switch_etims_off_confirm' => 'Mauzo mapya ya tawi hili hayatatumwa tena kwa KRA. Hati zilizo kwenye foleni bado zitatumwa.',
        'saved' => 'Tawi limesasishwa.',
    ],

    'devices' => [
        'name' => 'Kifaa',
        'branch' => 'Tawi',
        'last_seen' => 'Mara ya mwisho kuonekana',
        'never' => 'Hakijawahi',
        'token_expires' => 'Tokeni inaisha',
        'status' => 'Hali',
        'active' => 'Kinatumika',
        'revoked' => 'Kimefutiwa ruhusa',
        'revoke' => 'Futa ruhusa',
        'revoke_confirm' => 'Kifaa kitaondolewa mara moja na hakitaweza kusawazisha hadi kisajiliwe upya.',
        'revoked_notice' => 'Ruhusa ya kifaa imefutwa.',
    ],

    'etims' => [
        'order' => 'Oda',
        'branch' => 'Tawi',
        'document' => 'Hati',
        'sale' => 'Mauzo',
        'credit_note' => 'Hati ya marejesho',
        'status' => 'Hali',
        'pending' => 'Inasubiri',
        'failed' => 'Imeshindikana',
        'attempts' => 'Majaribio',
        'next_attempt' => 'Jaribio lijalo',
        'last_error' => 'Hitilafu ya mwisho',
        'queued' => 'Iliwekwa foleni',
        'retry_now' => 'Jaribu tena sasa',
        'retry_confirm' => 'Tuma hati hii kwa KRA tena sasa. Hati iliyoshindikana inapata jaribio moja zaidi.',
        'retry_queued' => 'Jaribio limewekwa kwenye foleni.',
        'empty' => 'Hakuna kinachosubiri: kila hati ya eTIMS imetumwa au haihitajiki.',
    ],

    'negative_stock' => [
        'branch' => 'Tawi',
        'item' => 'Bidhaa',
        'quantity' => 'Kilichopo',
        'empty' => 'Hakuna bidhaa iliyo chini ya sifuri.',
    ],

    'errors' => [
        'action_failed' => 'Haikuwezekana kufanya hivyo.',
    ],
];
