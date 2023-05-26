<?php
/**
 * @since 10.0.0
 */
class shopCustomerAddMethod extends shopApiMethod
{
    protected $method = 'POST';
    protected $courier_allowed = false;

    public function execute()
    {
        $data = $this->post('data');
        $skip_validation = $this->post('skip_validation');

        if (!is_array($data)) {
            $data = [];
        }

        $data = [
            'create_app_id' => 'shop',
            'create_method' => 'api_stub',
            'create_contact_id' => wa()->getUser()->getId(),
            'is_user' => 0,
        ] + array_filter($data);
        unset($data['id']);

        $c = new waContact();
        $validation_errors = $c->save($data, empty($skip_validation));
        if ($validation_errors) {
            $this->response = [
                'contact_id' => null,
                'errors' => $validation_errors,
            ];
            return;
        }

        $this->response = [
            'contact_id' => $c->getId(),
        ];
    }
}
