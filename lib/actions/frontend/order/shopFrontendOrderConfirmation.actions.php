<?php

class shopFrontendOrderConfirmationActions extends waJsonActions
{

    protected $is_change_info = [];

    public function defaultDialogAction()
    {
        $checkout_config = new shopCheckoutConfig(true);

        $view = wa('shop')->getView();

        $view->assign(array(
            'email'              => waRequest::post('email', '', waRequest::TYPE_STRING),
            'phone'              => waRequest::post('phone', '', waRequest::TYPE_STRING),
            'checkout_config'    => $checkout_config,
            'auth_with_code'     => $this->getAuthWitCodeStatus($checkout_config),
            'recode_timeout'     => $checkout_config['confirmation']['recode_timeout'],
            'channels'           => $this->getRawChannels(),
        ));

        $html = $view->fetch(wa()->getAppPath('templates/actions/frontend/order/form/dialog/channel_confirmation.html', 'shop'));

        $this->response['confirmation_dialog'] = $html;
    }


    protected function getAuthWitCodeStatus($checkout_config)
    {
        $order_without_auth = $checkout_config['confirmation']['auth_with_code'];
        $is_change_info = $this->isUserChangeContactInfo();

        if (!$is_change_info) {
            $order_without_auth = false;
        }

        return $order_without_auth;
    }

    public function isUserChangeContactInfo()
    {
        if (is_array($this->is_change_info)) {
            return $this->is_change_info;
        }

        $user = wa()->getUser();
        $result = [];

        if (!$user->isAuth()) {
            return $result;
        }

        $new_email = waRequest::post('email', '', waRequest::TYPE_STRING);
        $new_phone = waContactPhoneField::cleanPhoneNumber(waRequest::post('phone', '', waRequest::TYPE_STRING));
        $channels = $this->getRawChannels();

        foreach ($channels as $channel) {
            switch ($channel->getType()){
                case 'sms': {
                    $saved_phone_data = $user->get('phone');
                    $saved_phone = waContactPhoneField::cleanPhoneNumber(ifset($saved_phone_data, 'value', null));
                    $saved_phone_status = ifset($saved_phone_data, 'status', waContactDataModel::STATUS_UNCONFIRMED);

                    if (!$saved_phone || $saved_phone_status !== waContactDataModel::STATUS_CONFIRMED || $saved_phone !== $new_phone) {
                        $result[] = 'phone';
                    }
                }
                case 'email': {
                    $saved_email_data = $user->get('email');
                    $saved_email = ifset($saved_email_data, 'value', null);
                    $saved_email_status = ifset($saved_email_data, 'status', waContactEmailsModel::STATUS_UNCONFIRMED);

                    if (!$saved_email || $saved_email_status !== waContactEmailsModel::STATUS_CONFIRMED || $saved_email !== $new_email) {
                        $result[] = 'email';
                    }
                }
            }
        }


        $this->is_change_info = $result;

        return $result;
    }

    public function getRawChannels()
    {
        return waDomainAuthConfig::factory()->getVerificationChannelInstances();
    }

    public function validateConfirmationCodeAction()
    {
        //После подтверждения поставить статус подтвержден
    }

    /**
     * Проверить телефон или имейл, что они не пренадлежат юзерам бекенда
     * Вернуть Марку флаг, что нельзя пропускать подтверждение
     */
    public function sendConfirmationCodeAction()
    {
        $source = waRequest::post('source', null, waRequest::TYPE_STRING);


        /*
                $channels = waDomainAuthConfig::factory()->getVerificationChannelInstances();

                $is_email = (new waEmailValidator())->isValid($source);
                $is_phone = (new waPhoneNumberValidator())->isValid($source);
                $result = false;
                $address = null;


                foreach ($channels as $channel) {
                    $type = $channel->getType();
                    if (($type === 'email' && $is_email) || ($type === 'sms' && $is_phone)) {
                        $address = $source;
                    }

                    if ($address) {
                        $result = $channel->sendConfirmationCodeMessage($address, array(
                            'use_session' => true    // не хотим заморачиваться с хранением где-то asset_id - просто юзаем сессию
                        ));

                        if ($result) {
                            break;
                        }
                    }
                }*/

        //Установить время отправки
    }

}