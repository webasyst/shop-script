<?php

class shopInvoiceruPlugin extends shopPrintformPlugin
{
    public function allowedCurrency()
    {
        return array('RUB', 'UAH');
    }

    /**
     * For backward compatibility with SS6 use this method
     * @param array|int|waOrder $order
     * @return string
     */
    public function renderForm($order)
    {
        $locale = 'ru_RU';
        $old_locale = wa()->getLocale();
        if ($locale !== $old_locale) {
            wa()->setLocale($locale);
        }
        $res = parent::renderForm($order);
        if ($locale !== $old_locale) {
            wa()->setLocale($old_locale);
        }
        return $res;
    }

    public function renderPrintform($data)
    {
        $locale = 'ru_RU';
        $old_locale = wa()->getLocale();
        if ($locale !== $old_locale) {
            wa()->setLocale($locale);
        }
        $res = parent::renderPrintform($data);
        if ($locale !== $old_locale) {
            wa()->setLocale($old_locale);
        }
        return $res;
    }

    /**
     * For backward compatibility with SS6 use this method
     * @param waOrder $order
     * @param waView $view
     */
    public function prepareForm(waOrder &$order, waView &$view)
    {
        $settings = $this->getSettings();
        $contact_fields = array(
            'inn'     => 'CUSTOMER_INN_FIELD',
            'kpp'     => 'CUSTOMER_KPP_FIELD',
            'phone'   => 'CUSTOMER_PHONE_FIELD',
            'company' => 'CUSTOMER_COMPANY_FIELD',
        );

        $contact = array();
        foreach ($contact_fields as $field => $setting) {
            $contact[$field] = empty($settings[$setting]) ? '' : $order->getContactField($settings[$setting]);
        }
        $items = $order->items;
        $view->assign(compact('settings', 'items', 'contact'));
    }

    /**
     * For SS7 use this method
     * @param array $data
     * @param waView $view
     * @return array
     */
    public function preparePrintform($data, waView $view)
    {
        $order = $data['order'];
        /**
         * @var waOrder $order
         */
        $settings = $this->getSettings();
        $contact_fields = array(
            'inn'     => 'CUSTOMER_INN_FIELD',
            'kpp'     => 'CUSTOMER_KPP_FIELD',
            'phone'   => 'CUSTOMER_PHONE_FIELD',
            'company' => 'CUSTOMER_COMPANY_FIELD',
        );

        $contact = array();
        foreach ($contact_fields as $field => $setting) {
            $contact[$field] = empty($settings[$setting]) ? '' : $order->getContactField($settings[$setting]);
        }
        $items = $order->items;
        $view->assign(compact('settings', 'items', 'contact'));

        return $data;
    }
}
