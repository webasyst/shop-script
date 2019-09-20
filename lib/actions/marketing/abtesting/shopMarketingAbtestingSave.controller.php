<?php

class shopMarketingAbtestingSaveController extends waJsonController
{
    public function execute()
    {
        $abtest_variants_model = new shopAbtestVariantsModel();
        $abtest_model = new shopAbtestModel();

        $id = waRequest::post('id', null, waRequest::TYPE_INT);

        $test = $abtest_model->getById($id);
        if ($id && empty($test)) {
            $this->errors[] = array(
                'id'   => 'not_found',
                'text' => _w('A/B test not found.'),
            );
            return;
        }

        if (empty($test)) {
            $test = $abtest_model->getEmptyRow();
            $id = null;
            unset($test['id']);
        }

        $data = waRequest::post('test', null, waRequest::TYPE_ARRAY);
        $data = array_intersect_key($data, $test) + $test;

        $this->validateData($data);
        if (!empty($this->errors)) {
            return;
        }

        $variants = waRequest::post('variants', array(), waRequest::TYPE_ARRAY);
        if (!empty($id)) {
            $this->validateVariants($variants);
            if (!empty($this->errors)) {
                return;
            }

            foreach ($variants as $v_id => $variant) {
                $variants[$v_id] = array(
                    'id'        => $v_id,
                    'abtest_id' => $id,
                    'name'      => ifempty($variant, 'name', ''),
                    'code'      => $variant['code'],
                );
            }
        }

        $variants_create = array();

        $empty_variant = $abtest_variants_model->getEmptyRow();
        $empty_variant['abtest_id'] = $id;
        unset($empty_variant['id']);
        $last_code = null;

        $new_variants = waRequest::post('new_variants', array(), waRequest::TYPE_ARRAY);

        foreach($new_variants as $v_name) {
            if (is_string($v_name) && strlen($v_name)) {
                $last_code = $abtest_variants_model->getNextCode($last_code);
                $variants_create[] = array(
                        'name' => $v_name,
                        'code' => $last_code,
                    ) + $empty_variant;
            }
        }

        if (count($variants) + count($variants_create) < 2) {
            $this->errors[] = array(
                'id'   => 'invalid_count',
                'text' => _w('Add at least two variants.'),
            );

            return;
        }

        if ($id) {
            unset($data['create_datetime']);
            $abtest_model->updateById($id, $data);
            $abtest_variants_model->updateVariants($id, $variants);
        } else {
            $data['create_datetime'] = date('Y-m-d H:i:s');
            $id = $abtest_model->insert($data);
        }

        if ($variants_create) {
            $last_code = $abtest_variants_model->getLastCode($id);
            foreach($variants_create as &$v) {
                $last_code = $abtest_variants_model->getNextCode($last_code);
                $v['abtest_id'] = $id;
                $v['code'] = $last_code;
            }
            unset($v);

            $abtest_variants_model->multipleInsert($variants_create);
        }

        $this->response = array(
            'id' => $id,
        );
    }

    protected function validateData(array $data)
    {
        if (empty($data['name'])) {
            $this->errors[] = array(
                'name' => 'test[name]',
                'text' => _w('This field is required.'),
            );
        }
    }

    protected function validateVariants(array $variants)
    {
        foreach ($variants as $id => $variant) {
            if (empty($variant['name'])) {
                $this->errors[] = array(
                    'name' => 'variants['.$id.'][name]',
                    'text' => _w('This field is required.'),
                );
            }
        }
    }
}