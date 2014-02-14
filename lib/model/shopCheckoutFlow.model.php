<?php

class shopCheckoutFlowModel extends waModel
{
    protected $table = 'shop_checkout_flow';
    
    public function add($data)
    {
        // cart code
        if (empty($data['code'])) {
            $cart = new shopCart();
            $data['code'] = $cart->getCode();
        }
        
        if (empty($data['step'])) {
            $data['step'] = 0;
        }
        
        if (empty($data['description'])) {
            $data['description'] = null;
        }
        
        // if current step of current cart-code with current description exists - ignore
        if ($this->getByField(array(
            'code' => $data['code'],
            'step' => $data['step'],
            'description' => $data['description']
        ))) {
            return true;
        }
        
        if (empty($data['contact_id'])) {
            $data['contact_id'] = wa()->getUser()->getId();
        }
        
        $time = time();
        $data['year'] = date('Y', $time);
        $data['month'] = date('m', $time);
        $data['quarter'] = floor((date('n', $time) - 1) / 3) + 1;
        $data['date'] = date('Y-m-d', $time);
        return $this->insert($data);
    }
    
    public static function getDateSql($fld, $start_date, $end_date)
    {
        $date_sql = array();
        if ($start_date) {
            $date_sql[] = $fld." >= DATE('".$start_date."')";
        }
        if ($end_date) {
            $date_sql[] = $fld." <= DATE('".$end_date."')";
        }
        if ($date_sql) {
            return implode(' AND ', $date_sql);
        } else {
            return $fld." IS NOT NULL";
        }
    }
    
    public function getStat($start_date = null, $end_date = null)
    {
        $date_sql = self::getDateSql('date', $start_date, $end_date);
        $sql = "SELECT step, COUNT(id) as count FROM `{$this->table}` WHERE {$date_sql} GROUP BY step";

        $stat = $this->query($sql)->fetchAll('step');
        
        $step_names = array(
            _w('Cart')
        );
        foreach (wa('shop')->getConfig()->getCheckoutSettings() as $item) {
            $step_names[] = $item['name'];
        }
        $step_names[] = _w('Order was placed');
        
        foreach ($stat as $i => &$st) {
            $st['name'] = $step_names[$i];
        }
        unset($st);
        
        $cnt = count($stat);
        for ($i = 0; $i < $cnt; $i += 1) {
            $count = 0;
            for ($j = $i; $j < $cnt; $j += 1) {
                $count += $stat[$j]['count'];
            }
            $stat[$i]['count'] = $count;
        }
        
        // convert to percents
        foreach ($stat as &$st) {
            $st['percents'] = round($st['count'] / $stat[0]['count'], 5) * 100;
        }
        unset($st);
        
        return $stat;
        
    }
    
    public function clear()
    {
        $this->query("DELETE FROM `{$this->table}` WHERE 1");
    }
}