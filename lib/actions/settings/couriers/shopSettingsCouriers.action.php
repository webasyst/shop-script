<?php
/**
 * API couriers editor accessable via Orders sidebar.
 */
class shopSettingsCouriersAction extends waViewAction
{
    public function execute()
    {
        $courier_model = new shopApiCourierModel();
        $courier_storefronts_model = new shopApiCourierStorefrontsModel();

        $id = waRequest::request('id', '', 'string');
        if ($id != 'new') {
            $id = intval($id);
            if (!$id) {
                $id = '';
            }
        }
        $couriers = $courier_model->getAll('id');
        if ($id != 'new' && (!$id || empty($couriers[$id]))) {
            $id = key($couriers);
        }
        if (empty($couriers[$id])) {
            $courier = $courier_model->getEmptyRow();
            $courier['id'] = 'new';
        } else {
            $courier = $couriers[$id];
        }
        unset($id);

        // Submit via post
        $saved = false;
        $errors = array();
        if (waRequest::post()) {
            $regenerate_pin = waRequest::post('regenerate_pin') || $courier['id'] == 'new';
            $data = waRequest::post('courier');
            $data = array_intersect_key($data, $courier) + array(
                'all_storefronts' => 1,
                'enabled' => 0,
                'rights_order_edit' => 0,
                'rights_customer_edit' => 0,
            ) + $courier;
            unset(
                $data['id'],
                $data['orders_processed'],
                $data['create_datetime'],
                $data['api_token'],
                $data['api_pin'],
                $data['api_pin_expire'],
                $data['api_last_use']
            );
            $data['enabled'] = $data['enabled'] ? 1 : 0;
            $data['rights_order_edit'] = $data['rights_order_edit'] ? 1 : 0;
            $data['rights_customer_edit'] = $data['rights_customer_edit'] ? 1 : 0;
            if ($data['enabled'] == 0){
                $data['rights_order_edit'] = 0;
                $data['rights_customer_edit'] = 0;
            }
            $data['all_storefronts'] = $data['all_storefronts'] ? 1 : 0;
            $data['contact_id'] = intval($data['contact_id']);
            if (!$data['contact_id']) {
                $data['contact_id'] = null;
            }
            $data['note'] = @strval($data['note']);
            $data['name'] = @strval($data['name']);
            if (empty($data['name'])) {
                $errors['courier[name]'] = _ws('This field is required');
            }
            $storefronts_data = array();
            if (!$data['all_storefronts']) {
                $storefronts_data = waRequest::post('storefronts', null, 'array');
                if (!$storefronts_data) {
                    $errors['storefronts'] = _w('Select at least one storefront.');
                }
            }
            if (empty($errors)) {
                $saved = true;

                // Save shop_api_courier
                if ($courier['id'] == 'new') {
                    $data['create_datetime'] = date('Y-m-d H:i:s');
                    $courier['id'] = $courier_model->insert($data);
                } else {
                    $courier_model->updateById($courier['id'], $data);
                }

                // Regenerate auth code and API token
                if ($regenerate_pin) {

                    // Regenerate auth code
                    $data = array();
                    $data['api_pin'] = $courier_model->generateAuthCode();
                    $data['api_pin_expire'] = date('Y-m-d H:i:s', time() + 3600*24);

                    // Delete old api token
                    $token_model = new waApiTokensModel();
                    if ($courier['api_token']) {
                        $token_model->deleteByField('token', $courier['api_token']);
                    }

                    // Create new api token
                    $courier['api_token'] = $data['api_token'] = $token_model->getToken('shop_courier_'.$courier['id'], wa()->getUser()->getId(), 'shop');
                    $courier_model->updateById($courier['id'], $data);
                }

                // Reload all couriers to ensure correct ordering and contact photo data
                $couriers = $courier_model->getAll('id');
                $courier = ifset($couriers[$courier['id']]);
                if (!$courier) {
                    // Somebody else just deleted it?..
                    if ($courier['api_token']) {
                        // Paranoid mode: make sure not to leave a token behind
                        $token_model = new waApiTokensModel();
                        $token_model->deleteByField('token', $courier['api_token']);
                    }
                    throw new waException('Not found', 404);
                }

                // Save storefronts
                $courier_storefronts_model->deleteByField('courier_id', $courier['id']);
                if (!$courier['all_storefronts']) {
                    $values = array();
                    foreach($storefronts_data as $url) {
                        $values[] = array(
                            'courier_id' => $courier['id'],
                            'storefront' => $url,
                        );
                    }
                    if ($values) {
                        $courier_storefronts_model->multipleInsert($values);
                    }
                }
            } else {
                $courier = $data + $courier;
                $courier['storefronts'] = array_fill_keys(shopHelper::getStorefronts(), false);
                if (is_array($storefronts_data)) {
                    foreach($storefronts_data as $url) {
                        $courier['storefronts'][$url] = true;
                    }
                }
            }
        }

        // Storefronts selected for current courier
        if (!$errors) {
            if ($courier['id'] != 'new') {
                $courier['storefronts'] = $courier_storefronts_model->getByCourier($courier['id']);
            } else {
                $courier['storefronts'] = array_fill_keys(shopHelper::getStorefronts(), false);
            }
        }

        // Name of courier contact
        $courier_contact_photo_url = $courier_contact_name = '';
        if ($courier['contact_id']) {
            try {
                $c = new waContact($courier['contact_id']);
                $courier_contact_name = $c->getName();
                $courier_contact_photo_url = $c->getPhoto(96, 96);
            } catch (waException $e) {
                $courier['contact_id'] = null;
                if ($courier['id'] != 'new') {
                    $courier_model->updateById($courier['id'], array(
                        'contact_id' => null,
                    ));
                }
            }
        }

        $this->view->assign(array(
            'saved' => $saved,
            'courier' => $courier,
            'couriers' => $couriers,
            'courier_contact_photo_url' => $courier_contact_photo_url,
            'courier_contact_name' => $courier_contact_name,
            'errors' => ifempty($errors),
        ));
    }
}
