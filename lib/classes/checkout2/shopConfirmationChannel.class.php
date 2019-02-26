<?php

class shopConfirmationChannel
{
    const ATTEMPTS_TO_VERIFY_CODE = 3;

    protected $options = [];

    protected $is_dirty = null;

    public function __construct($options = [])
    {
        $this->options = $options;

        $this->updateStorageStatus();
    }

    public function __get($name)
    {

    }

    /**
     * Не работает. Если хотите выстрелить себе в ногу, убедитесь, что сбросили кэш из памяти
     * @param $name
     * @param $value
     */
    public function __set($name, $value)
    {

    }

    public function searchContact()
    {
        // Сравнить чтобы в сессии жил актуальный контакт

        // Найти контакты по телефону

        // Найти контакты по имейлу

        // найти самый старый подходящий по типу

        // сохранить в объект все результаты поиска

        // вернуть все найденные контакты
    }

    public function getContact()
    {
        // получить все контакты

        // найти нужный контакт

        // записать его в память и вернуть

        // вернуть его
    }

    public function issetAdmin()
    {
        //Проверка, можно ли авторизовать как админа
    }

    public function isBannedContact()
    {
        //Получить контакт под запрос, проверить что он не забанен, вернуть булево
    }

    public function isAdminError()
    {
        // получить список контактов, если среди них есть админ, то ошибка

        // Если включен шаг 1 и мы нашли 1 админа, то тоже ошибка

        // Если авторизованный пользователь, решил указать данные админа, то тоже будет ошибка
    }

    public function isForbiddenAddress()
    {
        // Если пользователь вводит свои данные, то все ок.

        // Если пользователь или гость вводит чужие данные, то все не ок.
    }

    public function getConfirmationChannel()
    {
        // Получить все каналы, которые нужно подтверидть

        // Получить все включенные поля в чекауте

        // Сравнить с уже заполненными данными.

        // Сбросить если изменились данные

        // Вернуть название канала, который нужно подтверджать
    }


    public static function parseData($data)
    {
        $result = [
            'is_company' => (int)$data['contact']['is_company']
        ];

        if (isset($data['result']['auth']['fields']['email'])) {
            $result['email'] = $data['result']['auth']['fields']['email']['value'];
        }

        if (isset($data['result']['auth']['fields']['phone'])) {
            $result['sms'] = waContactPhoneField::cleanPhoneNumber($data['result']['auth']['fields']['phone']['value']);
        }

        return $result;
    }


    ########
    # INIT #
    ########


    protected function updateStorageStatus()
    {
        //Получить каналы из сессии
        $old_channels = $this->getChannels();

        $raw_channels = $this->getRawChannels();
        $new_channels = [];

        foreach ($raw_channels as $raw_channel) {
            $type = $raw_channel->getType();

            if (isset($old_channels[$type])) {
                $new_channels[$type] = $old_channels[$type];
            } else {
                $new_channels[$type] = [
                    'status' => null,
                    'source' => null,
                ];
            }
        }

        $new_channels = $this->updateTransportStatus($new_channels);

        $this->setStorage($new_channels, 'channels');
    }

    ############
    # VALIDATE #
    ############

    /**
     * Что должно делать?
     * Проверить, что телефон и имейл из запроса, соответстуюет тем что в сессии
     * Проверить источник подтвержден
     * поставить флаг, что источник нужно сохранить
     *
     * @param $verifiable_channels
     * @return bool
     */
    public function isConfirmChannels($verifiable_channels)
    {
        $channels = $this->getChannels();
        $is_verified_flag = true;

        foreach ($channels as $channel_type => &$channel_data) {
            $requested_source = ifset($verifiable_channels, $channel_type, null);
            $channel_source = $channel_data['source'];
            $channel_status = $channel_data['status'];

            //Do not make an extra request in the database if everything converges and is confirmed.
            //If the values ​​do not converge, then reset the status.
            if ($requested_source === $channel_source && $channel_status === true) {
                continue;
            } elseif ($requested_source !== $channel_source) {
                $channel_data['status'] = null;
            }

            if (wa()->getUser()->isAuth()) {
                $saved_channel_data = $this->getSavedChannelStatus($requested_source, $channel_type);

                if ($saved_channel_data === waContactEmailsModel::STATUS_CONFIRMED) {
                    // Always check for a constant from the email. Because they have the same essence, and the code is simpler.
                    // For those who want to do something with  waContactDataModel::STATUS_CONFIRMED!!!
                    $channel_data['status'] = true;
                }
            }

            if ($channel_data['status'] !== true) {
                $is_verified_flag = false;
            }

            //Set the session to the actual source
            $channel_data['source'] = $requested_source;
        }

        $this->setStorage($channels, 'channels');

        return $is_verified_flag;
    }

    public function isValidateSource($source)
    {
        $result = true;
        $transport = $this->getTransport();

        if ($transport === waVerificationChannelModel::TYPE_SMS) {
            $result = (new waPhoneNumberValidator())->isValid($source);
        } elseif ($transport === waVerificationChannelModel::TYPE_EMAIL) {
            $result = (new waEmailValidator())->isValid($source);
        }

        return $result;
    }

    public function isValidateCode($code)
    {
        $transport = $this->getTransport();
        $verification = $this->getStorage('verification');

        if (!$verification || empty($verification['source'])) {
            return false;
        }

        $channel = waDomainAuthConfig::factory()->getVerificationChannelInstance($transport);
        $result = $channel->validateConfirmationCode($code, array(
            'recipient' => $verification['source']
        ));

        return $result['status'];
    }

    public function isAllChannelConfirmed()
    {
        $channels = $this->getChannels();
        $result = true;

        foreach ($channels as $channel_type => $channel_data) {
            if ($channel_data['status'] !== true || empty($channel_data['source'])) {
                $result = false;
                break;
            }
        }

        return $result;
    }

    ########
    # SEND #
    ########
    public function sendCode($source)
    {
        $channel = waDomainAuthConfig::factory()->getVerificationChannelInstance($this->getTransport());

        $result = $channel->sendConfirmationCodeMessage($source, [
            'use_session' => true
        ]);

        return $result;
    }

    public function sendRegisterMail($contact_id)
    {
        $contact = new waContact($contact_id);
        $result = $password = null;

        if (waDomainAuthConfig::factory()->getAuthType() !== waAuthConfig::AUTH_TYPE_ONETIME_PASSWORD) {
            $password = waContact::generatePassword();
            $contact->setPassword($password);
            $contact->save();
        }

        $channels = waDomainAuthConfig::factory()->getVerificationChannelInstances();
        foreach ($channels as $channel) {
            $result = $channel->sendSignUpSuccessNotification($contact, ['password' => $password]);
            if ($result) {
                break;
            }
        }

        return $result;
    }

    public function getSendTimeout()
    {
        $send_time = $this->getStorage('send_time');
        $timeout_left = 0;

        if ($send_time) {
            $checkout_config = new shopCheckoutConfig(true);

            $timeout_left = $send_time + $checkout_config['confirmation']['recode_timeout'] - time();
        }

        return $timeout_left;
    }

    ##########
    # VERIFY #
    ##########

    public function verifyTransportStatus()
    {
        $channels = $this->getStorage('channels');
        $verification = $this->getStorage('verification');
        $transport = $this->getTransport();

        if (empty($channels[$transport])) {
            return false;
        } else {
            $channels[$transport]['status'] = true;
            $channels[$transport]['source'] = $verification['source'];
            $this->updateUserAddress($channels[$transport]['source'], $transport);

            $this->delStorage();

            $this->setStorage($channels, 'channels');
        }

        return true;
    }


    protected function updateTransportStatus($storage)
    {
        $transport = $this->getTransport();
        $channel = waDomainAuthConfig::factory()->getVerificationChannelInstance($transport);

        //Дропаем значения для канала. не дропаем все, чтобы не просить подтверждать еще раз
        if ($channel instanceof waVerificationChannelNull) {
            unset($storage['transport']);
            unset($storage['verification']);
            unset($storage['channels'][$transport]);
        }

        return $storage;
    }

    protected function updateUserAddress($new_source, $transport)
    {
        $contact = wa()->getUser();
        if (!$contact->isAuth()) {
            return false;
        }

        // Because phone !== sms =(
        if ($transport === waVerificationChannelModel::TYPE_SMS) {
            $get_field = 'phone';
        } elseif ($transport === waVerificationChannelModel::TYPE_EMAIL) {
            $get_field = 'email';
        } else {
            return false;
        }

        $is_update = false;
        $saved = $contact->get($get_field);
        $new_source = $this->cleanSource($new_source, $transport);

        //find saved number
        foreach ($saved as $id => $source) {
            if ($source['value'] === $new_source) {
                $saved[$id]['status'] = waContactEmailsModel::STATUS_CONFIRMED;
                $is_update = true;
                break;
            }
        }

        //set new number
        if (!$is_update) {
            $saved[] = [
                'value'  => $new_source,
                'status' => waContactEmailsModel::STATUS_CONFIRMED,
            ];
        }

        $contact->set($get_field, $saved);
        $contact->save();

        return true;
    }

    #############
    # AUTHORIZE #
    #############

    public function authorize($contact_id)
    {
        $checkout_config = new shopCheckoutConfig(true);

        if (wa()->getUser()->isAuth() ||
            //You can not authorize the old user who did not confirm all channels
            ($checkout_config['confirmation']['order_without_auth'] === 'existing_contact' && !$this->isAllChannelConfirmed())) {
            return false;
        }

        wa()->getAuth()->auth(array('id' => $contact_id));

        $channels = $this->getChannels();

        foreach ($channels as $channel_type => $channel_data) {
            if ($channel_data['status'] === true) {
                $this->updateUserAddress($channel_data['source'], $channel_type);
            }
        }

        return true;
    }

    ###########
    # STORAGE #
    ###########

    /**
     * Writes all or specific data to a session.
     * @param $data
     * @param null $key
     */
    public function setStorage($data, $key = null)
    {
        if ($key) {
            $storage = (array)$this->getStorage();
            $storage[$key] = $data;
            $data = $storage;
        }

        wa()->getStorage()->set($this->getStorageKey(), $data);
    }

    /**
     * Removes all or specific data from the session.
     * @param null $key
     */
    public function delStorage($key = null)
    {
        if ($key) {
            $storage = $this->getStorage();
            unset($storage[$key]);
            wa()->getStorage()->set($this->getStorageKey(), $storage);
        } else {
            wa()->getStorage()->del($this->getStorageKey());
        }
    }

    /**
     * Get all or specific data from a session.
     * @param null $key
     * @return mixed|null
     */
    public function getStorage($key = null)
    {
        $storage = wa()->getStorage()->get($this->getStorageKey());
        if ($key) {

            if ($key === 'transport') {
                $storage = $this->getTransport();
            } else {
                $storage = ifset($storage, $key, null);
            }
        }

        return $storage;
    }

    /**
     * @return string
     */
    protected function getStorageKey()
    {
        return 'checkout_confirmation_data';
    }

    ##########
    # HELPER #
    ##########

    /**
     * Return the current active transport
     * @return mixed
     */
    public function getTransport()
    {

        //todo проверить, чтобы транспорт был актуальный
        return $this->getStorage('transport');
    }

    /**
     * Return channels with statuses from the session
     * @return mixed
     */
    public function getChannels()
    {
        return $this->getStorage('channels');
    }

    /**
     * Prepare source for clean condition
     * @param string $source
     * @param string $transport
     * @return string
     */
    public function cleanSource($source, $transport)
    {
        if ($transport === waVerificationChannelModel::TYPE_SMS) {
            $source = waContactPhoneField::cleanPhoneNumber($source);
        }

        return $source;
    }

    /**
     * Make sure the transport still needs to be confirmed.
     * @return array
     */
    public function getTransportError()
    {
        $transport = $this->getTransport();
        $errors = [];

        if (!$transport) {
            $errors = [
                'id'   => 'transport_error',
                'text' => _w('TODO Больше не нужно подтверждать этот канал')
            ];
        }

        return $errors;
    }

    /**
     * @return waVerificationChannel[]
     */
    protected function getRawChannels()
    {
        $channels = [];
        if (waDomainAuthConfig::factory()->getAuth()) {
            $channels = waDomainAuthConfig::factory()->getVerificationChannelInstances();
        }
        return $channels;
    }

    /**
     * @param string $requested_source
     * @param string $channel_type
     * @return string|null
     * @throws waException
     */
    protected function getSavedChannelStatus($requested_source, $channel_type)
    {
        if ($channel_type === waVerificationChannelModel::TYPE_SMS) {
            $saved_data = (new waContactDataModel())->getByField([
                'contact_id' => wa()->getUser()->getId(),
                'value'      => $requested_source
            ]);
        } elseif ($channel_type === waVerificationChannelModel::TYPE_EMAIL) {
            $saved_data = (new waContactEmailsModel())->getByField([
                'contact_id' => wa()->getUser()->getId(),
                'email'      => $requested_source
            ]);
        }

        $channel_status = ifset($saved_data, 'status', null);

        return $channel_status;
    }
}