<?php
class shopPushClientModel extends waModel
{
    protected $table = 'shop_push_client';
    protected $id = 'client_id';

    public function getAllMobileClients($result_with_couriers = false)
    {
        $all_clients = $this->getByField(array('type' => array('', 'mobile')), true);
        if (!$all_clients) {
            return array();
        }

        // Fetch all API tokens and couriers mentioned.
        $couriers = array();
        $api_tokens = array();
        foreach($all_clients as $c) {
            $api_tokens[$c['api_token']] = $c['api_token'];
        }
        if ($api_tokens) {
            // Fetch API tokens
            $api_token_model = new waApiTokensModel();
            $api_tokens = $api_token_model->getById($api_tokens);

            // Fetch couriers
            if ($api_tokens) {
                $api_courier_model = new shopApiCourierModel();
                $rows = $api_courier_model->getByField('api_token', array_keys($api_tokens), true);
                foreach($rows as $c) {
                    $couriers[$c['api_token']] = $c;
                }
            }
        }

        // Fetch all users mentioned with their access rights.
        $contact_exists = array();
        $can_access_app = array();
        foreach($all_clients as $c) {
            $contact_exists[$c['contact_id']] = false;
        }
        if ($contact_exists) {
            $contact_model = new waContactModel();
            $rows = $contact_model->select('id,is_user')->where('id IN (?)', array(array_keys($contact_exists)))->fetchAll('id', true);
            foreach($rows as $contact_id => $is_user) {
                $contact_exists[$contact_id] = true;
                if ($is_user > 0) {
                    $can_access_app[$contact_id] = true;
                }
            }
            if ($can_access_app) {
                $contact_rights_model = new waContactRightsModel();
                $users_with_access = array_flip($contact_rights_model->getUsers('shop'));
                $can_access_app = array_intersect_key($can_access_app, $users_with_access);
            }
        }

        $active_clients = array();
        $inactive_clients = array();
        foreach($all_clients as $c) {
            $token = $c['api_token'];
            if ($token) {
                // Make sire API token stil exists and not expired
                if (!isset($api_tokens[$token])) {
                    $inactive_clients[] = $c['client_id'];
                    continue;
                } else if ($api_tokens[$token]['expires']) {
                    if (strtotime($api_tokens[$token]['expires']) < time()) {
                        $inactive_clients[] = $c['client_id'];
                        continue;
                    }
                }

                // If courier's token, check if active
                if (isset($couriers[$token])) {
                    $courier = $couriers[$token];
                    if ($result_with_couriers && $courier['enabled']) {
                        $active_clients[] = $c;
                    } else {
                        // Do not add to inactive because they could re-enable the courier
                    }
                    continue;
                }
            }

            // Check user's access rights
            if (empty($can_access_app[$c['contact_id']])) {
                if (empty($contact_exists[$c['contact_id']])) {
                    $inactive_clients[] = $c['client_id'];
                }
                continue;
            }

            // All fine
            $active_clients[] = $c;
        }

        // Forget inactive clients
        if ($inactive_clients) {
            $this->deleteByField('client_id', $inactive_clients);
        }

        return array_values($active_clients);
    }
}
