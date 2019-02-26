<?php

class shopFrontendOrderConfirmationActions extends waJsonActions
{
    protected $channels = null;


    public function defaultDialogAction()
    {
        $confirmation = shopConfirmationChannel::getInstance();
        $invalid_transport = $confirmation->getTransportError();
        if ($invalid_transport) {
            $this->errors[] = $invalid_transport;
            return null;
        }

        $checkout_config = new shopCheckoutConfig(true);

        if ($checkout_config['confirmation']['order_without_auth'] === 'confirm_contact') {
            $require_confirmation = true;
        } else {
            $require_confirmation = false;
        }

        $view = wa('shop')->getView();

        $view->assign(array(
            'source'               => waRequest::post('source', '', waRequest::TYPE_STRING),
            'type'                 => $confirmation->getTransport(),
            'recode_timeout'       => $checkout_config['confirmation']['recode_timeout'],
            'require_confirmation' => $require_confirmation
        ));

        $html = $view->fetch(wa()->getAppPath('templates/actions/frontend/order/form/dialog/channel_confirmation.html', 'shop'));

        $this->response['confirmation_dialog'] = $html;
    }

    public function sendConfirmationCodeAction()
    {
        $confirmation = shopConfirmationChannel::getInstance();
        $source = waRequest::post('source', '', waRequest::TYPE_STRING);
        $source = $confirmation->cleanSource($source, $confirmation->getTransport());

        $invalid_transport = $confirmation->getTransportError();
        if ($invalid_transport) {
            $this->errors[] = $invalid_transport;
            return null;
        }

        $timeout_left = $confirmation->getSendTimeout();
        if ($timeout_left > 0) {
            $this->errors[] = [
                'id'   => 'timeout_error',
                'text' => sprintf(_w('Todo Подождите %d секунд'), $timeout_left)
            ];

            return null;
        }

        if (!$source || !$confirmation->isValidateSource($source)) {
            $this->errors[] = [
                'id'   => 'source_error',
                'text' => _w('TODO Неправильно указан источник')
            ];
            return null;
        }

        if (!$confirmation->sendCode($source)) {
            $this->errors[] = [
                'id'   => 'send_error',
                'text' => _w('TODO Ошибка отправки кода')
            ];
            return null;
        }

        $verification = [
            'source'   => $source,
            'attempts' => $confirmation::ATTEMPTS_TO_VERIFY_CODE,
        ];

        $confirmation->setStorage($verification, 'verification');
        $confirmation->setStorage(time(), 'send_time');
    }

    public function validateConfirmationCodeAction()
    {
        $confirmation = shopConfirmationChannel::getInstance();
        $code = waRequest::post('code', '', waRequest::TYPE_STRING);

        $invalid_transport = $confirmation->getTransportError();
        if ($invalid_transport) {
            $this->errors[] = $invalid_transport;
            return null;
        }

        $verification = $confirmation->getStorage('verification');
        if (!$verification) {
            $this->errors[] = [
                'id'   => 'storage_error',
                'text' => _w('TODO Код не отправлен')
            ];
            return null;
        }

        $result = $confirmation->isValidateCode($code);
        if (!$result) {
            $attempts = $verification['attempts'];
            $attempts = $attempts - 1;

            if ($attempts <= 0) {
                $this->errors[] = [
                    'id'   => 'code_attempts_error',
                    'text' => _w('TODO У вас закончились попытки ввода. Отправьте смс еще раз')
                ];
                $confirmation->delStorage('verification');
            } else {
                $this->errors[] = [
                    'id'   => 'code_error',
                    'text' => sprintf(_w('TODO Вы ввели не верный код. У вас осталось %d попыток'), $attempts)
                ];

                $verification['attempts'] = $attempts;
                $confirmation->setStorage($verification, 'verification');
            }
        } else {
            $confirmation->verifyTransportStatus();
        }
    }

}