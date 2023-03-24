<?php
class shopPresentationColumnsModel extends waModel
{
    protected $table = 'shop_presentation_columns';

    public function fillPresentationColumns($presentations)
    {
        $pres = [];
        foreach($presentations as $p) {
            $p['columns'] = [];
            $pres[$p['id']] = $p;
        }
        $presentations = $pres;

        if ($presentations) {
            $sql = "SELECT * FROM {$this->table}
                    WHERE presentation_id IN (?)
                    ORDER BY presentation_id, sort";
            foreach ($this->query($sql, [array_keys($presentations)]) as $row) {
                $row['data'] = json_decode((string)$row['data'], true);
                $presentations[$row['presentation_id']]['columns'][] = $row;
            }
        }

        return $presentations;
    }

    protected function getFieldValue($field, $value)
    {
        if ($field == 'data' && is_array($value)) {
            return "'" . json_encode($value) . "'";
        }
        return parent::getFieldValue($field, $value);
    }
}
