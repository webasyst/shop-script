<?php

class shopContactsContacts_collectionHandler extends waEventHandler
{
    /**
    * @param $params[string][collection] waContactsCollection
    * @param $params[string][auto_title] boolean
    */
    public function execute(&$params) {
        /**
        * @var waContactsCollection
        */
        $collection = $params['collection'];
        $hash = $collection->getHash();
        if ($hash && $hash[0] === 'shop_customers') {
            $hash[1] = ifset($hash[1], '');
            
            /**
                * @event customers_collection
                * @param array [string]mixed $params
                * @param array [string]waContactsCollection $params['collection']
                * @param array [string]boolean $params['auto_title']
                * @param array [string]boolean $params['new']
                * @return array [string]bool|null if ignored, true when something changed in the collection
            */
            $processed_plugins = wa()->event(array('shop', 'customers_collection'), $params);
            $processed = false;
            foreach ($processed_plugins as $plugin_id => $is_proc) {
                if ($is_proc) {
                    $processed = true;
                    break;
                }
            }
            if (!$processed) {
                $preparator = new shopCustomersCollectionPreparator($collection, array('title_prefix' => _w('Shop customers').': '));
                if (preg_match('/^filter=([\d]+)$/', $hash[1], $m)) {
                    $preparator->filterPrepare($m[1], $params['auto_title']);
                } else if (preg_match('/^category=([\d]+)$/', $hash[1], $m)) {
                    $preparator->categoryPrepare($m[1], $params['auto_title']);
                } else {
                    if ($hash[1] && $hash[1] !== 'all') {
                        $preparator->searchPrepare($hash[1], $params['auto_title']);
                    } else {
                        $preparator->setTitle(_w('All'));
                    }
                }
                return true;   
            }
        }
        return false;
    }
}
