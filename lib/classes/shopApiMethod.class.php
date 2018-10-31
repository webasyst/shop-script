<?php

abstract class shopApiMethod extends waAPIMethod
{
    protected $courier_allowed = false;
    protected $courier = null;
    protected $errors;

    public function __construct()
    {
        $courier_model = new shopApiCourierModel();
        $this->courier = $courier_model->getByToken(waRequest::request('access_token', '', 'string'));
        if ($this->courier) {
            waRequest::setParam('api_courier', $this->courier);
            if (!$this->courier['api_last_use'] || time() - strtotime($this->courier['api_last_use']) > 300) {
                $this->courier['api_last_use'] = date('Y-m-d H:i:s');
                $courier_model->updateById($this->courier['id'], array(
                    'api_last_use' => $this->courier['api_last_use'],
                ));
            }
        }
        return parent::__construct();
    }

    public function getResponse($internal = false)
    {
        // Check courier access rights
        if (!$internal && $this->courier) {
            if (!$this->courier_allowed) {
                throw new waAPIException('access_denied', 'Access denied to limited courier token.', 403);
            } elseif (!$this->courier['enabled']) {
                throw new waAPIException('invalid_token', 'Access token has expired', 401);
            }
        }
        return parent::getResponse($internal);
    }

    /**
     * An additional option for creating an answer
     * @param array $data
     * @return array
     */
    public function createResponse($data = array())
    {
        $response = array('status' => 'ok');
        if ($this->errors) {
            $response['status'] = 'fail';
            $response['errors'] = $this->errors;
        } else {
            $response['data'] = $data;
        }
        return $response;
    }
}