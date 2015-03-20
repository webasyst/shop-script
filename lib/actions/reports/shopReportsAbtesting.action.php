<?php

class shopReportsAbtestingAction extends waViewAction
{
    public function execute()
    {
        shopReportsSalesAction::jsRedirectIfDisabled();

        $abtest_variants_model = new shopAbtestVariantsModel();
        $abtest_model = new shopAbtestModel();
        $tests = $abtest_model->getTests();

        $id = waRequest::request('id', 0, 'int');
        if ($id && waRequest::post('delete')) {
            $abtest_model->deleteById($id);
            $abtest_variants_model->deleteByField('abtest_id', $id);
            unset($tests[$id], $_REQUEST['id'], $_GET['id'], $_POST['id']); // out of sight, out of mind!
            $id = 0;
        }
        if (!$id && !waRequest::request('id') && $tests) {
            reset($tests);
            $id = key($tests);
        }
        if ($id && !empty($tests[$id])) {
            $test = $tests[$id];
        }
        if(empty($test)) {
            $id = '';
            $test = $abtest_model->getEmptyRow();
        }

        // Save if data came via POST
        $errors = array();
        $variants = array();
        $variants_create = array();
        $data = waRequest::post('test', null, 'array');
        if ($data) {
            $data = array_intersect_key($data, $test) + $test;
            unset($data['id']);
            if (empty($data['name'])) {
                $errors['test[name]'] = _w('This field is required.');
            }

            if ($id) {
                foreach(waRequest::post('variants', array(), 'array') as $v_id => $v) {
                    if (!empty($v['code'])) {
                        $v = $variants[$v_id] = array(
                            'id' => $v_id,
                            'abtest_id' => $id,
                            'name' => ifempty($v['name'], ''),
                            'code' => $v['code'],
                        );
                        if (empty($v['name'])) {
                            $errors['variants['.$v_id.'][name]'] = _w('This field is required.');
                        }
                    }
                }
            }

            $empty_variant = $abtest_variants_model->getEmptyRow();
            $empty_variant['abtest_id'] = $id;
            unset($empty_variant['id']);
            $last_code = null;
            foreach(waRequest::post('new_variants', array(), 'array') as $v_name) {
                if (is_string($v_name) && strlen($v_name)) {
                    $last_code = $abtest_variants_model->getNextCode($last_code);
                    $variants_create[] = array(
                        'name' => $v_name,
                        'code' => $last_code,
                    ) + $empty_variant;
                }
            }

            if (count($variants) + count($variants_create) < 2) {
                $errors[''] = _w('Add at least two variants.');
                while (count($variants) + count($variants_create) < 2) {
                    $last_code = $empty_variant['code'] = $abtest_variants_model->getNextCode($last_code);
                    $variants_create[] = $empty_variant;
                }
            }

            if (!$errors) {
                if ($id) {
                    unset($data['create_datetime']);
                    $abtest_model->updateById($id, $data);
                    $abtest_variants_model->updateVariants($id, $variants);
                } else {
                    $data['create_datetime'] = date('Y-m-d H:i:s');
                    $id = $abtest_model->insert($data);
                    $tests = $abtest_model->getTests();
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
                    $variants = $abtest_variants_model->getByField('abtest_id', $id, 'id');
                    $variants_create = array();
                }
            }

            $test = $data + $test;
            $test['id'] = $id;
            if (!$errors) {
                $tests[$id] = $test;
            }
        } else {
            if ($id) {
                $variants = $abtest_variants_model->getByField('abtest_id', $id, 'id');
            } else {
                $empty_variant = $abtest_variants_model->getEmptyRow();
                unset($empty_variant['id']);
                $empty_variant['code'] = $abtest_variants_model->getNextCode(null);
                $variants_create[] = $empty_variant;
                $empty_variant['code'] = $abtest_variants_model->getNextCode($empty_variant['code']);
                $variants_create[] = $empty_variant;
            }
        }

        $this->view->assign(array(
            'stats' => $this->getStats($test),
            'menu_types' => shopReportsSalesAction::getMenuTypes(),
            'smarty_code' => self::getSmartyCode($id, $variants),
            'variants_create' => $variants_create,
            'variants' => $variants,
            'errors' => $errors,
            'tests' => $tests,
            'test' => $test,
        ));
    }

    protected function getStats($test)
    {
        $result = array(
            'orders_count' => 0,
            'orders_total' => 0,
            'date_min' => null,
            'date_max' => null,
        );
        if ($test['id']) {
            $sql = "SELECT count(*) AS orders_count, SUM(o.total*o.rate) AS orders_total, MIN(o.paid_date) AS date_min, MAX(DATE(o.create_datetime)) AS date_max
                    FROM shop_order_params AS op
                        JOIN shop_order AS o
                            ON op.order_id=o.id
                    WHERE op.name='abt{$test['id']}'
                        AND o.paid_date IS NOT NULL";
            $result = wao(new waModel())->query($sql)->fetchAssoc();
            $result['orders_total'] = ifempty($result['orders_total'], 0);
        }
        return $result;
    }

    protected static function getSmartyCode($id, $variants)
    {
        if (!$variants || !$id) {
            return '';
        }

        $result = '';
        foreach($variants as $v) {
            if (end($variants) === $v) {
                $result .= "{else}";
            } else if (reset($variants) === $v) {
                $result .= "{if \$wa->shop->ABtest({$id}) == '{$v['code']}'}";
            } else {
                $result .= "{elseif \$wa->shop->ABtest({$id}) == '{$v['code']}'}";
            }

            $result .= "\n\n\t".sprintf_wp('... your HTML for option “%s” here ...', $v['name'])."\n\n";
        }
        $result .= '{/if}';
        return $result;
    }
}

