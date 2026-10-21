<?php

return [
    'common' => [
        'updated' => 'Last updated :date',
        'draft' => 'Draft: this document is being reviewed by our legal advisers and may change before launch.',
        'contents' => 'On this page',
        'precedence' => 'If the English and Kiswahili versions of this document differ, the English version applies.',
        'company_title' => 'Company details',
        'fields' => [
            'name' => 'Company',
            'location' => 'Based in',
            'registration_number' => 'Registration number',
            'postal_address' => 'Postal address',
            'odpc_registration' => 'ODPC registration',
            'hosting_region' => 'Data hosting region',
            'privacy_email' => 'Privacy contact',
        ],
        'privacy' => 'Privacy policy',
        'terms' => 'Terms of service',
    ],

    'privacy' => [
        'meta' => [
            'title' => 'Privacy policy',
            'description' => 'How DanamoTech Ltd collects, uses and protects personal data on the VendaPOS website and platform, under the Kenya Data Protection Act, 2019.',
        ],
        'title' => 'Privacy policy',
        'lead' => 'How we collect, use and protect personal data, and the rights you have under the Kenya Data Protection Act, 2019.',
        'sections' => [
            'who' => [
                'title' => 'Who we are',
                'body' => [
                    'VendaPOS is a point-of-sale platform built and run by DanamoTech Ltd of Mombasa, Kenya (“DanamoTech”, “we”, “us”). This policy explains how we handle personal data when you visit this website, request a demo or use VendaPOS.',
                    'We are the data controller for personal data collected through this website and for the account details of businesses that use VendaPOS. When a business uses VendaPOS to record its own sales and customers, that business is the data controller and we process the data on its behalf as a data processor.',
                ],
            ],
            'collect' => [
                'title' => 'What we collect',
                'list' => [
                    'Demo requests: your name, work email, phone number, business name, type of business, number of outlets, preferred call-back time and any message you send.',
                    'Customer accounts: the business’s details, the names, roles and login details of its staff, and its billing details.',
                    'Business records we process for our customers: sales, payments, products, stock and, where a business records them, its own customers’ details.',
                    'Technical data: the IP address, browser type and pages requested, kept in server logs to run and protect the service.',
                ],
                'body' => [
                    'We do not collect card numbers. Card payments are processed by Pesapal or DPO, and M-Pesa payments by Safaricom.',
                ],
            ],
            'use' => [
                'title' => 'How and why we use it',
                'list' => [
                    'To respond to your demo request and follow it up, based on your consent.',
                    'To provide VendaPOS to our customers, based on our contract with them.',
                    'To send sales to KRA eTIMS on our customers’ behalf and keep the records Kenyan tax law requires, based on legal obligation.',
                    'To keep the service secure, prevent fraud and fix problems, based on our legitimate interests.',
                ],
                'body' => [
                    'We do not sell personal data, and we do not use it for advertising.',
                ],
            ],
            'sharing' => [
                'title' => 'Who we share it with',
                'list' => [
                    'Hosting, email and other service providers that process data for us under written agreements.',
                    'Safaricom (M-Pesa), Pesapal and DPO, to process the payments our customers choose to take.',
                    'The Kenya Revenue Authority, when a sale is submitted to eTIMS.',
                    'Authorities or courts, when the law requires it.',
                ],
            ],
            'transfers' => [
                'title' => 'Where your data is stored',
                'body' => [
                    'Personal data may be stored or processed outside Kenya by our hosting and service providers. When it is, we make sure the transfer meets the requirements of the Kenya Data Protection Act, 2019, including appropriate safeguards. Our hosting region is listed in the company details on this page.',
                ],
            ],
            'retention' => [
                'title' => 'How long we keep it',
                'list' => [
                    'Demo requests: :months months after we last contact you, then deleted.',
                    'Customer account data: for as long as the account is active, and for a limited period afterwards to settle billing and resolve disputes.',
                    'Tax and sales records: for as long as Kenyan tax law requires.',
                    'Server logs: for a short period, unless they are needed to investigate a security incident.',
                ],
            ],
            'security' => [
                'title' => 'How we protect it',
                'body' => [
                    'Each business’s data is held in its own isolated database schema. Tills use full-disk encryption and sensitive fields are encrypted in our database. Access is limited to staff who need it for their role, and every till signs in with its own credential that can be revoked.',
                ],
            ],
            'rights' => [
                'title' => 'Your rights',
                'body' => [
                    'Under the Kenya Data Protection Act, 2019 you have the right to:',
                ],
                'list' => [
                    'be told how your personal data is used;',
                    'access the personal data we hold about you;',
                    'have inaccurate data corrected, and data we no longer need deleted;',
                    'object to processing and withdraw your consent at any time;',
                    'receive your data in a portable format.',
                ],
                'after' => [
                    'To use these rights, email the privacy contact shown in the company details on this page. We will reply within the time the law allows. If your data is held by a business that uses VendaPOS, please contact that business first; we will help it respond.',
                    'If you are not satisfied with our response, you can complain to the Office of the Data Protection Commissioner (ODPC).',
                ],
            ],
            'cookies' => [
                'title' => 'Cookies',
                'body' => [
                    'Our public pages set no cookies. The demo form uses two strictly necessary cookies, a session cookie and a security token, which protect the form from misuse and expire after a short time. We do not use analytics or advertising cookies.',
                ],
            ],
            'children' => [
                'title' => 'Children',
                'body' => [
                    'This website and VendaPOS are meant for businesses. We do not knowingly collect personal data from children.',
                ],
            ],
            'changes' => [
                'title' => 'Changes to this policy',
                'body' => [
                    'We will update this page when our practices change, and show the date of the latest version at the top.',
                ],
            ],
        ],
    ],

    'terms' => [
        'meta' => [
            'title' => 'Terms of service',
            'description' => 'The terms that apply to the VendaPOS website and platform, provided by DanamoTech Ltd of Mombasa, Kenya.',
        ],
        'title' => 'Terms of service',
        'lead' => 'The terms that apply when you use this website or VendaPOS.',
        'sections' => [
            'about' => [
                'title' => 'About these terms',
                'body' => [
                    'These terms apply to your use of this website and of VendaPOS, provided by DanamoTech Ltd of Mombasa, Kenya. By using the website or the service you agree to them. If you use VendaPOS on behalf of a business, you confirm that you may accept these terms for it.',
                ],
            ],
            'service' => [
                'title' => 'The service',
                'body' => [
                    'VendaPOS is a subscription point-of-sale platform made up of till software, an online dashboard and connections to payment providers and KRA eTIMS. The features you get depend on your plan, as described on our pricing page or in your order.',
                ],
            ],
            'account' => [
                'title' => 'Your account and devices',
                'list' => [
                    'Keep login details, PINs and device credentials confidential.',
                    'Give staff only the access their role needs.',
                    'Tell us promptly if you suspect unauthorised access or lose a device, so we can revoke it.',
                    'You are responsible for activity carried out under your account.',
                ],
            ],
            'tax' => [
                'title' => 'Tax and compliance',
                'body' => [
                    'VendaPOS helps you send sales to KRA eTIMS and apply VAT categories, but you remain responsible for your tax obligations, for the accuracy of your products, prices and VAT settings, and for meeting the conditions of any licence you hold.',
                ],
            ],
            'payments' => [
                'title' => 'Payments to your business',
                'body' => [
                    'M-Pesa, card and other payments are processed by third parties such as Safaricom, Pesapal and DPO under their own terms, and their charges are separate from your VendaPOS subscription. We are not responsible for delays or failures caused by those providers, but we will help you trace any payment recorded in VendaPOS.',
                ],
            ],
            'fees' => [
                'title' => 'Fees and billing',
                'body' => [
                    'Subscription fees are charged per branch for the plan you choose, monthly or yearly, as set out in your order. Prices are in Kenya shillings. If an invoice is not paid, we may suspend the service after giving you notice.',
                ],
            ],
            'data' => [
                'title' => 'Your data',
                'body' => [
                    'You own the data you put into VendaPOS. You allow us to process it only to provide the service, as described in our privacy policy. When your subscription ends, you can ask for an export of your data before it is deleted.',
                ],
            ],
            'use' => [
                'title' => 'Acceptable use',
                'list' => [
                    'Don’t use VendaPOS for anything unlawful, including evading tax.',
                    'Don’t try to access other businesses’ data or interfere with the service.',
                    'Don’t copy, resell or reverse engineer the software, except where the law allows it.',
                ],
            ],
            'availability' => [
                'title' => 'Availability',
                'body' => [
                    'We work to keep VendaPOS available, and tills keep selling offline when the internet or our service is unavailable. We sometimes need to carry out maintenance, which we try to schedule outside busy trading hours. Any specific service levels are set out in your order.',
                ],
            ],
            'ip' => [
                'title' => 'Intellectual property',
                'body' => [
                    'VendaPOS, its software and its brand belong to DanamoTech Ltd. Your subscription gives you the right to use the service, not ownership of it. M-Pesa, KRA and other names and marks belong to their owners.',
                ],
            ],
            'liability' => [
                'title' => 'Liability',
                'body' => [
                    'Nothing in these terms limits liability that cannot be limited under Kenyan law. Otherwise, we are not liable for indirect losses such as lost profits, and our total liability in any twelve months is limited to the fees you paid us in that period.',
                ],
            ],
            'ending' => [
                'title' => 'Suspension and ending the service',
                'body' => [
                    'You can cancel at the end of any billing period. We may suspend or end the service if you seriously breach these terms, after giving notice where it is reasonable to do so.',
                ],
            ],
            'law' => [
                'title' => 'Governing law',
                'body' => [
                    'These terms are governed by the laws of Kenya, and the courts of Kenya have jurisdiction over any dispute.',
                ],
            ],
            'changes' => [
                'title' => 'Changes to these terms',
                'body' => [
                    'We may update these terms. We will tell customers about significant changes before they take effect.',
                ],
            ],
        ],
    ],
];
