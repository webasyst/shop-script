<?php
class shopCourierAuthAdapter extends waAuthAdapter
{
    public function auth()
    {
        $auth_code = waRequest::post('code', null, 'string');
        $auth_code = str_replace('-', '', $auth_code);
        $auth_code = join('-', array(
            substr($auth_code, 0, -8),
            substr($auth_code, -8, 4),
            substr($auth_code, -4, 4),
        ));
        list($courier_id, $api_pin) = explode('-', $auth_code, 2) + array(1 => '');
        if (!$courier_id || !$api_pin) {
            $this->showResponse(array(
                'error' => 'invalid_request',
                'error_description' => 'invalid code',
            ));
        }

        $courier_id = (int) $courier_id;
        $courier_model = new shopApiCourierModel();
        $courier = $courier_model->getById($courier_id);
        if (!$courier || !$courier['enabled'] || $courier['api_pin'] != $api_pin) {
            $this->showResponse(array(
                'error' => 'invalid_request',
                'error_description' => 'invalid code',
            ));
        }

        if ($courier['api_pin_expire'] && strtotime($courier['api_pin_expire']) < time()) {
            $this->showResponse(array(
                'error' => 'invalid_request',
                'error_description' => 'invalid code',
            ));
        }

        $this->showResponse(array(
            'token' => $courier['api_token'],
        ));
    }

    protected function showResponse($response)
    {
        echo json_encode($response);
        exit;
    }
}