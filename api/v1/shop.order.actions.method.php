<?php

class shopOrderActionsMethod extends shopApiMethod
{
    /**
     *  If color undefined, returns this color
     */
    const BASIC_COLOR = '#aaa';

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

            $b_class = $a->getOption('button_class');
            $b_color = $a->getOption('border_color');
            if (!empty($b_class)) {
                $color = self::getHexColorCode($b_class);
            } elseif (!empty($b_color)) {
                $color = '#'.$b_color;
            } else {
                $color = self::BASIC_COLOR;
            }

            if ($id === "delete") {
                $color = "#853133";
            } elseif ($id === "process") {
                $color = "#2e65c7";
            }

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
                'color'         => $color,
            );
        }
        return $result;
    }

    /**
     * Returns hex color code
     * @param $b_class
     * @return string
     */
    protected static function getHexColorCode($b_class)
    {
        $colors = array(
            'green'  => '#6c3',
            'yellow' => '#fdda3b',
            'blue'   => '#09f',
            'purple' => '#96a',
            'red'    => '#f60',
        );

        if (isset($colors[$b_class])) {
            return $colors[$b_class];
        } else {
            return self::BASIC_COLOR;
        }
    }
}
