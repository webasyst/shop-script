<?php

class shopOrderActionsMethod extends shopApiMethod
{
    protected $method = 'GET';
    protected $courier_allowed = true;

    public function execute()
    {
        $order_id = @(int)$this->get('id', true);

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
            $courier_id = $order_params_model->getOne($order_id, 'courier_id');
            if (empty($courier_id) || ($courier_id != $this->courier['id'])) {
                throw new waAPIException('access_denied', 'Access denied to limited courier token.', 403);
            }
        }

        $workflow = new shopWorkflow();
        $actions = $workflow->getStateById($order['state_id'])->getActions($order);
        $this->response = self::formatActions($actions, $order_id);
    }

    /**
     * @param shopWorkflowAction[] $actions
     * @param int $order_id
     * @return array
     */
    protected static function formatActions($actions, $order_id)
    {
        $result = array();
        foreach ($actions as $id => $a) {
            $type = 'button';
            if ($a->getOption('top') || $a->getOption('position') == 'top') {
                $type = 'link_top';
            } elseif ($a->getOption('position') == 'bottom') {
                $type = 'link_bottom';
            } elseif ($a->getOption('head') && $a->getHTML($order_id)) {
                $type = 'link_head';
            }

            $result[] = array(
                'id'            => $id,
                'type'          => $type,
                'is_custom'     => !$a->original,
                'name'          => $a->getName(),
                'data_required' => $id == 'edit' || !!$a->getHTML($order_id),
            );
        }
        return $result;
    }
}
