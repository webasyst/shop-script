<?php

class shopOrdersWidget extends waWidget
{
    public function defaultAction()
    {
        if (!wa()->getUser()->getRights('shop', 'orders')) {
            $this->display(array(), $this->getTemplatePath('NoAccess.html'));
            return;
        }

        $states = self::getStates();

        // Sum of totals for all orders currently procesing
        $processing_count = 0;
        $processing_amount = 0;
        foreach(array('new', 'processing', 'paid') as $state_id) {
            if (!empty($states[$state_id])) {
                $processing_amount += $states[$state_id]['amount'];
                $processing_count += $states[$state_id]['count'];
            }
        }

        $this->display(array(
            'states' => $states,
            'processing_amount' => $processing_amount,
            'processing_count' => $processing_count,
            'size' => $this->info['size'],
        ));
    }

    protected static function getStates()
    {
        $result = array();

        $wf = new shopWorkflow();
        $states = $wf->getAllStates();

        // Put states in resulting list in order specified by the workflow
        foreach($states as $state_id => $s) {
            $st = $states[$state_id];
            $result[$state_id] = array(
                'name' => $st->getName(),
                'icon' => $st->getOption('icon'),
                'style' => $st->getStyle(),
                'amount' => 0,
                'count' => 0,
            );
        }

        // Get counts and totals
        $m = new waModel();
        $sql = "SELECT state_id, SUM(total*rate) AS amount, COUNT(*) AS `count`
                FROM shop_order
                GROUP BY state_id";
        foreach($m->query($sql) as $row) {
            // Deleted state?
            if (empty($states[$row['state_id']])) {
                $result[$row['state_id']] = array(
                    'name' => $row['state_id'],
                    'icon' => 'icon16 broom-bw',
                    'style' => 'color:#999999',
                );
            }

            $result[$row['state_id']]['count'] = $row['count'];
            $result[$row['state_id']]['amount'] = $row['amount'];
        }

        return $result;
    }
}
