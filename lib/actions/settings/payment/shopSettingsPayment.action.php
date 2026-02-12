<?php

class shopSettingsPaymentAction extends waViewAction
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'settings')) {
            throw new waRightsException(_w('Access denied'));
        }

        $plugins = shopPayment::getList();

        $wa_pay_instance_exists = false;
        $model = new shopPluginModel();
        $instances = $model->listPlugins(shopPluginModel::TYPE_PAYMENT, [
            'all' => true,
            'list_storefronts' => true,
        ]);
        foreach ($instances as &$instance) {
            $wa_pay_instance_exists = $wa_pay_instance_exists || $instance['plugin'] === 'pay';
            $instance['installed'] = $instance['plugin'] === 'pay' || isset($plugins[$instance['plugin']]);
            unset($instance);
        }
        if ($wa_pay_instance_exists && !isset($plugins['pay'])) {
            try {
                $plugins['pay'] = waPayment::info('pay');
            } catch (Throwable $e) {
            }
        }

        $wa_pay_available = $wa_pay_instance_exists || $this->isWaPayAvailable();
        if ($wa_pay_available) {
            $promotion_enabled = shopHelper::waPayPromotionEnabled();
            $promotion_payment = $this->getPromotionData($promotion_enabled, $plugins);
        } else {
            $promotion_enabled = false;
            $promotion_payment = null;
        }

        $this->view->assign(array(
            'instances' => $instances,
            'plugins'   => $plugins,
            'installer' => $this->getUser()->getRights('installer', 'backend'),
            'wa_pay_available' => $wa_pay_available,
            'promotion_enabled' => $promotion_enabled,
            'promotion_payment' => $promotion_payment,
            'has_sales' => $promotion_payment ? $promotion_payment['per_year']['total_sales'] > 0 : false,
        ));
    }

    protected function getPromotionData($promotion_enabled, $plugins)
    {
        $result = [
            'per_month' => self::getPromotionDataForPeriod(30, $promotion_enabled, $plugins),
            'per_year' => self::getPromotionDataForPeriod(365, $promotion_enabled, $plugins),
        ];
        if ($promotion_enabled) {
            $result['per_year']['total'] .= '/'._w('year');
            $result['per_month']['total'] .= '/'._w('mo.');
        }
        return $result;
    }

    protected static function getPromotionDataForPeriod(int $days, ?bool $promotion_enabled=null, ?array $plugins = null)
    {
        if ($promotion_enabled === null) {
            $promotion_enabled = shopHelper::waPayPromotionEnabled();
        }
        if ($plugins === null) {
            $plugins = shopPayment::getList();
        }
        $plugins += [
            '' => [ // null
                'name' => _w('Manual payment'),
                'type' => 'manual',
            ],
            'cash' => [
                'name' => _w('Cash'),
                'type' => 'manual',
            ],
            'invoicejur' => [
                'name' => _w('Оплата по счету'),
                'type' => 'manual',
            ],
            'invoicephys' => [
                'name' => _w('Оплата по квитанции'),
                'type' => 'manual',
            ],
        ];
        if (!isset($plugins['dummy'])) {
            $plugins['dummy'] = shopPaymentDummy::dummyInfo();
        }

        $date_start = date('Y-m-d', strtotime("-{$days} days"));
        $date_end = date('Y-m-d');

        // Calculate total sales for period, group by payment plugin.
        $sales_model = new shopSalesModel();
        $table_data = $sales_model->getPeriod('payment_plugin', $date_start, $date_end, [
            'order'  => '!sales',
            'limit'  => 100500,
        ]);

        // Split WA Pay ('pay') stat in two: one for SBP, one for card payments
        $sbp_total_sales = 0;
        if (!$promotion_enabled) {
            [$sbp_total_sales, $sbp_total_fee] = self::getSbpTotalsForPeriod($date_start, $date_end);
        }

        $sbp_payment = [
            'name' => _w('СБП'),
            'sales' => 0,
            'is_sbp' => true,
        ];
        if ($sbp_total_sales > 0) {
            $reorder = false;
            foreach ($table_data as &$row) {
                if ($row['name'] === 'pay') {
                    if ($row['sales'] > $sbp_total_sales) {
                        $row['name'] = _w('Card payment');
                        $row['sales'] -= $sbp_total_sales;
                        $sbp_payment['sales'] = $sbp_total_sales;
                        $table_data[] = $sbp_payment;
                        $reorder = true;
                    } else {
                        $row['sales'] = $sbp_total_sales;
                    }
                    break;
                }
            }
            unset($row);
            if ($reorder) {
                usort($table_data, function($a, $b) {
                    return $a['sales'] <=> $b['sales'];
                });
            }
        } else if (!$promotion_enabled) {
            $table_data[] = $sbp_payment;
        }

        $total_sales = 0;
        $total_card_sales = 0;
        $included_sales = 0;
        $sales_pie_chart = [];
        foreach ($table_data as $row) {
            $total_sales += $row['sales'];
            if (ifset($plugins, $row['name'], 'type', 'online') !== 'manual') {
                $total_card_sales += $row['sales'];
            }
            if (count($sales_pie_chart) < 4 || !empty($row['is_sbp'])) {
                $included_sales += $row['sales'];
                $sales_pie_chart[] = [
                    'name' => ifset($plugins, $row['name'], 'name', $row['name']),
                    'value' => $row['sales'],
                    'is_sbp' => !empty($row['is_sbp']),
                ];
            }
        }
        if ($sales_pie_chart) {
            if ($included_sales < $total_sales) {
                if (count($sales_pie_chart) > 4) {
                    $excluded_row = array_pop($sales_pie_chart);
                    if (!empty($excluded_row['is_sbp'])) {
                        $sbp_row = $excluded_row;
                        $excluded_row = array_pop($sales_pie_chart);
                        $sales_pie_chart[] = $sbp_row;
                    }
                    $included_sales -= $excluded_row['value'];
                }
                $sales_pie_chart[] = [
                    'name' => _w('Other'),
                    'value' => $total_sales - $included_sales,
                ];
            }
            foreach ($sales_pie_chart as &$row) {
                if ($total_sales > 0) {
                    $row['value'] /= $total_sales;
                } else {
                    $row['value'] = 1.0 / count($sales_pie_chart);
                }
            }
            unset($row);
        } else {
            $sales_pie_chart[] = [
                'name' => _w('Other'),
                'value' => 1.0,
            ];
        }

        // Hardcoded fee percentages based on card payment via all plugins
        $total_fee_card = $total_card_sales*0.03;
        $total_fee_sbp_predicted = $total_card_sales*0.007;
        if ($promotion_enabled) {
            $total_fee_sbp_actual = $total_fee_sbp_predicted;
        } else {
            // This is real SBP fees paid
            //$total_fee_sbp_actual = $sbp_total_fee;

            // This is fee calculated from sales of all orders paid with SBP, hardcoded 0.7% rate
            $total_fee_sbp_actual = $sbp_total_sales*0.007;
        }
        // This is predicted card payment fees for SBP orders if all of them were paid with card
        $total_fee_card_predicted_from_sbp = ifempty($sbp_total_sales, $total_fee_sbp_actual / 0.007) * 0.03;

        $cur = function($amount) {
            return strip_tags(shop_currency_html(round($amount)));
        };

        $primary_currency = wa('shop')->getConfig()->getCurrency();
        $curForTotal = function($amount) use ($primary_currency) {
            return waCurrency::format('%k{h}', round($amount), $primary_currency);
        };

        return [
            'sales_chart' => $sales_pie_chart,
            'total_fee_sbp' => round($total_fee_sbp_actual),
            'total_fee_card' => round($total_fee_card),
            'total_fee_sbp_html' => $curForTotal($total_fee_sbp_actual),
            'total_fee_card_html' => $curForTotal($total_fee_card),
            'total_sales' => round($total_sales),
            'total_sales_html' => $curForTotal($total_sales),
            'total' => $cur($total_fee_card_predicted_from_sbp - $total_fee_sbp_actual),
            'turnover_on_cards' => $cur($total_card_sales),
            'card_fees' => $cur($total_fee_card),
            'sbp_fee' => $cur($total_fee_sbp_predicted),
            'total_savings' => sprintf('%s – %s = %s', $cur($total_fee_card), $cur($total_fee_sbp_predicted), $cur($total_fee_card - $total_fee_sbp_predicted)),
        ];
    }

    protected static function getSbpTotalsForPeriod($date_start, $date_end)
    {
        if (wa()->getSetting('reports_date_type', 'paid', 'shop') == 'create') {
            $order_date_sql = shopSalesModel::getDateSql('o.create_datetime', $date_start, $date_end).' AND o.paid_date IS NOT NULL';
        } else {
            $order_date_sql = shopSalesModel::getDateSql('o.paid_date', $date_start, $date_end);
        }

        $m = new waModel;
        $sql = "
            SELECT o.id, SUM(o.total*o.rate) AS `sales`
            FROM shop_order AS o
            JOIN shop_order_params AS op
                ON o.id=op.order_id
            WHERE {$order_date_sql}
                AND op.name='payment_is_sbp'
                AND op.value > 0
        ";

        $order_ids = [];
        $total_fee = $total_sales = 0;
        foreach ($m->query($sql) as $row) {
            $order_ids[] = $row['id'];
            $total_sales += $row['sales'];
        }

        if ($order_ids) {
            $total_fee = (float) $m->query("
                SELECT SUM(value)
                FROM shop_order_params
                WHERE name='payment_fee'
                    AND order_id IN (?)
            ", [$order_ids])->fetchField();
        }

        return [$total_sales, $total_fee];
    }

    protected function isWaPayAvailable()
    {
        try {
            wa('installer');
            return installerHelper::getGeoZone() === 'ru';
        } catch (Throwable $e) {
        }
        return false;
    }

}
