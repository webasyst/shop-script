<?php

if (wa()->getLocale() == 'ru_RU') {
    return [
        [
            'name'  => 'Онлайн-заказы',
            'rules' => [
                [
                    'rule_type'   => 'banner',
                    'rule_params' => [
                        'banners' => [
                            [
                                'type'  => 'link',
                                'image' => 'img/promo-dummy-1.jpg',
                                'title' => 'Онлайн-заказ',
                                'body'  => 'Заказывайте с помощью компьютера, планшета или телефона.',
                                'link'  => '#',
                                'color' => '#ffffff',
                            ]
                        ]
                    ]
                ]
            ],
        ],
        [
            'name'  => 'Быстрая доставка',
            'rules' => [
                [
                    'rule_type'   => 'banner',
                    'rule_params' => [
                        'banners' => [
                            [
                                'type'  => 'link',
                                'image' => 'img/promo-dummy-2.jpg',
                                'title' => 'Быстрая доставка',
                                'body'  => 'Закажите до 20:00 — доставим уже завтра утром!',
                                'link'  => '#',
                                'color' => '#ffffff',
                            ]
                        ]
                    ]
                ]
            ],
        ],
        [
            'name'  => 'Скидки до 30%',
            'rules' => [
                [
                    'rule_type'   => 'banner',
                    'rule_params' => [
                        'banners' => [
                            [
                                'type'  => 'link',
                                'image' => 'img/promo-dummy-3.jpg',
                                'title' => 'Скидки до 30%',
                                'body'  => 'Распродажа зимней коллекции по суперценам. Не пропустите!',
                                'link'  => '#',
                                'color' => '#ffffff',
                            ]
                        ]
                    ]
                ]
            ],
        ],
        [
            'name'  => 'Товар недели',
            'rules' => [
                [
                    'rule_type'   => 'banner',
                    'rule_params' => [
                        'banners' => [
                            [
                                'type'  => 'link',
                                'image' => 'img/promo-dummy-4.jpg',
                                'title' => 'Товар недели',
                                'body'  => 'Заказывайте сегодня по лучшей цене и получите подарок.',
                                'link'  => '#',
                                'color' => '#ffffff',
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
        'name'  => 'Mobile friendly',
        'rules' => [
            [
                'rule_type'   => 'banner',
                'rule_params' => [
                    'banners' => [
                        [
                            'type'  => 'link',
                            'image' => 'img/promo-dummy-1.jpg',
                            'title' => 'Mobile friendly',
                            'body'  => 'Order on your computer, tablet, or mobile device.',
                            'link'  => '#',
                            'color' => '#ffffff',
                        ]
                    ]
                ]
            ]
        ],
    ],
    [
        'name'  => 'Up to 30% off',
        'rules' => [
            [
                'rule_type'   => 'banner',
                'rule_params' => [
                    'banners' => [
                        [
                            'type'  => 'link',
                            'image' => 'img/promo-dummy-2.jpg',
                            'title' => 'Up to 30% off',
                            'body'  => 'Enjoy our super-duper special offers.',
                            'link'  => '#',
                            'color' => '#ffffff',
                        ]
                    ]
                ]
            ]
        ],
    ],
    [
        'name'  => 'Deal of the week',
        'rules' => [
            [
                'rule_type'   => 'banner',
                'rule_params' => [
                    'banners' => [
                        [
                            'type'  => 'link',
                            'image' => 'img/promo-dummy-3.jpg',
                            'title' => 'Deal of the week',
                            'body'  => 'Order today and get an awesome free gift.',
                            'link'  => '#',
                            'color' => '#ffffff',
                        ]
                    ]
                ]
            ]
        ],
    ],
    [
        'name'  => 'Free shipping',
        'rules' => [
            [
                'rule_type'   => 'banner',
                'rule_params' => [
                    'banners' => [
                        [
                            'type'  => 'link',
                            'image' => 'img/promo-dummy-4.jpg',
                            'title' => 'Free shipping',
                            'body'  => 'Free domestic shipping for all order over $50.',
                            'link'  => '#',
                            'color' => '#ffffff',
                        ]
                    ]
                ]
            ]
        ],
    ],
];
