<?php

class shopWorkflowEditcodeAction extends shopWorkflowAction
{
    public function getDefaultOptions()
    {
        $options = parent::getDefaultOptions();
        $options['html'] = true;
        return $options;
    }

    public function isAvailable($order)
    {
        if (empty($order['id'])) {
            // When asked without a particular order, allow the action in list
            return true;
        }
        if (!($order instanceof shopOrder)) {
            $order = new shopOrder($order['id']);
        }

        foreach($order['items_product_codes'] as $item) {
            if (!empty($item['product_codes'])) {
                // Order has at least one item with product code set up for its type
                return true;
            }
        }

        // Order has no items with applicable product codes properly set up
        return false;
    }

    public function getHTML($order_id)
    {
        try {
            $order = new shopOrder($order_id);
        } catch (waException $ex) {
            if ($ex->getCode() === 404) {
                return false;
            } else {
                throw $ex;
            }
        }

        $user = wa()->getUser();
        $user_has_rights = $user->isAdmin('shop') || $user->getRights('shop', 'workflow_actions.editcode');

        $view = $this->getView();
        $view->assign(array(
            'user_has_rights' => $user_has_rights,
            'order'           => $order,
        ));
        return parent::getHTML($order_id);
    }

    public function execute($order_id = null)
    {
        $order = $this->order_model->getById($order_id);
        if (!$order) {
            return false;
        }

        $code_values = waRequest::post('code', [], 'array');

        // Allow to save existing product codes only - fetch them from DB
        $product_code_model = new shopProductCodeModel();
        $code_codes = $product_code_model->getById(array_keys($code_values));
        $code_codes = waUtils::getFieldValues($code_codes, 'code', true);

        $order_item_codes_model = new shopOrderItemCodesModel();

        // Also allow codes previously saved for this order
        $rows = $order_item_codes_model->query(
            'SELECT code_id, code
             FROM shop_order_item_codes
             WHERE order_id=?
                AND code_id NOT IN (?)',
            $order['id'],
            ifempty(ref(array_keys($code_codes)), 0)
        );
        foreach($rows as $row) {
            $code_codes[$row['code_id']] = $row['code'];
        }

        // Clean up previously saved codes for this order
        $order_item_codes_model->deleteByField([
            'order_id' => $order['id'],
            'code_id' => array_keys($code_values),
        ]);

        // Insert codes that came from POST
        $values = [];
        foreach($code_values as $code_id => $item_values) {
            if (!is_array($item_values) || !wa_is_int($code_id) || !isset($code_codes[$code_id])) {
                continue;
            }
            foreach($item_values as $item_id => $codes) {
                if (!is_array($codes) || !wa_is_int($item_id)) {
                    continue;
                }
                foreach(array_values($codes) as $i => $value) {
                    $value = trim($value);
                    if (!$value) {
                        continue;
                    }
                    $values[] = [
                        'order_id' => $order['id'],
                        'order_item_id' => $item_id,
                        'code_id' => $code_id,
                        'code' => $code_codes[$code_id],
                        'value' => $value,
                        'sort' => $i,
                    ];
                }
            }
        }

        $order_item_codes_model->multipleInsert($values);

        $this->waLog('order_editcode', $order_id);
        return array(
            'text' => _w('Product codes were changed'),
        );
    }

    public function getButton()
    {
        return parent::getButton(' data-form-in-dialog="1" ');
    }

    public function getOption($opt, $default=null)
    {
        if ($opt == 'allow_form_no_rights') {
            return true;
        }
        return parent::getOption($opt, $default);
    }
}
