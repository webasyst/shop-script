<?php

class shopWorkflowCallbackAction extends shopWorkflowAction
{
    public function execute($params = null)
    {
        $result = array();
        $result['text'] = $params['plugin'].' ';

        if (isset($params['callback_declined'])) {
            $reason = _w($params['callback_declined']);


            if (!empty($params['unsettled_order_id'])) {
                $template = '<br><em>'._w('Callback request was received but not applied to this order the following reason: <strong>%s</strong>').'</em>';
                $result['text'] .= sprintf($template, $reason);
                $result['text'] .= "\n\n".'<i class="icon16 icon16 ss flag-purple"></i><em>'.sprintf(_w("Unsettled order %s was created so you could resolve this case manually.").'</em>', $this->getOrderLink($params['unsettled_order_id']));
            } else {
                $template = '<br><em>'.'<i class="icon16 icon16 ss flag-purple"></i>'._w('This unsettled order was created because its initiating callback request was rejected by the original order it was addressed to. We thought you should resolve this case manually. The reason was: <strong>%s</strong>').'</em>';
                $result['text'] .= sprintf($template, $reason);
                if (!empty($params['original_order_id'])) {
                    $result['text'] .= "\n\n".'<em>'.sprintf(_w("Original order that did not accept the callback request: %s"), $this->getOrderLink($params['original_order_id'])).'</em>';
                }
            }
            $result['text'] .= "\n\n".$this->getCallbackDetails($params);
        } else {

            $result['text'] .= $this->getCallbackDetails($params);
            $result['params'] = array();
            if (isset($params['id'])) {
                $result['params']['payment_transaction_id'] = $params['id'];
            }
            $result['callback_transaction_data'] = $params;
        }

        return $result;
    }


    public function postExecute($params = null, $result = null)
    {
        if (is_array($params)) {
            $order_id = $params['order_id'];
        } else {
            $order_id = $params;
        }
        return parent::postExecute($order_id, $result);
    }

    private function getCallbackDetails($params)
    {
        $data = empty($params['view_data']) ? '' : ($params['view_data'].' - ');
        return $params['state'].' ('.$data.$params['amount'].' '.$params['currency_id'].')';
    }

    private function getOrderLink($id)
    {
        $id_str = htmlentities(shopHelper::encodeOrderId($id), ENT_QUOTES, 'utf-8');
        return sprintf('<a href="#/order/%d/" class="inline-link">%s</a>', $id, $id_str);
    }
}
