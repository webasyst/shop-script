<?php

class shopMarketingAbtestingAction extends shopMarketingViewAction
{
    public function execute()
    {
        shopReportsSalesAction::jsRedirectIfDisabled();

        $abtest_variants_model = new shopAbtestVariantsModel();
        $abtest_model = new shopAbtestModel();
        $tests = $abtest_model->getTests();

        $id = waRequest::param('id');

        if ($id == 'create') {
            $id = null;
            $test = $abtest_model->getEmptyRow();
        } elseif ($id && !empty($tests[$id])) {
            $test = $tests[$id];
        } elseif (empty($id) && $tests) {
            reset($tests);
            $id = key($tests);
            $test = ifempty($tests, $id, null);
        }

        if(empty($test)) {
            $id = null;
            $test = $abtest_model->getEmptyRow();
        }

        $variants = array();
        $variants_create = array();

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

        $this->view->assign(array(
            'stats'           => $this->getStats($test),
            'menu_types'      => shopReportsSalesAction::getMenuTypes(),
            'smarty_code'     => self::getSmartyCode($id, $variants),
            'variants_create' => $variants_create,
            'variants'        => $variants,
            'tests'           => $tests,
            'test'            => $test,
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

