<?php

class shopMarketingCostSaveController extends waJsonController
{
    public function execute()
    {
        shopReportsSalesAction::jsRedirectIfDisabled();

        $expense_id = waRequest::request('expense_id', null, waRequest::TYPE_INT);

        $expense_model = new shopExpenseModel();
        if ($expense_id) {
            $expense = $expense_model->getById($expense_id);
        }
        if (empty($expense) || !$expense_id) {
            $expense_id = null;
            $expense = $expense_model->getEmptyRow();
        }

        unset($expense['id']);
        $expense = array_intersect_key(waRequest::post('expense', [], waRequest::TYPE_ARRAY) + $expense, $expense);
        if (waRequest::post('expense_period_type') == 'timeframe') {
            $expense['start'] = waRequest::post('expense_period_from', '', waRequest::TYPE_STRING_TRIM);
            $expense['end'] = waRequest::post('expense_period_to', '', waRequest::TYPE_STRING_TRIM);
            if (empty($expense['start']) && empty($expense['end'])) {
                $this->addError('expense_period_single', _w('This field is required.'));
            } else {
                if (empty($expense['start'])) {
                    $this->addError('expense_period_from', _w('This field is required.'));
                } elseif (!waDateTime::parse('Y-m-d', $expense['start'])) {
                    $this->addError('expense_period_from', _w('Incorrect format.'));
                }
                if (empty($expense['end'])) {
                    $this->addError('expense_period_to', _w('This field is required.'));
                } elseif (!waDateTime::parse('Y-m-d', $expense['end'])) {
                    $this->addError('expense_period_to', _w('Incorrect format.'));
                }
                if (strtotime($expense['start']) > strtotime($expense['end'])) {
                    list($expense['start'], $expense['end']) = [$expense['end'], $expense['start']];
                }
            }
        } else {
            $expense['start'] = $expense['end'] = waRequest::post('expense_period_single', '', waRequest::TYPE_STRING_TRIM);
            if (empty($expense['start'])) {
                $this->addError('expense_period_single', _w('This field is required.'));
            } elseif (!waDateTime::parse('Y-m-d', $expense['start'])) {
                $this->addError('expense_period_single', _w('Incorrect format.'));
            }
        }

        if (empty($expense['amount'])) {
            $this->addError('expense[amount]', _w('This field is required.'));
        } elseif (!is_numeric($expense['amount'])) {
            $this->addError('expense[amount]', _w('Incorrect format.'));
        }

        if (empty($expense['type'])) {
            $this->addError('channel_selector', _w('This field is required.'));
        } elseif (!mb_strlen($expense['name'])) {
            $this->addError('expense[name]', _w('This field is required.'));
        }

        if (empty($expense['color'])) {
            $expense['color'] = '#f00';
        }

        if (!$this->errors) {
            if ($expense_id) {
                $expense_model->updateById($expense_id, $expense);
            } else {
                $expense_model->insert($expense);
            }

            // Clear sales chart cache for the period
            $sales_model = new shopSalesModel();
            $sales_model->deletePeriod($expense['start'], $expense['end']);
        }
    }

    protected function addError($id, $text)
    {
        $this->errors[] = [
            'id' => $id,
            'text' => $text
        ];
    }
}