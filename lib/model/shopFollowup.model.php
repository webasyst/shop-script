<?php

/**
 * Follow-ups are email messages sent to customers when some time passed after a successful order.
 * E.g. to offer additional service related to their order, or to thank them.
 *
 * Clarification for table columns.
 * - `delay`: number of seconds to wait after order.paid_date before sending follow-up.
 * - `first_order_only`: 0 to send after every successful order; 1 to only send once per customer, after their first order.
 * - `subject`, `body`: email message
 * - `last_cron_time`: all orders with their paid_date less than this datetime are already processed by cron job.
 *    Note that this datetime is actually <last cron call time> minus `delay`.
 */
class shopFollowupModel extends waModel
{
    protected $table = 'shop_followup';
    
    public function getAllEnabled($key = null, $normalize = false) {
        $sql = "SELECT * FROM ".$this->table . " WHERE status = 1";
        $followups = $this->query($sql)->fetchAll($key, $normalize);
        if (!empty($followups)) {
            $followup_sources_model = new shopFollowupSourcesModel();
            $ids = array_column($followups , 'id');
            $sources = $followup_sources_model->getByField('followup_id', $ids, true);
            foreach ($followups as &$followup) {
                foreach ($sources as $source) {
                    if ($source['followup_id'] == $followup['id']) {
                        $followup['sources'][] = isset($source['source']) ? $source['source'] : 'all_sources';
                    }
                }
            }
            unset($followup);
        }
        return $followups;
    }
    
}

