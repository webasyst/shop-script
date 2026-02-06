<?php

if (wa()->getLocale() == 'ru_RU') {
    return [
        [
            'name'  => 'Свои правила',
            'rules' => [
                [
                    'rule_type'   => 'banner',
                    'rule_params' => [
                        'banners' => [
                            [
                                'type'  => 'link',
                                'image' => 'img/promo-dummy-1.jpg',
                                'title' => 'Свой дом — свои правила',
                                'body'  => 'Устанавливай собственные правила, а не играй по чужим. Строй свою платформу, свой онлайн-магазин.',
                                'link'  => 'https://www.shop-script.ru',
                                'color' => '#ffffff',
                                'background_color' => '#290E0C',
                                'countdown_datetime' => date("Y-m-d H:i:s", strtotime("+1 day")),
                            ]
                        ]
                    ]
                ]
            ],
        ],
        [
            'name'  => 'Начало положено',
            'rules' => [
                [
                    'rule_type'   => 'banner',
                    'rule_params' => [
                        'banners' => [
                            [
                                'type'  => 'link',
                                'image' => 'img/promo-dummy-2.jpg',
                                'title' => 'Начало положено',
                                'body'  => 'Каждый новый день — это новый вызов и новые свершения. Правильное начало и бодрый настрой определяют результат.',
                                'link'  => 'https://www.webasyst.ru',
                                'color' => '#ffffff',
                                'background_color' => '#733E25',
                            ]
                        ]
                    ]
                ]
            ],
        ],
        [
            'name'  => 'От мечты к цели',
            'rules' => [
                [
                    'rule_type'   => 'banner',
                    'rule_params' => [
                        'banners' => [
                            [
                                'type'  => 'link',
                                'image' => 'img/promo-dummy-3.jpg',
                                'title' => 'От мечты к цели',
                                'body'  => 'Каждая большая победа начинается с маленькой мечты. Сделай первый шаг — приблизь свою мечту к заветной цели.',
                                'link'  => '#',
                                'color' => '#ffffff',
                                'background_color' => '#623D27',
                            ]
                        ]
                    ]
                ]
            ],
        ],
        [
            'name'  => 'В тренде',
            'rules' => [
                [
                    'rule_type'   => 'banner',
                    'rule_params' => [
                        'banners' => [
                            [
                                'type'  => 'link',
                                'image' => 'img/promo-dummy-4.jpg',
                                'title' => 'В тренде',
                                'body'  => 'Быть в тренде — значит не догонять, а чувствовать ритм времени. Следуй не за модой, а за новыми возможностями.',
                                'link'  => '#',
                                'color' => '#ffffff',
                                'background_color' => '#213432',
                            ]
                        ]
                    ]
                ]
            ],
        ],
        [
            'name'  => 'Будущее наступило',
            'rules' => [
                [
                    'rule_type'   => 'banner',
                    'rule_params' => [
                        'banners' => [
                            [
                                'type'  => 'link',
                                'image' => 'img/promo-dummy-5.jpg',
                                'title' => 'Будущее наступило',
                                'body'  => 'Здесь не просто товары — здесь твои амбиции становятся реальностью. А где уверенность, там и крылья.',
                                'link'  => '#',
                                'color' => '#ffffff',
                                'background_color' => '#242F6D',
                            ]
                        ]
                    ]
                ]
            ],
        ],
    ];
}

// Defaults to en_US
return [
    [
        'name'  => 'Your rules',
        'rules' => [
            [
                'rule_type'   => 'banner',
                'rule_params' => [
                    'banners' => [
                        [
                            'type'  => 'link',
                            'image' => 'img/promo-dummy-1.jpg',
                            'title' => 'Your home, your rules',
                            'body'  => 'Set your own rules instead of playing by someone else’s. Build your own platform, your own online store.',
                            'link'  => 'https://www.shop-script.com',
                            'color' => '#ffffff',
                            'background_color' => '#290E0C',
                            'countdown_datetime' => date("Y-m-d H:i:s", strtotime("+1 day")),
                        ]
                    ]
                ]
            ]
        ],
    ],
    [
        'name'  => 'More than just a start',
        'rules' => [
            [
                'rule_type'   => 'banner',
                'rule_params' => [
                    'banners' => [
                        [
                            'type'  => 'link',
                            'image' => 'img/promo-dummy-2.jpg',
                            'title' => 'More than just a start',
                            'body'  => 'Every new day is a new challenge and new achievements. The perfect start and the right mindset determine the result.',
                            'link'  => 'https://www.webasyst.com',
                            'color' => '#ffffff',
                            'background_color' => '#733E25',
                        ]
                    ]
                ]
            ]
        ],
    ],
    [
        'name'  => 'Dreams come true',
        'rules' => [
            [
                'rule_type'   => 'banner',
                'rule_params' => [
                    'banners' => [
                        [
                            'type'  => 'link',
                            'image' => 'img/promo-dummy-3.jpg',
                            'title' => 'Dreams come true',
                            'body'  => 'Every great victory starts with a small dream. Take the first step — bring your dream closer to your ultimate goal.',
                            'link'  => '#',
                            'color' => '#ffffff',
                            'background_color' => '#623D27',
                        ]
                    ]
                ]
            ]
        ],
    ],
    [
        'name'  => 'Trendy mindset',
        'rules' => [
            [
                'rule_type'   => 'banner',
                'rule_params' => [
                    'banners' => [
                        [
                            'type'  => 'link',
                            'image' => 'img/promo-dummy-4.jpg',
                            'title' => 'Trendy mindset',
                            'body'  => 'Being on trend means not chasing, but feeling the rhythm of the times. Follow new opportunities, not just fashion.',
                            'link'  => '#',
                            'color' => '#ffffff',
                            'background_color' => '#213432',
                        ]
                    ]
                ]
            ]
        ],
    ],
    [
        'name'  => 'The future awaits',
        'rules' => [
            [
                'rule_type'   => 'banner',
                'rule_params' => [
                    'banners' => [
                        [
                            'type'  => 'link',
                            'image' => 'img/promo-dummy-5.jpg',
                            'title' => 'The future awaits',
                            'body'  => 'This isn’t just about products — this is where your ambitions become reality. And where there’s confidence, there are wings.',
                            'link'  => '#',
                            'color' => '#ffffff',
                            'background_color' => '#242F6D',
                        ]
                    ]
                ]
            ]
        ],
    ],
];
