<?php
class shopAbtestVariantsModel extends waModel
{
    protected $table = 'shop_abtest_variants';

    public function getLastCode($abtest_id)
    {
        $sql = "SELECT code FROM {$this->table} WHERE abtest_id=? ORDER BY id DESC LIMIT 1";
        return $this->query($sql, array($abtest_id))->fetchField();
    }

    public function updateVariants($abtest_id, $variants)
    {
        $variant_ids = array();
        foreach($variants as $v) {
            if (!empty($v['id'])) {
                $v_id = $v['id'];
                unset($v['id'], $v['code'], $v['abtest_id']);
                if (empty($v['name'])) {
                    $v['name'] = _w('<no name>');
                }
                $variant_ids[$v_id] = $v_id;
                $this->updateById($v_id, $v);
            }
        }

        if (!$variant_ids) {
            $variant_ids[] = 0;
        }
        $sql = "DELETE FROM {$this->table} WHERE abtest_id=? AND id NOT IN (?)";
        $this->query($sql, array($abtest_id, $variant_ids));
    }

    public static function getNextCode($prev_code)
    {
        if (!$prev_code) {
            return 'A';
        }

        // code to integer
        $l = strlen($prev_code);
        $n = 0;
        for($i = 0; $i < $l; $i++)
            $n = $n*26 + ord($prev_code{$i}) - 0x40;

        // integer to code
        for($r = ""; $n >= 0; $n = intval($n / 26) - 1)
            $r = chr($n%26 + 0x41) . $r;

        return $r;
    }
}

