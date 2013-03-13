<?php

class shopAffiliateTransactionModel extends waModel
{
    protected $table = 'shop_affiliate_transaction';

    public function getByContact($contact_id)
    {
        return $this->where('contact_id=?', $contact_id)->order('id DESC')->fetchAll();
    }

    public function applyBonus($contact_id, $amount, $order_id=null, $comment=null)
    {
        $amount = (float) $amount;
        if ($amount == 0) {
            return;
        }

        $sql = "SELECT affiliate_bonus FROM shop_customer WHERE contact_id=?";
        $old_balance = $this->query($sql, $contact_id)->fetchField();
        if ($old_balance === null) {
            return;
        }
        $old_balance = (float) $old_balance;

        //
        // !!! FIXME: Race condition is possible here. Use locking or something?..
        //

        $this->insert(array(
            'contact_id' => $contact_id,
            'amount' => (float) $amount,
            'order_id' => $order_id,
            'comment' => $comment,
            'balance' => $old_balance + $amount,
            'create_datetime' => date('Y-m-d H:i:s'),
        ));

        $sql = 'UPDATE shop_customer SET affiliate_bonus=? WHERE contact_id=?';
        $this->query($sql, $old_balance + $amount, $contact_id);
    }
}
