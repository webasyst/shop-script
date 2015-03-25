<?php
class shopAbtestModel extends waModel
{
    protected $table = 'shop_abtest';

    public function getTests()
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY id DESC";
        $result = $this->query($sql)->fetchAll('id');
        $this->workupTests($result);
        return $result;
    }

    public function workupTests(&$tests)
    {
        $sql = "SELECT av.abtest_id AS id, MIN(o.create_datetime) AS start, MAX(o.create_datetime) AS end
                FROM shop_order_params AS op
                    JOIN shop_abtest_variants AS av
                        ON op.value=av.id
                    JOIN shop_order AS o
                        ON op.order_id=o.id
                WHERE op.name='abtest_variant'
                GROUP BY av.abtest_id";
        $data = $this->query($sql)->fetchAll('id');
        foreach($tests as &$t) {
            $t += ifset($data[$t['id']], array(
                'start' => null,
                'end' => null,
            ));
        }
        unset($t, $data);
    }
}

