<?php

/**
 * Accepts data from affiliation transactions editor at customer info page.
 */
class shopCustomersAffiliateController extends waJsonController
{
    public function execute()
    {
        $contact_id = waRequest::post('contact_id', 0, 'int');
        $amount = (float) str_replace(',', '.', waRequest::post('amount', '0'));
        $comment = trim(waRequest::post('comment', ''));

        if (!$contact_id || !$amount) {
            return;
        }

        if (!$comment) {
            if ($amount < 0) {
                $comment = _w('Bonus pay out');
                $this->logAction('affiliate_payout', -$amount, $contact_id);
            } else {
                $comment = _w('Bonus credit');
                $this->logAction('affiliate_credit', $amount, $contact_id);
            }
        }

        $atm = new shopAffiliateTransactionModel();
        $atm->applyBonus($contact_id, $amount, null, ifempty($comment),
            $amount > 0 ? shopAffiliateTransactionModel::TYPE_DEPOSIT : shopAffiliateTransactionModel::TYPE_WITHDRAWAL);
    }
}

