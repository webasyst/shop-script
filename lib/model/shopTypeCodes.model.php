<?php
/**
 * Link between product types and codes.
 * type_id=0 means particular code is attached to all types - existing and possibly created in future.
 */
class shopTypeCodesModel extends waModel
{
    protected $table = 'shop_type_codes';

    public function getTypesByCode($code_id)
    {
        $result = [];
        foreach($this->getByField('code_id', $code_id, true) as $row) {
            $result[$row['type_id']] = [
                'id' => $row['type_id'],
            ];
        }
        return $result;
    }

    public function updateByCode($code_id, $code_type_ids)
    {
        $this->deleteByField('code_id', $code_id);
        $this->multipleInsert(array_map(function($type_id) use ($code_id) {
            return [
                'code_id' => $code_id,
                'type_id' => $type_id,
            ];
        }, array_values($code_type_ids)));
    }
}
