<?php

class shopConfirmationChannel
{
    const ATTEMPTS_TO_VERIFY_CODE = 3;

    // The maximum number of contacts that we get from the collection of one type
    const MAX_CONTACT_PER_REQUEST = 5000;

    protected static $options = [];

    protected static $contacts = null;

    protected $contact = [];

    protected $count = [];

    protected $channels = [];


    /**
     * $options array - parameter list
     *  $address array:
     *      $email string checkout phone or phone to confirm
     *      $phone string email from checkout form or email that needs to be confirmed
     *  $is_company int from whom the order is executed
     *
     *
     * shopConfirmationChannel constructor.
     * @param array $options
     */
    public function __construct($options = [])
    {
        $new_options = $this->parseOptions($options);

        if ($new_options != self::$options) {
            self::$options = $new_options;
            self::$contacts = null;
        }
    }

    public function parseOptions($options)
    {
        $fake_contact = new waContact();

        if (isset($options['address'])) {
            foreach ($options['address'] as $address_type => $address_value) {
                if ($address_value) {
                    $fake_contact->set($address_type, $address_value);
                    $clear_value = $fake_contact->get($address_type);
                    $options['address'][$address_type] = $clear_value[0]['value'];
                    $options['raw_address'][$address_type] = $address_value;
                } else {
                    unset($options['address'][$address_type]);
                }
            }
        }

        $options['is_company'] = ifset($options, 'is_company', null);

        return $options;
    }

    /**
     *
     * @param null $type
     * @param bool $all
     * @return null
     * @throws waException
     */
    protected function getContacts($type = null, $all = false)
    {
        // Do not search for contacts again
        if (is_null(self::$contacts)) {
            $this->searchContacts();
        }
        $result = [];

        $contacts = self::$contacts;
        foreach ($contacts as $c_id => $contact) {
            $is_company = self::$options['is_company'];

            if (!$all && !is_null($is_company) && $contact['is_company'] != $is_company) {
                continue;
            }

            if ($type == 'admins' && $contact['is_user'] > 0) {
                $result[$c_id] = $contact;
                continue;
            } elseif (is_null($type)) {
                $result[$c_id] = $contact;
            }
        }

        return $result;
    }

    /**
     * @throws waException
     */
    protected function searchContacts()
    {
        self::$contacts = [];
        $channels = $this->getChannels();

        foreach ($channels as $channel => $source) {
            $field_id = $channel;

            if ($channel === 'phone') {
                $collection = $this->searchContactByPhone($source);
            } else {
                $collection = new waContactsCollection('search/'.$field_id.'='.str_replace('&', '\\&', $source));
            }

            // First of all, we are looking for those who have a password. This reduces the chance to clear the password from the admin.
            $collection->orderBy('password', 'desc');

            //Because email is returned in a different format
            if ($channel === waVerificationChannelModel::TYPE_EMAIL) {
                $field_id = 'email.*';
            }
            $result = $collection->getContacts('id,is_user,password,login,is_company'.",$field_id", 0, self::MAX_CONTACT_PER_REQUEST);

            $this->setContacts($result);
        }
    }

    /**
     * Prepare collection for phone search
     *
     * @param $phone
     * @return waContactsCollection
     */
    protected function searchContactByPhone($phone)
    {
        $phones = [$phone];
        $raw_phone = self::$options['raw_address']['phone'];

        $reverse = substr($raw_phone, 0, 1) === '+'; // Convert to the opposite format
        $result = waDomainAuthConfig::factory()->transformPhone($phone, $reverse);
        $phones[] = $result['phone'];

        // Leave only unique phones.
        $phones = implode(',', array_unique($phones));
        // Just to add an escape just in case. 👻👻👻╰(◉ᾥ◉)╯
        $phones = (new waModel())->escape($phones);

        $collection = new waContactsCollection();
        $collection->addJoin('wa_contact_data', ':table.contact_id = c.id', ":table.field = 'phone' AND :table.value IN ({$phones})");

        return $collection;
    }


    /**
     * @param $contacts
     */
    protected function setContacts($contacts)
    {
        foreach ($contacts as $c_id => $contact) {
            if (isset(self::$contacts[$c_id])) {
                self::$contacts[$c_id] = array_merge(self::$contacts[$c_id], $contact);
            } else {
                self::$contacts[$c_id] = $contact;
            }
        }
    }

    /**
     * Returns channels that need to be confirmed.
     * @return array
     */
    public function getChannels()
    {
        if ($this->channels) {
            return $this->channels;
        }

        $raw_channels = $this->getRawChannels();
        $channels = [];

        foreach ($raw_channels as $raw_channel) {
            $type = $raw_channel->getType();

            if ($type === waVerificationChannelModel::TYPE_SMS) {
                $type = 'phone';
            }

            $address = ifset(self::$options, 'address', $type, false);

            // return only those channels for which the user has filled in data.
            if ($address) {
                // The phone must be a number
                if ($type === 'phone' && !is_numeric($address)) {
                    continue;
                }
                $channels[$type] = self::$options['address'][$type];
            }
        }

        $this->channels = $channels;
        return $this->channels;
    }

    /**
     * @param $contact
     * @return string|null
     */
    protected function getContactType($contact)
    {
        $type = null;

        // Find banned users
        // Banned admins are entered into the ban and we clean them with a password. God help them.
        if ($contact['is_user'] < 0) {
            $type = 'banned';
        }

        // Find admins
        if ($contact['is_user'] > 0) {
            $type = 'admins';
        }

        if (is_null($type)) {
            // looking for users and buyers
            if (!empty($contact['password'])) {
                $type = 'users';
            } else {
                $type = 'buyers';
            }
        }

        return $type;
    }

    /**
     * Get the number of users by type and channel
     * @param string $type
     * @param string $channel
     * @param bool $all
     * @return int
     * @throws waException
     */
    protected function getCount($type = '', $channel = '', $all = false)
    {
        //Update counter
        $contacts = $this->getContacts(null, $all);
        $count = 0;

        foreach ($contacts as $contact) {
            if ($type === 'users' && $this->getContactType($contact) == 'buyers') {
                continue;
            } elseif ($type === 'buyers' && $this->getContactType($contact) != 'buyers') {
                continue;
            }

            if (!$channel || ($channel && isset($contact[$channel]))) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Returns the contact to which you want to attach the order.
     * First, he searches among users (those who have a password) and then among customers
     *
     * @return array
     * @throws waException
     */
    public function getContact()
    {
        if ($this->contact) {
            return $this->contact;
        }
        $contacts = $this->getContacts();
        $user = null;
        $buyers = null;

        foreach ($contacts as $c_id => $contact) {
            $options_is_company = self::$options['is_company'];

            if (!is_null($options_is_company) && $contact['is_company'] != $options_is_company) {
                continue;
            }

            $type = $this->getContactType($contact);
            if ($type != 'buyers' && (!$user || $contact['id'] < $user['id'])) {
                $user = $contact;
            } elseif ($type == 'buyers' && (!$buyers || $contact['id'] < $buyers['id'])) {
                $buyers = $contact;
            }
        }

        if ($user) {
            $result = $user;
        } elseif ($buyers) {
            $result = $buyers;
        } else {
            $result = [
                'id'         => null,
                'is_user'    => null,
                'password'   => null,
                'is_company' => null
            ];
        }

        $this->contact = $result;
        return $this->contact;
    }

    /**
     * If the user is banned, we return the fields for which he was found.
     *
     * @return array
     * @throws waException
     */
    public function getBannedErrorFields()
    {
        $contact = $this->getContact();
        $result = [];

        if ($contact['is_user'] < 0) {
            $channels = $this->getChannels();

            foreach ($channels as $channel => $source) {
                if (isset($contact[$channel])) {
                    $result[$channel] = $source;
                }
            }
        }

        return $result;
    }

    /**
     * Returns the fields for which admins found
     *
     * @return array
     * @throws waException
     */
    public function getAdminErrorFields()
    {
        $result = [];
        $admins = $this->getContacts('admins', true);

        // Everything is bad if we try to reset the password to the admin.
        if (wa()->getUser()->getId()) {
            // We are the user, and there is an admin (who is not us)
            unset($admins[wa()->getUser()->getId()]);
            $should_fail = (bool)$admins;
        } else {
            // We are a guest and there are several candidates, one of them is admin
            $should_fail = $admins && $this->getCount() > 1;
        }

        if ($should_fail) {
            $channels = $this->getChannels();
            //Check all admins, looking for what field they were found
            foreach ($admins as $contact) {
                foreach ($channels as $channel => $source) {
                    if (isset($contact[$channel])) {
                        $result[$channel] = $source;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * If an authorized user enters someone else's data, then returns the fields in which he entered them
     *
     * @return array
     * @throws waException
     */
    public function getForbiddenAddress()
    {
        $result = [];

        $user = wa()->getUser();
        $channels = $this->getChannels();

        // The address can be banned only if the personal account is enabled and if the order is made by the user
        if (!$channels || !$user->isAuth()) {
            return $result;
        }

        $user_from_memory = $this->getUserFromMemory();

        foreach ($channels as $channel => $source) {
            // If the data from the POST is not in the cache, then this data does not belong to the user.
            // if other users have this data an error
            if (!isset($user_from_memory[$channel]) && $this->getCount('users', $channel, true)) {
                $result[$channel] = $source;
            }
        }

        return $result;
    }

    /**
     * If the sample was the current contact, then return it.
     *
     * @return array|null
     * @throws waException
     */
    protected function getUserFromMemory()
    {
        $user = null;
        $contacts = $this->getContacts();
        if (isset($contacts[wa()->getUser()->getId()])) {
            $user = $contacts[wa()->getUser()->getId()];
        }

        return $user;
    }

    /**
     * Returns the type of address to confirm.
     *
     * @return string
     * @throws waException
     */
    public function getConfirmChannel()
    {
        $result = '';
        $unconfirmed = $this->getUnconfirmedChannels();
        $current = $this->getStorage('current_confirm');

        // If started to confirm the channel and it still needs to be confirmed - confirm further
        if ($current && isset($unconfirmed[$current['type']]) && $unconfirmed[$current['type']] === $current['source']) {
            $result = $current['type'];
        } elseif ($unconfirmed) {
            // Take the first one, they will already be sorted
            $confirm = [
                'source' => reset($unconfirmed),
                'type'   => key($unconfirmed)
            ];

            $this->setStorage($confirm, 'current_confirm');
            $result = $confirm['type'];
        }

        return $result;
    }

    /**
     * Returns channels to confirm
     * Does not include channels that have already been confirmed and are in session.
     *
     * @return array
     * @throws waException
     */
    public function getUnconfirmedChannels()
    {
        if (wa()->getUser()->isAuth()) {
            $confirm_addresses = $this->getChannelForUser();
        } else {
            $confirm_addresses = $this->getChannelForGuest();
        }

        $confirmed = $this->getStorage('confirmed');

        // check, maybe we have already confirmed this channel
        foreach ($confirmed as $type => $source) {
            if (isset($confirm_addresses[$type])) {
                if ($confirm_addresses[$type] == $source) {
                    unset($confirm_addresses[$type]);
                } else {
                    // If we have a confirmed channel, but its value does not agree with what needs to be confirmed, such a channel needs to be dropped from the session.
                    unset($confirmed[$type]);
                    $this->setStorage($confirmed, 'confirmed');
                }
            }
        }

        $this->setStorage($confirm_addresses, 'unconfirmed');

        return $confirm_addresses;
    }

    /**
     * Compare the source from the session and the one that is in the confirmation form.
     *
     * @param $source
     */
    public function validateSource($source)
    {
        $storage = $this->getStorage();
        $current_source = ifset($storage, 'current_confirm', 'source', null);

        if ($current_source && $current_source !== $source) {
            // Change the current confirmed channel
            $storage['current_confirm']['source'] = $source;
            //Update Unapproved
            $storage['unconfirmed'][$this->getActiveType()] = $source;

            //If such a channel has been confirmed - delete
            unset($storage['confirmed'][$this->getActiveType()]);

            $this->setStorage($storage);
        }
    }

    /**
     * Returns the channels that the active user must confirm
     *
     * @return array
     * @throws waException
     */
    protected function getChannelForUser()
    {
        $config = $this->getCheckoutConfig();
        $forbidden = $this->getForbiddenAddress();

        $result = [];
        $order_without_auth = $config['confirmation']['order_without_auth'];

        if ($order_without_auth === 'existing_contact') {
            // we need to confirm only those data that belong to other users.
            // If the data was saved by the user and other users have it - we do not confirm
            $result = $forbidden;
        } elseif ($order_without_auth === 'confirm_contact') {
            // See method documentation.
            $user_from_memory = $this->getUserFromMemory();
            if ($user_from_memory) {
                //Be sure to confirm not your data.
                $result = $forbidden;

                $channels = $this->getChannels();
                foreach ($channels as $channel => $source) {
                    $status = ifset($user_from_memory, $channel, 0, 'status', null);

                    // If the user does not have this channel, or it is not confirmed, it must be confirmed.
                    if (!$status || $status !== $this->getConfirmedStatus($channel)) {
                        $result[$channel] = $source;
                    }
                }
            } else {
                // No user - confirm all channels
                // Validation in the authorization step ensures that the channel can be confirmed for it.
                $result = $this->getChannels();
            }
        }

        return $result;
    }

    /**
     * Returns the channels to be confirmed to the guest.
     *
     * @return array
     * @throws waException
     */
    protected function getChannelForGuest()
    {
        $config = $this->getCheckoutConfig();

        $channels = [];
        $order_without_auth = $config['confirmation']['order_without_auth'];

        // If step 'existing_contact' need to confirm if found another contact
        // If step 'confirm_contact' is always confirmed
        $contact = $this->getContact();
        if (($order_without_auth === 'existing_contact' && $contact['id']) || $order_without_auth === 'confirm_contact') {
            $channels = $this->getChannels();
        }

        return $channels;
    }

    protected function getCheckoutConfig()
    {
        return new shopCheckoutConfig(true);
    }

    ############
    # VALIDATE #
    ############

    /**
     * Uses standard framework validators
     *
     * @param $source
     * @return bool
     */
    public function isValidateSource($source)
    {
        $result = true;
        $channel_type = $this->getActiveType();

        if ($channel_type === 'phone') {
            $result = (new waPhoneNumberValidator())->isValid($source);
        } elseif ($channel_type === 'email') {
            $result = (new waEmailValidator())->isValid($source);
        }

        return $result;
    }

    /**
     * Checks if the verification code is entered correctly
     *
     * @param $code
     * @return array|bool
     * @throws waException
     */
    public function validateCode($code)
    {
        $verification = $this->getStorage('verification');

        if (!$verification || empty($verification['source'])) {
            return false;
        }

        $channel = waDomainAuthConfig::factory()->getVerificationChannelInstance($this->getTransport());
        $result = $channel->validateConfirmationCode($code, array(
            'recipient'   => $verification['source'],
            'check_tries' => [
                'count' => self::ATTEMPTS_TO_VERIFY_CODE,
                'clean' => true,
            ]
        ));

        return $result;
    }

    /**
     * Checks if all channels are verified.
     *
     * @return bool
     */
    protected function isAllChannelConfirmed()
    {
        $channels = $this->getChannels();
        $confirmed = $this->getStorage('confirmed');

        $result = true;

        foreach ($channels as $channel => $source) {
            if (empty($confirmed[$channel]) || $confirmed[$channel] !== $source) {
                $result = false;
                break;
            }
        }

        return $result;
    }

    ########
    # SEND #
    ########
    /**
     * Send code to the current channel
     *
     * @param $source
     * @return mixed
     */
    public function sendCode($source)
    {
        $channel = waDomainAuthConfig::factory()->getVerificationChannelInstance($this->getTransport());

        $auth_config = waDomainAuthConfig::factory();

        // options for send method
        $options = [
            'site_url' => $auth_config->getSiteUrl(),
            'site_name' => $auth_config->getSiteName(),
            'login_url' => $auth_config->getLoginUrl(array(), true),
            'use_session' => true
        ];

        return $channel->sendConfirmationCodeMessage($source, $options);
    }

    /**
     * Returns the time which need to wait before the next message is sent.
     * @return int
     */
    public function getSendTimeout()
    {
        $send_time = $this->getStorage('send_time');
        $timeout_left = 0;

        if ($send_time) {
            $checkout_config = $this->getCheckoutConfig();

            $timeout_left = $send_time + $checkout_config['confirmation']['recode_timeout'] - time();
        }

        return $timeout_left;
    }

    ##########
    # VERIFY #
    ##########

    /**
     * The current confirmed channel is recorded as confirmed.
     * @return bool
     */
    public function setConfirmed()
    {
        $current = $this->getStorage('current_confirm');

        if (!$current) {
            return false;
        }

        $confirmed = $this->getStorage('confirmed');
        $confirmed[$current['type']] = $current['source'];

        //reset storage
        $this->delStorage();

        //set confirmed channel
        $this->setStorage($confirmed, 'confirmed');

        return true;
    }

    /**
     * Update the status or insert confirmed data to an authorized user.
     * @param $type
     * @param $new_source
     * @return bool
     * @throws waException
     */
    protected function updateUserAddress($type, $new_source)
    {
        $contact = wa()->getUser();
        if (!$contact->isAuth()) {
            return false;
        }
        $is_update = false;
        $saved = $contact->get($type);

        //find saved number
        foreach ($saved as $id => $source) {
            if ($source['value'] === $new_source) {
                $saved[$id]['status'] = $this->getConfirmedStatus($type);
                $is_update = true;
                break;
            }
        }

        //set new number
        if (!$is_update) {
            $saved[] = [
                'value'  => $new_source,
                'status' => $this->getConfirmedStatus($type),
            ];
        }

        $contact->set($type, $saved);
        $contact->removeCache();
        $contact->save();

        return true;
    }

    #############
    # AUTHORIZE #
    #############

    /**
     * Performs authorization, clears passwords, sets data in contact
     *
     * @param int $contact_id
     * @param bool $new_contact
     *
     * @return bool
     * @throws waAuthConfirmEmailException
     * @throws waAuthConfirmPhoneException
     * @throws waAuthException
     * @throws waAuthInvalidCredentialsException
     * @throws waException
     */
    public function postConfirm($contact_id, $new_contact)
    {
        $config = $this->getCheckoutConfig();
        $order_without_auth = $config['confirmation']['order_without_auth'];
        $unconfirmed = $this->getUnconfirmedChannels();

        // can only authorize if all channels have been confirmed or this is a new contact.
        if (!wa()->getUser()->isAuth() && $order_without_auth !== 'create_contact' && (!$unconfirmed || $new_contact)) {
            $this->authorize($contact_id);
        }

        // can reset passwords only if the user has confirmed all channels.
        if (!$unconfirmed && $order_without_auth !== 'create_contact') {
            $this->dropPasswords($contact_id);
        }

        if (wa()->getUser()->isAuth()) {
            // Get all channels that have been confirmed
            $confirmed = $this->getStorage('confirmed');

            // Update/set data.
            foreach ($confirmed as $type => $source) {
                if ($type === 'phone') {
                    $source = $this->transformSourceToInternationalFormat($source);
                }
                $this->updateUserAddress($type, $source);
            }
        }

        // Clear all data from session
        $this->delStorage();

        return true;
    }

    protected function transformSourceToInternationalFormat($phone)
    {
        $result = waDomainAuthConfig::factory()->transformPhone($phone);
        return $result['phone'];
    }

    /**
     * Authorize user by id
     *
     * @param $contact_id
     * @throws waAuthConfirmEmailException
     * @throws waAuthConfirmPhoneException
     * @throws waAuthException
     * @throws waAuthInvalidCredentialsException
     * @throws waException
     */
    protected function authorize($contact_id)
    {
        wa()->getAuth()->auth(array('id' => $contact_id));
    }

    /**
     * Reset your password to all contacts who are on the search terms.
     * Do not reset the password to admins!
     *
     * @param $contact_id
     * @return bool
     * @throws waException
     */
    protected function dropPasswords($contact_id)
    {
        $contact_ids = [];

        // Collect all contact id
        $contacts = $this->getContacts(null, true);
        foreach ($contacts as $c_id => $contact) {
            if ($contact['is_user'] > 0 || $contact['id'] == $contact_id) {
                //You can not reset the password admin.
                //No need to reset the password to the found user
                continue;
            }
            $contact_ids[] = $c_id;
        }

        if ($contact_ids) {
            $wa_contact_model = new waContactModel();
            $wa_contact_model->updateByField('id', $contact_ids, ['password' => null]);
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
                $storage = $this->getActiveType();
            } else {
                $storage = ifset($storage, $key, []);
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
     * Return the current active channel type
     * @return mixed
     */
    public function getActiveType()
    {
        $current = $this->getStorage('current_confirm');
        return ifset($current, 'type', '');
    }

    /**
     * Converts an address type from a checkout form (phone, email) to a verification channel type
     * @return string
     */
    public function getTransport()
    {
        $type = $this->getActiveType();
        $result = '';

        if ($type === 'phone') {
            $result = waVerificationChannelModel::TYPE_SMS;
        } elseif ($type === 'email') {
            $result = waVerificationChannelModel::TYPE_EMAIL;
        }

        return $result;
    }

    /**
     * Prepare source for clean condition
     * @param string $source
     * @param string $type
     * @return string
     */
    public function cleanSource($source, $type)
    {
        if ($type === 'phone' || $type === 'sms') {
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
        $channel = waDomainAuthConfig::factory()->getVerificationChannelInstance($transport);

        $errors = [];

        if ($channel instanceof waVerificationChannelNull) {
            $errors = [
                'id'   => 'channel_error',
                'text' => _w('You do not need to confirm this value any more.')
            ];
        }

        return $errors;
    }

    /**
     * Returns all included channels for personal account
     * @return waVerificationChannel[]
     */
    protected function getRawChannels()
    {
        $channels = [];

        // If the personal account is turned off, then we are not looking for contacts
        if (waDomainAuthConfig::factory()->getAuth()) {
            $channels = waDomainAuthConfig::factory()->getVerificationChannelInstances();
        }
        return $channels;
    }

    /**
     * Returns the status "confirmed" for different types of channels
     * @param $type
     * @return string
     */
    protected function getConfirmedStatus($type)
    {
        $result = '';
        if ($type === 'phone' || $type === 'sms') {
            $result = waContactDataModel::STATUS_CONFIRMED;
        } elseif ($type === 'email') {
            $result = waContactEmailsModel::STATUS_CONFIRMED;
        }

        return $result;
    }

    public static function clear()
    {
        self::$options = [];
        self::$contacts = null;
    }
}
