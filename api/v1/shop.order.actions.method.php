<?php

class shopOrderActionsMethod extends waAPIMethod
{
    protected $method = 'GET';

    public function execute()
    {
        $order_id = @(int) $this->get('id', true);

        $order_model = new shopOrderModel();
        $order = $order_model->getById($order_id);
        if (!$order) {
            throw new waAPIException('invalid_param', 'Order not found', 404);
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
