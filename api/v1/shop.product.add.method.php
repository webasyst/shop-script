<?php

class shopProductAddMethod extends shopProductUpdateMethod
{
    protected $method = 'POST';
    
    public function execute()
    {
        $data = waRequest::post();
        $exclude = array('id');
        foreach ($exclude as $k) {
            if (isset($data[$k])) {
                unset($data[$k]);
            }
        }

        $this->post("name", true);
        if (ifset($data['sku_type']) != 1) {
            $this->post("skus", true);
        }
        $this->checkSku($data);
        // check access rights
        $this->checkRights($this->post("type_id", true));

        $p = new shopProduct();
        if ($p->save($data, true, $errors)) {
            $_GET['id'] = $p->getId();
            $method = new shopProductGetInfoMethod();
            $this->response = $method->getResponse(true);
        } else {
            throw new waAPIException('server_error', implode(",\n", $errors), 500);
        }
    }



}