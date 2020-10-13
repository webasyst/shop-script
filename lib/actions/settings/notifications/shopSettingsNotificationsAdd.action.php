<?php

class shopSettingsNotificationsAddAction extends shopSettingsNotificationsAction
{
    public function execute()
    {
        $routes = wa()->getRouting()->getByApp('shop');
        $domains = wa()->getRouting()->getAliases();
        $active_domains = array();
        foreach ($domains as $mirror => $domain) {
            if (isset($routes[$domain])) {
                $active_domains[$domain][] = $mirror;
            }
        }

        $this->view->assign('events', $this->getEvents());
        $this->view->assign('availability_sms_plugins', $this->checkAvailabilitySmsPlugins());
        $this->view->assign('transports', self::getTransports());
        $this->view->assign('templates', $this->getTemplates());
        $this->view->assign('default_email_from', $this->getConfig()->getGeneralSettings('email'));
        $this->view->assign('sms_from', $this->getSmsFrom());
        $this->view->assign('active_domains', $active_domains);
        $this->view->assign('routes', $routes);
        $this->view->assign('backend_notification_add', wa()->event('backend_notification_add'));
    }

    protected static function getOrderCreateTemplate()
    {
        $template = file_get_contents(wa('shop')->getAppPath('templates/mail/Order.create.html'));
        $locales = array(
            "%1%"  => _w('Qty'),
            "%2%"  => _w('Total'),
            "%3%"  => _w('Download'),
            "%4%"  => _w('Subtotal'),
            "%5%"  => _w('Discount'),
            "%6%"  => _w('Shipping'),
            "%7%"  => _w('Tax'),
            "%9%"  => _w('Contact info'),
            "%10%" => _w('Email'),
            "%11%" => _w('Shipping'),
            "%12%" => _w('Payment'),
            "%13%" => _w('Phone'),
            "%14%" => _w('Comment to the order'),
            "%15%" => _w('View and manage your order'),
            "%16%" => _w('PIN'),
            "%17%" => sprintf(_w('Thank you for shopping at %s!'), '{$wa->shop->settings("name")}')
        );

        foreach ($locales as $index => $locale) {
            $template = str_replace($index, $locale, $template);
        }

        return $template;
    }

    protected static function getOrderConfirmedTemplate()
    {
        $template = self::getMailTemplate('Order.confirmed.html');
        $locales = array(
            "%1%"            => sprintf(_w('Hi %s'), '{$customer->get("name", "html")}'),
            "%2%"            => sprintf(_w('Your order %s has been confirmed and accepted for processing.'), '{$order.id}'),
            "%contact_info%" => _w('Contact info'),
            "%16%"           => _w('PIN'),
            "%17%"           => sprintf(_w('Thank you for shopping at %s!'), '{$wa->shop->settings("name")}')
        );

        foreach ($locales as $index => $locale) {
            $template = str_replace($index, $locale, $template);
        }

        return $template;
    }

    protected static function getOrderShipmentTemplate()
    {
        $template = self::getMailTemplate('Order.shipment.html');
        $locales = array(
            "%1%"            => sprintf(_w('Hi %s'), '{$customer->get("name", "html")}'),
            "%2%"            => sprintf(_w('Your order %s has been shipped!'), '{$order.id}'),
            "%3%"            => sprintf(_w('The shipment tracking number is <strong>%s</strong>'), '{$action_data.params.tracking_number|escape}'),
            "%contact_info%" => _w('Contact info'),
            "%16%"           => _w('PIN'),
            "%17%"           => sprintf(_w('Thank you for shopping at %s!'), '{$wa->shop->settings("name")}')
        );

        foreach ($locales as $index => $locale) {
            $template = str_replace($index, $locale, $template);
        }

        return $template;
    }

    protected static function getOrderCancelTemplate()
    {
        $template = self::getMailTemplate('Order.cancel.html');
        $locales = array(
            "%1%"            => sprintf(_w('Hi %s'), '{$customer.name|escape}'),
            "%2%"            => sprintf(_w('Your order %s has been cancelled. If you want your order to be re-opened, please contact us.'), '{$order.id}'),
            "%contact_info%" => _w('Contact info'),
            "%16%"           => _w('PIN'),
            "%17%"           => sprintf(_w('Thank you for shopping at %s!'), '{$wa->shop->settings("name")}')
        );

        foreach ($locales as $index => $locale) {
            $template = str_replace($index, $locale, $template);
        }

        return $template;
    }

    protected static function getOrderStatusChangeTemplate()
    {
        $template = self::getMailTemplate('Order.status_change.html');
        $locales = array(
            "%1%"            => sprintf(_w('Hi %s'), '{$customer.name|escape}'),
            "%2%"            => sprintf(_w('Your order %s status has been updated to <strong>%s</strong>'), '{$order.id}', '{$status}'),
            "%contact_info%" => _w('Contact info'),
            "%16%"           => _w('PIN'),
            "%17%"           => sprintf(_w('Thank you for shopping at %s!'), '{$wa->shop->settings("name")}')
        );

        foreach ($locales as $index => $locale) {
            $template = str_replace($index, $locale, $template);
        }

        return $template;
    }

    protected static function getOrderCommentTemplate()
    {
        $template = self::getMailTemplate('Order.comment.html');
        $locales = array(
            "%1%"            => sprintf(_w('Hi %s'), '{$customer.name|escape}'),
            "%contact_info%" => _w('Contact info'),
            "%16%"           => _w('PIN'),
            "%17%"           => sprintf(_w('Thank you for shopping at %s!'), '{$wa->shop->settings("name")}')
        );

        foreach ($locales as $index => $locale) {
            $template = str_replace($index, $locale, $template);
        }

        return $template;
    }

    protected static function getOrderEditShippingDetailsTemplate()
    {
        $template = self::getMailTemplate('Order.editshippingdetails.html');
        $locales = array(
            "%1%"            => sprintf(_w('Hi %s'), '{$customer.name|escape}'),
            "%contact_info%" => _w('Contact info'),
            "%16%"           => _w('PIN'),
            "%17%"           => sprintf(_w('Thank you for shopping at %s!'), '{$wa->shop->settings("name")}')
        );

        foreach ($locales as $index => $locale) {
            $template = str_replace($index, $locale, $template);
        }

        return $template;
    }

    protected static function getOrderSettleTemplate()
    {
        $template = self::getMailTemplate('Order.settle.html');
        $locales = array(
            "%1%"            => sprintf(_w('Hi %s'), '{$customer.name|escape}'),
            "%contact_info%" => _w('Contact info'),
            "%16%"           => _w('PIN'),
            "%17%"           => sprintf(_w('Thank you for shopping at %s!'), '{$wa->shop->settings("name")}')
        );

        foreach ($locales as $index => $locale) {
            $template = str_replace($index, $locale, $template);
        }

        return $template;
    }

    protected static function getMailTemplate($file)
    {
        $template = file_get_contents(wa('shop')->getAppPath('templates/mail/'.$file));
        if (function_exists('smarty_prefilter_translate')) {
            $null = null;
            $template = smarty_prefilter_translate($template, $null);
        }
        return $template;
    }

    public function getTemplates()
    {
        $result = array();


        /* NEW ORDER email notification template */
        $sms = _w('We successfully accepted your order, and will contact you asap.').' ';
        $sms .= sprintf(
            _w('Your order number is %s. Order total: %s'),
            '{$order.id}',
            '{wa_currency($order.total, $order.currency)}'
        );
        $result['order.create'] = array(
            'description' => _w('Order placed by a customer or created by an administrator in backend.'),
            'subject'     => sprintf(_w('New order %s'), '{$order.id}'),
            'body'        => self::getOrderCreateTemplate(),
            'sms'         => $sms,

        );


        /* order was CONFIRMED (accepted for processing) */
        $result['order.process'] = array(
            'description' => _w('Execution of “Process” action in backend.'),
            'subject'     => sprintf(_w('Order %s has been confirmed'), '{$order.id}'),
            'body'        => self::getOrderConfirmedTemplate(),
            'sms'         => sprintf(_w('Your order %s has been confirmed and accepted for processing.'), '{$order.id}'),

        );


        /* order SHIPMENT (sending out) email notification template */
        $sms = sprintf(_w('Your order %s has been sent out!'), '{$order.id}');
        $sms .= '{if !empty($action_data.params.tracking_number)} ';
        $sms .= _w('Tracking number');
        $sms .= ': {$action_data.params.tracking_number}';
        $sms .= '{/if}';

        $result['order.ship'] = array(
            'description' => _w('Execution of “Sent” action in backend.'),
            'subject'     => sprintf(_w('Order %s has been sent out!'), '{$order.id}'),
            'body'        => self::getOrderShipmentTemplate(),
            'sms'         => $sms,

        );


        /* order CANCELLATION email notification template */
        $result['order.delete'] = array(
            'description' => _w('Execution of “Delete” action in backend.'),
            'subject'     => sprintf(_w('Order %s has been cancelled'), '{$order.id}'),
            'body'        => self::getOrderCancelTemplate(),
            'sms'         => sprintf(_w('Your order %s has been cancelled'), '{$order.id}'),
        );


        /* order COMMENTING email notification template */
        $result['order.comment'] = array(
            'description' => _w('Adding of a comment to an order in backend.'),
            'subject'     => sprintf(_w('A comment was added to your order %s'), '{$order.id}'),
            'body'        => self::getOrderCommentTemplate(),
            'sms'         => sprintf(_w('A comment was added to your order %s'), '{$order.id}'),
        );


        /* editing order SHIPPING DETAILS email notification template */
        $result['order.editshippingdetails'] = array(
            'description' => _w('Editing of order shipping details in backend.'),
            'subject'     => sprintf(_w('Shipping details of your order %s have been changed'), '{$order.id}'),
            'body'        => self::getOrderEditShippingDetailsTemplate(),
            'sms'         => sprintf(_w('Shipping details of your order %s have been changed'), '{$order.id}'),
        );


        /* order SETTLING email notification template */
        $result['order.settle'] = array(
            'description' => _w('Merging of an order without an ID, which was paid via a mobile terminal, with another order in backend.'),
            'subject'     => sprintf(_w('Your order %s has been settled'), '{$order.id}'),
            'body'        => self::getOrderSettleTemplate(),
            'sms'         => sprintf(_w('Your order %s has been settled'), '{$order.id}'),
        );


        $result['order.refund']['description'] = _w('Execution of “Refund” action in backend.');
        $result['order.edit']['description'] = _w('Saving of changes made in an edited order in backend.');
        $result['order.restore']['description'] = _w('Execution of “Restore” action in backend.');
        $result['order.complete']['description'] = _w('Execution of “Mark as Completed” action in backend.');
        $result['order.message']['description'] = _w('Sending of a message to a customer by an administrator in backend.');
        $result['order.callback']['description'] = _w('Registration of an automatic callback from a payment gateway; e.g., to update the order status. Depending on the callback result, additionally action “Paid” may be called afterwards.');
        $result['order.pay']['description'] = _w('Execution of “Paid” action manually in backend or automatically by a callback from a payment gateway.');


        /* MISC order status change email notification template */
        $result['order'] = array(
            'description' => '',
            'subject'     => sprintf(_w('Order %s has been updated'), '{$order.id}'),
            'body'        => self::getOrderStatusChangeTemplate(),
            'sms'         => sprintf(_w('Your order %s status has been updated to “%s”'), '{$order.id}', '{$status}'),
        );

        foreach ($result as $id => &$item) {
            if (strpos($id, 'order.') === 0) {
                $item += $result['order'];
            }
            unset($item);
        }
        return $result;
    }

    protected function checkAvailabilitySmsPlugins()
    {
        $installed_adapter_ids = waSMS::getInstalledAdapterIds();
        return !empty($installed_adapter_ids);
    }
}
