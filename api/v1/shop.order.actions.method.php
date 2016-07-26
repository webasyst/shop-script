<?php

class shopOrderActionsMethod extends shopApiMethod
{
    protected $method = 'GET';
    protected $courier_allowed = true;

    public function execute()
    {
        $order_id = @(int) $this->get('id', true);

        $order_model = new shopOrderModel();
        $order = $order_model->getById($order_id);
        if (!$order) {
            if ($this->courier) {
                throw new waAPIException('access_denied', 'Access denied to limited courier token.', 403);
            } else {
                throw new waAPIException('invalid_param', 'Order not found', 404);
            }
        }

        // Check courier access rights
        if ($this->courier) {
            $order_params_model = new shopOrderParamsModel();
            $params = $order_params_model->get($order_id);
            if (ifset($params['courier_id']) != $this->courier['id']) {
                throw new waAPIException('access_denied', 'Access denied to limited courier token.', 403);
            }
        }

        $workflow = new shopWorkflow();
        $actions = $workflow->getStateById($order['state_id'])->getActions($order);
        $this->response = self::formatActions($actions);
    }

    protected static function formatActions($actions)
    {
        $result = array();
        foreach($actions as $id => $a) {
            $type = 'button';
            if ($a->getOption('top') || $a->getOption('position') == 'top') {
                $type = 'link_top';
            } elseif ($a->getOption('position') == 'bottom') {
                $type = 'link_bottom';
            } elseif ($a->getOption('head') && $a->getHTML($id)) {
                $type = 'link_head';
            }

            $result[] = array(
                'id' => $id,
                'type' => $type,
                'is_custom' => !$a->original,
                'name' => $a->getName(),
                'data_required' => $id == 'edit' || !!$a->getHtml($id),
            );
        }
        return $result;
    }
}
