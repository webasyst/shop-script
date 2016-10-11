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
        return $template;
    }

    protected static function getOrderConfirmedTemplate() {
        $template = file_get_contents(wa('shop')->getAppPath('templates/mail/Order.confirmed.html'));
        return $template;
    }

    protected static function getOrderShipmentTemplate() {
        $template = file_get_contents(wa('shop')->getAppPath('templates/mail/Order.shipment.html'));
        return $template;
    }

    protected static function getOrderCancelTemplate() {
        $template = file_get_contents(wa('shop')->getAppPath('templates/mail/Order.cancel.html'));
        return $template;
    }

    protected static function getOrderStatusChangeTemplate() {
        $template = file_get_contents(wa('shop')->getAppPath('templates/mail/Order.status_change.html'));
        return $template;
    }

    public function getTemplates()
    {
        $result = array();

        /* NEW ORDER email notification template */
        $result['order.create']['subject'] = '{sprintf("[`New order %s`]", $order.id)}';
        $result['order.create']['body'] = self::getOrderCreateTemplate();
        $result['order.create']['sms'] = '[`We successfully accepted your order, and will contact you asap.`]' . ' {sprintf("[`Your order number is %s. Order total: %s`]", $order.id, wa_currency($order.total, $order.currency))}';

        /* order was CONFIRMED (accepted for processing) */
        $result['order.process']['subject'] = '{sprintf("[`Order %s has been confirmed`]", $order.id)}';
        $result['order.process']['body'] = self::getOrderConfirmedTemplate();
        $result['order.process']['sms'] = '{sprintf("[`Your order %s has been confirmed and accepted for processing.`]", $order.id)}';

        /* order SHIPMENT (sending out) email notification template */
        $result['order.ship']['subject'] = '{sprintf("[`Order %s has been sent out!`]", $order.id)}';
        $result['order.ship']['body'] = self::getOrderShipmentTemplate();
        $result['order.ship']['sms'] = '{sprintf("[`Your order %s has been sent out!`]", $order.id)}' . '{if !empty($action_data.params.tracking_number)} ' . '[`Tracking number`]: {$action_data.params.tracking_number}' . '{/if}';

        /* order CANCELLATION email notification template */
        $result['order.delete']['subject'] = '{sprintf("[`Order %s has been cancelled`]", $order.id)}';
        $result['order.delete']['body'] = self::getOrderCancelTemplate();
        $result['order.delete']['sms'] = '{sprintf("[`Your order %s has been cancelled`]", $order.id)}';

        /* MISC order status change email notification template */
        $result['order']['subject'] = '{sprintf("[`Order %s has been updated`]", $order.id)}';
        $result['order']['body'] = self::getOrderStatusChangeTemplate();
        $result['order']['sms'] = '{sprintf("[`Your order %s status has been updated to “%s”`]", $order.id, $status)}';

        return $result;
    }
}
