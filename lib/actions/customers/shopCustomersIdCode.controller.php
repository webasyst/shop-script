<?php

class shopCustomersIdCodeController extends waJsonController {
    public function execute() {
        if (empty(wa()->getUser()->getRights('shop', 'customers'))) {
            $this->errors = _w('Insufficient access rights');
            return;
        }

        $contact_id = waRequest::post('customer_id', 0, 'int');
        if (!$contact_id) {
            $this->errors = _w('Customer not found.');
            return;
        }

        try {
            $contact = new shopCustomer($contact_id);
            $contact->getName();
        } catch (waException $e) {
            $this->errors = _w('Customer not found.');
            return;
        }

        $customer_model = new shopCustomerModel();
        $id_code = $customer_model->normalizeIdCode(waRequest::post('id_code', ''));

        if (!$customer_model->isValidIdCode($id_code)) {
            $this->errors = _w('The loyalty card code must contain 12 digits and must not start with 0.');
            return;
        }

        if (!$customer_model->getById($contact_id)) {
            $customer_model->createFromContact($contact_id);
        }

        if ($id_code) {
            $saved_id_code = $customer_model->getAvailableIdCode($contact_id, $id_code);
            if (!$saved_id_code) {
                $this->errors = _w('Unable to generate a unique loyalty card code.');
                return;
            }
        } else {
            $saved_id_code = null;
        }

        $customer_model->updateById($contact_id, [
            'id_code' => $saved_id_code,
        ]);

        $this->response = [
            'id_code' => $saved_id_code,
            'message' => $saved_id_code === null || $saved_id_code === $id_code
                ? _w('Loyalty card code has been saved.')
                : _w('The entered code was already taken. Another available code has been saved.'),
        ];
    }
}
