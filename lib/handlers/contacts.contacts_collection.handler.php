<?php
/**
 * This event handler is a part of app integration between Shop and Mailer.
 * Idea is: when Mailer attempts to use a waContactsCollection with hash shop_customers/*
 * (which hash is of course unknown to waContactsCollection), collection will fire this event.
 * This handler then is responsible to prepare the collection to return the same customers
 * as shopCustomersCollection would return with corresponding hash.
 *
 * This scheme is the reason shopCustomersCollectionPreparator come to be.
 *
 * Note that neither shopCustomersCollection nor Preparator are aware
 * of hashes like shop_customers/*. Normal shopCustomer hash is converted to
 * shop_customers/* hash in one of Mailer handlers, namely: mailerShopBackend_customers_listHandler.
 *
 * $this->removeShopCustomersFromHash() is supposed to convert them back.
 */
class shopContactsContacts_collectionHandler extends waEventHandler
{
    /**
     * @param $params [string][collection] waContactsCollection
     * @param $params [string][auto_title] boolean
     * @return bool|void
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
                // Set hash on a collection like normal shopCustomersCollection would accept
                // this makes sure shopCustomersCollectionPreparator works properly.
                $collection->setHash($this->removeShopCustomersFromHash($hash));

                $preparator = new shopCustomersCollectionPreparator($collection, array('title_prefix' => _wd('shop','Shop customers').': '));
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

                $collection->setHash(join('/', $hash));
                return true;
            }
        }
        return false;
    }

    // this is supposed to be a reverse operation to what mailerShopBackend_customers_listHandler does to a hash
    protected static function removeShopCustomersFromHash($hash)
    {
        if ($hash[0] !== 'shop_customers') {
            throw new waException('unexpected input');
        }

        unset($hash[0]);

        if (preg_match('/^filter=([\d]+)$/', $hash[1], $m)) {
            return 'filter/'.substr(join('/', $hash), 7);
        } else if (preg_match('/^category=([\d]+)$/', $hash[1], $m)) {
            return 'category/'.substr(join('/', $hash), 9);
        } else if (!strlen($hash[1]) || $hash[1] === 'all') {
            return '';
        } else if (count($hash) == 1) {
            return 'search/'.join('/', $hash);
        } else {
            return join('/', $hash);
        }
    }
}
