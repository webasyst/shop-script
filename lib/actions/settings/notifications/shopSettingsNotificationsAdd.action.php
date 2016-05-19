<?php
class shopSettingsNotificationsAddAction extends shopSettingsNotificationsAction
{
    public function execute()
    {
        $this->view->assign('events', $this->getEvents());
        $this->view->assign('transports', self::getTransports());
        $this->view->assign('templates', $this->getTemplates());
        $this->view->assign('default_email_from', $this->getConfig()->getGeneralSettings('email'));
        $this->view->assign('sms_from', $this->getSmsFrom());
        $this->view->assign('routes', wa()->getRouting()->getByApp('shop'));
    }

    protected static function getOrderCreateTemplate() {
        $template = file_get_contents(wa('shop')->getAppPath('templates/mail/Order.create.html'));
        $locales = array(
            "%1%" => _w('Qty'),
            "%2%" => _w('Total'),
            "%3%" => _w('Download'),
            "%4%" => _w('Subtotal'),
            "%5%" => _w('Discount'),
            "%6%" => _w('Shipping'),
            "%7%" => _w('Tax'),
            "%9%" => _w('Contact info'),
            "%10%" => _w('Email'),
            "%11%" => _w('Ship to'),
            "%12%" => _w('Bill to'),
            "%13%" => _w('Phone'),
            "%14%" => _w('Comment to the order'),
            "%15%" => _w('View and manage your order'),
            "%16%" => _w('PIN'),
            "%17%" => sprintf( _w('Thank you for shopping at %s!'), '{$wa->shop->settings("name")}')
        );

        foreach ($locales as $index => $locale) {
            $template = str_replace($index, $locale, $template);
        }

        return $template;
    }

    protected static function getOrderConfirmedTemplate() {
        $template = file_get_contents(wa('shop')->getAppPath('templates/mail/Order.confirmed.html'));
        $locales = array(
            "%1%" => sprintf( _w('Hi %s'), '{$customer->get("name", "html")}'),
            "%2%" => sprintf( _w('Your order %s has been confirmed and accepted for processing.'), '{$order.id}'),
            "%contact_info%" => _w('Contact info'),
            "%16%" => _w('PIN'),
            "%17%" => sprintf( _w('Thank you for shopping at %s!'), '{$wa->shop->settings("name")}')
        );

        foreach ($locales as $index => $locale) {
            $template = str_replace($index, $locale, $template);
        }

        return $template;
    }

    protected static function getOrderShipmentTemplate() {
        $template = file_get_contents(wa('shop')->getAppPath('templates/mail/Order.shipment.html'));
        $locales = array(
            "%1%" => sprintf( _w('Hi %s'), '{$customer->get("name", "html")}'),
            "%2%" => sprintf( _w('Your order %s has been shipped!'), '{$order.id}'),
            "%3%" => sprintf( _w('The shipment tracking number is <strong>%s</strong>'), '{$action_data.params.tracking_number|escape}'),
            "%contact_info%" => _w('Contact info'),
            "%16%" => _w('PIN'),
            "%17%" => sprintf( _w('Thank you for shopping at %s!'), '{$wa->shop->settings("name")}')
        );

        foreach ($locales as $index => $locale) {
            $template = str_replace($index, $locale, $template);
        }

        return $template;
    }

    protected static function getOrderCancelTemplate() {
        $template = file_get_contents(wa('shop')->getAppPath('templates/mail/Order.cancel.html'));
        $locales = array(
            "%1%" => sprintf( _w('Hi %s'), '{$customer.name|escape}'),
            "%2%" => sprintf( _w('Your order %s has been cancelled. If you want your order to be re-opened, please contact us.'), '{$order.id}'),
            "%contact_info%" => _w('Contact info'),
            "%16%" => _w('PIN'),
            "%17%" => sprintf( _w('Thank you for shopping at %s!'), '{$wa->shop->settings("name")}')
        );

        foreach ($locales as $index => $locale) {
            $template = str_replace($index, $locale, $template);
        }

        return $template;
    }

    protected static function getOrderStatusChangeTemplate() {
        $template = file_get_contents(wa('shop')->getAppPath('templates/mail/Order.status_change.html'));
        $locales = array(
            "%1%" => sprintf( _w('Hi %s'), '{$customer.name|escape}'),
            "%2%" => sprintf( _w('Your order %s status has been updated to <strong>%s</strong>'), '{$order.id}', '{$status}'),
            "%contact_info%" => _w('Contact info'),
            "%16%" => _w('PIN'),
            "%17%" => sprintf( _w('Thank you for shopping at %s!'), '{$wa->shop->settings("name")}')
        );

        foreach ($locales as $index => $locale) {
            $template = str_replace($index, $locale, $template);
        }

        return $template;
    }

    public function getTemplates()
    {
        $result = array();

        /* NEW ORDER email notification template */
        $result['order.create']['subject'] = sprintf( _w('New order %s'), '{$order.id}');
        $result['order.create']['body'] = self::getOrderCreateTemplate();
        $result['order.create']['sms'] = _w('We successfully accepted your order, and will contact you asap.') . ' ' . sprintf( _w('Your order number is %s. Order total: %s'), '{$order.id}', '{wa_currency($order.total, $order.currency)}');

        /* order was CONFIRMED (accepted for processing) */
        $result['order.process']['subject'] = sprintf( _w('Order %s has been confirmed'), '{$order.id}');
        $result['order.process']['body'] = self::getOrderConfirmedTemplate();
        $result['order.process']['sms'] = sprintf( _w('Your order %s has been confirmed and accepted for processing.'), '{$order.id}');


        /* order SHIPMENT (sending out) email notification template */
        $result['order.ship']['subject'] = sprintf( _w('Order %s has been sent out!'), '{$order.id}');
        $result['order.ship']['body'] = self::getOrderShipmentTemplate();
        $result['order.ship']['sms'] = sprintf( _w('Your order %s has been sent out!'), '{$order.id}' ) . '{if !empty($action_data.params.tracking_number)} '. _w('Tracking number').': {$action_data.params.tracking_number}' . '{/if}';


        /* order CANCELLATION email notification template */
        $result['order.delete']['subject'] = sprintf( _w('Order %s has been cancelled'), '{$order.id}');
        $result['order.delete']['body'] = self::getOrderCancelTemplate();
        $result['order.delete']['sms'] = sprintf( _w('Your order %s has been cancelled'), '{$order.id}');


        /* MISC order status change email notification template */
        $result['order']['subject'] = sprintf( _w('Order %s has been updated'), '{$order.id}');
        $result['order']['body'] = self::getOrderStatusChangeTemplate();
        $result['order']['sms'] = sprintf( _w('Your order %s status has been updated to “%s”'), '{$order.id}', '{$status}');

        return $result;

    }
}
