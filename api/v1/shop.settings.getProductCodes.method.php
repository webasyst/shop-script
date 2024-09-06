<?php

class shopSettingsGetProductCodesMethod extends shopApiMethod
{
    public function execute()
    {
        $this->response = [
            'codes' => $this->getCodes(),
        ];
    }

    public function getCodes()
    {
        $product_code_model = new shopProductCodeModel();
        $codes = $product_code_model->getAll('id');

        $type_codes_model = new shopTypeCodesModel();
        foreach($type_codes_model->getAll() as $row) {
            $codes[$row['code_id']]['type_ids'][$row['type_id']] = (int)$row['type_id'];
        }

        foreach($codes as &$c) {
            $c['type_ids'] = ifset($c, 'type_ids', []);
            if (isset($c['type_ids'][0])) {
                if (!isset($all_type_ids)) {
                    $all_type_ids = array_keys((new shopTypeModel())->select('id')->query()->fetchAll('id'));
                }
                $c['type_ids'] = $all_type_ids;
            } else {
                $c['type_ids'] = array_values($c['type_ids']);
            }
        }

        $codes = array_values($codes);
        usort($codes, function($a, $b) {
            return $a['name'] <=> $b['name'];
        });
        return $codes;
    }
}
