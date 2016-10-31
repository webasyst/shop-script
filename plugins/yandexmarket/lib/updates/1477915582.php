<?php

if (!empty($this) && (get_class($this) == 'shopYandexmarketPlugin')) {
    $plugin = $this;
} else {
    $plugin = wa('shop')->getPlugin('yandexmarket');
}

if (!$plugin->getSettings('contact_id')) {


    $contact = new waContact();
    $contact['firstname'] = 'Бот';
    $contact['lastname'] = 'Яндекс.Маркет';
    $contact['about'] = 'Технический контакт для новых заказов, сделанных через API Заказ на Маркете.';
    $contact['create_app_id'] = 'shop';
    $contact['create_contact_id'] = 0;

    $errors = $contact->save();

    if ($errors) {
        waLog::log('Error occurs during save contact: '.var_export($errors, true), 'shop/plugins/yandexmarket/order.error.log');
    } else {
        $contact_id = $contact->getId();
        if ($contact_id) {
            $contact->addToCategory('shop');
        }
        waLog::log("Contact {$contact_id} was created", 'shop/plugins/yandexmarket/order.log');
        $settings = $plugin->getSettings();
        $settings['contact_id'] = $contact_id;
        $plugin->saveSettings($settings);
    }
}
