<?php
return [
    'telegram' => [
        'class' => 'shopTelegramSalesChannel',
        'name' => /*_w*/('Telegram'),
        'menu_icon' => '<i class="fab fa-telegram-plane"></i>',
        'available' => true,
    ],
    'pos' => [
        'class' => 'shopPosSalesChannel',
        'name' => /*_w*/('Point of Sale'),
        'menu_icon' => '<i class="fas fa-map-marker-alt"></i>',
        'available' => true,
    ],
    'max' => [
        'class' => 'shopMaxSalesChannel',
        'name' => /*_w*/('MAX'),
        'menu_icon' => '<svg width="16" height="16" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M8.1 15.5c-1.5 0-2.2-.2-3.4-1.1-.7 1-3.2 1.7-3.3.4 0-1-.2-1.8-.4-2.7C.7 11 .3 9.7.3 7.9a7.6 7.6 0 0 1 15.2 0c0 4.3-3.4 7.6-7.4 7.6ZM8.1 4c-2-.1-3.6 1.3-4 3.5-.2 1.9.3 4 .7 4.2.3 0 .8-.4 1.1-.7.6.4 1.2.6 1.9.7 2 0 3.9-1.6 4-3.7.1-2-1.6-3.9-3.6-4Z" fill="currentColor"/></svg>',
        'available' => true,
    ],
    'vk' => [
        'class' => 'shopVkSalesChannel',
        'name' => /*_w*/('VK'),
        'menu_icon' => '<i class="fab fa-vk"></i>',
        'available' => false,
    ],
    'widget' => [
        'class' => 'shopWidgetSalesChannel',
        'name' => /*_w*/('Widget'),
        'menu_icon' => '<i class="fas fa-code"></i>',
        'available' => false,
    ],
    'qr' => [
        'class' => 'shopQrSalesChannel',
        'name' => /*_w*/('QR order'),
        'menu_icon' => '<i class="fas fa-qrcode"></i>',
        'available' => false,
    ],
];
