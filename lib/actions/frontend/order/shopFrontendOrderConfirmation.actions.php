<?php

class shopFrontendOrderConfirmationActions extends waJsonActions
{
    protected $channels = null;

    public function defaultDialogAction()
    {
        $source = waRequest::post('source', '', waRequest::TYPE_STRING);

        $confirmation = new shopConfirmationChannel();
        $checkout_config = new shopCheckoutConfig(true);

        $confirmation->validateSource($source);

        $view = wa('shop')->getView();
        $view->assign(array(
            'source'         => $source,
            'type'           => $confirmation->getActiveType(),
            'recode_timeout' => $checkout_config['confirmation']['recode_timeout'],
            'is_last_channel' => $this->isLastChannelToConfirm(),
        ));

        $html = $view->fetch(wa()->getAppPath('templates/actions/frontend/order/form/dialog/channel_confirmation.html', 'shop'));
        $this->response['confirmation_dialog'] = $html;
    }

    /**
     * @return bool
     */
    protected function isLastChannelToConfirm()
    {
        $confirmation = new shopConfirmationChannel();
        $last_channel = true;

        $confirmed = $confirmation->getStorage('confirmed');
        $unconfirmed = $confirmation->getStorage('unconfirmed');

        if (count($unconfirmed) - count($confirmed) > 1) {
            $last_channel = false;
        }

        return $last_channel;
    }

    /**
     * В этом методе не передается в конструктор значение по следующим соображениям:
     * Если человек в форме изменил изменил значение адреса, то пусть его подтверждает.
     * Чекаут все равно на шаге auth потом провалидирует значения, которые пользователь ввел.
     */
    public function sendConfirmationCodeAction()
    {
        $confirmation = new shopConfirmationChannel();

        $source = waRequest::post('source', '', waRequest::TYPE_STRING);
        $source = $confirmation->cleanSource($source, $confirmation->getActiveType());
        $confirmation->validateSource($source);

        $invalid_transport = $confirmation->getTransportError();
        if ($invalid_transport) {
            $this->errors[] = $invalid_transport;
            return null;
        }

        $timeout_left = $confirmation->getSendTimeout();
        if ($timeout_left > 0) {
            $this->errors[] = [
                'id'   => 'timeout_error',
                'text' => _w('Wait for %d second', 'Wait for %d seconds', $timeout_left)
            ];

            return null;
        }

        if (!$source || !$confirmation->isValidateSource($source)) {
            $this->errors[] = [
                'id'   => 'source_error',
                'text' => _w('Incorrect data specified to send a code')
            ];
            return null;
        }

        if (!$confirmation->sendCode($source)) {
            $this->errors[] = [
                'id'   => 'send_error',
                'text' => _w('Code sending error')
            ];
            return null;
        }

        $verification = [
            'source'   => $source,
            'attempts' => shopConfirmationChannel::ATTEMPTS_TO_VERIFY_CODE,
        ];

        $confirmation->setStorage($verification, 'verification');
        $confirmation->setStorage(time(), 'send_time');
    }

    public function validateConfirmationCodeAction()
    {
        $confirmation = new shopConfirmationChannel();
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
                'text' => _w('Code has not been sent')
            ];
            return null;
        }

        $result = $confirmation->validateCode($code);

        if (!$result['status']) {
            if (is_null($result['details']['rest_tries']) || $result['details']['rest_tries'] == 0) {
                $this->errors[] = [
                    'id'   => 'code_attempts_error',
                    'text' => _w('You have run out of available attempts. Please request a new code.')
                ];
                $confirmation->delStorage('verification');
            } else {
                $this->errors[] = [
                    'id'   => 'code_error',
                    'text' => _w('You have entered an incorrect code. %d more attempt is available.', 'You have entered an incorrect code. %d more attempts are available.', $result['details']['rest_tries']),
                ];
            }
        } else {
            $confirmation->setConfirmed();
        }
    }

}
