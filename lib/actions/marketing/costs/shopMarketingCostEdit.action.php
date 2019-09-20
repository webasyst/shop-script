<?php

class shopMarketingCostEditAction extends shopMarketingViewAction
{
    public function execute()
    {
        shopReportsSalesAction::jsRedirectIfDisabled();

        $expense_id = waRequest::request('expense_id', '', 'int');

        // Get existing record data from DB
        $expense_model = new shopExpenseModel();
        if ($expense_id) {
            $expense = $expense_model->getById($expense_id);
        }
        if (empty($expense) || !$expense_id) {
            $expense_id = '';
            $expense = $expense_model->getEmptyRow();
        }

        // Handle validation and saving if data came via POST
        $errors = array();
        unset($expense['id']);
        if (waRequest::post()) {
            $expense = array_intersect_key(waRequest::post('expense', array(), 'array') + $expense, $expense);

            if (waRequest::post('expense_period_type') == 'timeframe') {
                $expense['start'] = waRequest::post('expense_period_from', '', 'string');
                $expense['end'] = waRequest::post('expense_period_to', '', 'string');
                if (empty($expense['start']) && empty($expense['end'])) {
                    $errors['expense_period_single'] = _w('This field is required.');
                } else {
                    if (empty($expense['start'])) {
                        $errors['expense_period_from'] = _w('This field is required.');
                    } elseif (!strtotime($expense['start'])) {
                        $errors['expense_period_from'] = _w('Incorrect format.');
                    }
                    if (empty($expense['end'])) {
                        $errors['expense_period_to'] = _w('This field is required.');
                    } elseif (!strtotime($expense['end'])) {
                        $errors['expense_period_to'] = _w('Incorrect format.');
                    }
                    if (strtotime($expense['start']) > strtotime($expense['end'])) {
                        list($expense['start'], $expense['end']) = array($expense['end'], $expense['start']);
                    }
                }
            } else {
                $expense['start'] = $expense['end'] = waRequest::post('expense_period_single', '', 'string');
                if (empty($expense['start'])) {
                    $errors['expense_period_single'] = _w('This field is required.');
                } elseif (!strtotime($expense['start'])) {
                    $errors['expense_period_single'] = _w('Incorrect format.');
                }
            }

            if (empty($expense['amount'])) {
                $errors['expense[amount]'] = _w('This field is required.');
            } elseif (!is_numeric($expense['amount'])) {
                $errors['expense[amount]'] = _w('Incorrect format.');
            }

            if (empty($expense['type'])) {
                $errors['channel_selector'] = _w('This field is required.');
            } elseif (empty($expense['name'])) {
                $errors['expense[name]'] = _w('This field is required.');
            }

            if (empty($expense['color'])) {
                $expense['color'] = '#f00';
            }

            if (!$errors) {
                if ($expense_id) {
                    $expense_model->updateById($expense_id, $expense);
                } else {
                    $expense_id = $expense_model->insert($expense);
                }

                // Clear sales chart cache for the period
                $sales_model = new shopSalesModel();
                $sales_model->deletePeriod($expense['start'], $expense['end']);
            }
        }

        $expense['id'] = $expense_id;

        // Promo, on the basis of which it is necessary to create a cost
        $promo_id = waRequest::request('promo_id', null, waRequest::TYPE_INT);
        $promo_model = new shopPromoModel();
        $promo = $promo_model->getById($promo_id);

        // Prepare data for template
        $def_cur = wa()->getConfig()->getCurrency();
        $this->view->assign(array(
            'storefronts' => shopReportsSalesAction::getStorefronts(),
            'campaigns'   => shopMarketingCostsAction::getCampaigns($expense['type'] == 'campaign' ? $expense['name'] : null, $expense['color']),
            'sources'     => shopMarketingCostsAction::getSources($expense['type'] == 'source' ? $expense['name'] : null, $expense['color']),
            'promos'      => shopMarketingCostsAction::getPromos($expense['type'] == 'promo' ? $expense['name'] : null, $expense['color']),
            'promo'       => $promo,
            'expense'     => $expense,
            'def_cur'     => $def_cur,
            'errors'      => $errors,
        ));
    }
}