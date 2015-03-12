<?php

class shopAffiliateTransactionModel extends waModel
{
    const TYPE_ORDER_BONUS = 'order_bonus';
    const TYPE_ORDER_DISCOUNT = 'order_discount';
    const TYPE_ORDER_CANCEL = 'order_cancel';
    const TYPE_DEPOSIT = 'deposit';
    const TYPE_WITHDRAWAL = 'withdrawal';

    protected $table = 'shop_affiliate_transaction';

    public function getByContact($contact_id)
    {
        $sql = "SELECT t.*, o.contact_id order_contact_id FROM ".$this->table." t LEFT JOIN shop_order o ON t.order_id = o.id
                WHERE t.contact_id = i:0 ORDER BY id DESC";
        return $this->query($sql, $contact_id)->fetchAll();
    }

    public function applyBonus($contact_id, $amount, $order_id=null, $comment=null, $type = null)
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

        if (!$type) {
            if ($order_id) {
                if ($amount < 0 && !$comment) {
                    $type = self::TYPE_ORDER_CANCEL;
                } else {
                    $type = $amount > 0 ? self::TYPE_ORDER_BONUS : self::TYPE_ORDER_DISCOUNT;
                }
            } else {
                $type = $amount > 0 ? self::TYPE_DEPOSIT : self::TYPE_WITHDRAWAL;
            }
        }

        $this->insert(array(
            'contact_id' => $contact_id,
            'amount' => (float) $amount,
            'order_id' => $order_id,
            'comment' => $comment,
            'balance' => $old_balance + $amount,
            'create_datetime' => date('Y-m-d H:i:s'),
            'type' => $type
        ));

        $sql = 'UPDATE shop_customer SET affiliate_bonus=? WHERE contact_id=?';
        $this->query($sql, $old_balance + $amount, $contact_id);

        // Write to order log
        if ($order_id) {
            $order_model = new shopOrderModel();
            $order = $order_model->getById($order_id);
            if ($order) {

                $contact_name = 'contact_id='.$contact_id;
                try {
                    $c = new waContact($contact_id);
                    $contact_name = htmlspecialchars($c->getName());
                } catch (Exception $e) {}

                if ($amount > 0) {
                    $text = sprintf_wp('Affiliate balance of %1$s is increased by amount %2$s', $contact_name, $amount);
                } else {
                    $text = sprintf_wp('Affiliate balance of %1$s is decreased by amount %2$s', $contact_name, -$amount);
                }

                if ($comment) {
                    $text .= ' ('.$comment.')';
                }

                $order_log_model = new shopOrderLogModel();
                $order_log_model->add(array(
                    'order_id' => $order_id,
                    'contact_id' => $contact_id,
                    'before_state_id' => $order['state_id'],
                    'after_state_id' => $order['state_id'],
                    'text' => $text,
                    'action_id' => '',
                ));
            }
        }
    }

    public function getLast($contact_id, $order_id)
    {
        $sql = "SELECT * FROM ".$this->table." WHERE contact_id = i:0 AND order_id = i:1 ORDER BY id DESC";
        return $this->query($sql, $contact_id, $order_id)->fetch();
    }
}
