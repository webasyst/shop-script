<?php

class shopOrderSendprintformController extends waJsonController
{
    public function execute()
    {
        try {
            $order_id = waRequest::post('order_id', 0, waRequest::TYPE_INT);
            if (empty($order_id)) {
                $this->setError('Missed order id');
            } else {
                if ($plugin_id = waRequest::get('plugin_id')) {
                    $plugin = wa()->getPlugin($plugin_id);
                    if (method_exists($plugin, 'sendForm')) {
                        /**
                         * @var shopPrintformPlugin $plugin
                         */
                        $plugin->sendForm($order_id, true);
                    } else {
                        $this->setError('Plugin not support sending form via email');
                    }
                } elseif ($form_id = waRequest::get('form_id')) {
                    if (strpos($form_id, '.')) {
                        list($type, $form) = explode('.', $form_id, 2);
                    } else {
                        $form = null;
                        $type = $form_id;
                    }

                    $om = new shopOrderModel();

                    $order = $om->getOrder($order_id);
                    if (!$order) {
                        throw new waException('Order not found', 404);
                    }

                    $params = $order['params'];
                    switch ($type) {
                        case 'payment':
                            if (!empty($params['payment_id'])) {
                                $plugin = shopPayment::getPlugin(null, $params['payment_id']);
                            }
                            break;
                        case 'shipping':
                            if (!empty($params['shipping_id'])) {
                                $plugin = shopShipping::getPlugin(null, $params['shipping_id']);
                            }
                            break;
                    }
                    if (!empty($plugin)) {
                        /**
                         * @var waShipping|waPayment $plugin
                         */
                        $order = shopPayment::getOrderData($order, $plugin);
                        $forms = $plugin->getPrintForms($order);
                        if (!isset($forms[$form])) {
                            $this->setError('Printform not found');
                        } elseif (empty($forms[$form]['emailprintform'])) {
                            $this->setError('Plugin not support sending via email');
                        } else {
                            if ($order->contact_email) {
                                $mail = new waMailMessage();
                                $mail->setBody($plugin->displayPrintForm($form, $order));
                                $mail->setSubject(sprintf(_w('Printform %s for order %s'), $forms[$form]['name'], $order->id_str));
                                $mail->setTo($order->contact_email, $order->contact_name);
                                $from = $this->getConfig()->getGeneralSettings('email');
                                $mail->setFrom($from, $this->getConfig()->getGeneralSettings('name'));
                                if ($mail->send()) {
                                    $log = sprintf(_w("Printform <strong>%s</strong> sent to customer."), $forms[$form]['name']);
                                    $order_log_model = new shopOrderLogModel();
                                    $order_log_model->add(array(
                                        'order_id'        => $order->id,
                                        'contact_id'      => null,
                                        'action_id'       => '',
                                        'text'            => '<i class="icon16 email"></i> '.$log,
                                        'before_state_id' => $order->state_id,
                                        'after_state_id'  => $order->state_id,
                                    ));
                                }
                            }
                        }

                    } else {
                        $this->setError('Plugin not found');
                    }
                } else {
                    $this->setError('Missed printform identity');
                }
            }
        } catch (waException $ex) {
            $this->setError($ex->getMessage());
        }
    }
}
