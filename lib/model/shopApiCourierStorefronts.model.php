<?php
class shopApiCourierStorefrontsModel extends waModel
{
    protected $table = 'shop_api_courier_storefronts';

    public function getByCourier($id)
    {
        $return_single = false;
        if (!is_array($id)) {
            $return_single = true;
            $id = array($id);
        }

        $id = array_filter($id, 'intval');
        if (!$id) {
            return array();
        }

        $result = array_fill_keys($id, array_fill_keys(shopHelper::getStorefronts(), false));
        foreach($this->getByField('courier_id', $id, true) as $row) {
            $courier_id = $row['courier_id'];
            $url = $row['storefront'];
            $url1 = rtrim($url, '/');
            $url2 = $url1.'/';
            if (isset($result[$courier_id][$url1])) {
                $result[$courier_id][$url1] = true;
            } elseif (isset($result[$courier_id][$url2])) {
                $result[$courier_id][$url2] = true;
            } else {
                $result[$courier_id][$url] = true;
            }
        }

        if ($return_single) {
            return $result[reset($id)];
        } else {
            return $result;
        }
    }
}
