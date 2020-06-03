<?php

/**
 * Class shopStorefrontList
 *
 * Some kind of collection/list encapsulation of storefronts with function programming alike interface
 *
 * You can:
 *  - add several filter/condition to list: addFilter
 *  - extract result array after filtering, with or without mapping: toArray(), toArray($map)
 *  - call own callback for each item (storefront) of filtered result: each($callback)
 *  - reduce filtered result into one value: reduce($callback, $initial)
 *  - combine map & reduce: mapReduce($map, $callback, $initial)
 *
 * Also there is shopStorefrontList::getAllStorefronts static method if you want just get all storefronts without any Black Jack & whores
 *
 *
 */
class shopStorefrontList
{
    protected static $all_storefronts = null;
    protected static $checkout_configs = [];

    protected $filters = array();

    /**
     * @param array|callable $filter
     * @param string|null $id - unique ID of filter, in case if you want delete filter from list later (actual only for lambda filters)
     *
     *   If array
     *     - string <key> is property to filter by
     *     - string <value> is constraint to filter by
     *
     *   If callable - will work as array_filter
     *
     * What filters are supported for now:
     * - 'contact_type' - filter by contact_type that enabled in this storefront, this filter also add 'contact_type' info in result, see fetchAll
     *
     * @example
     *   array(
     *       'contact_type' => 'person'
     *   )
     *
     * @return int|string|array|null - unique ID of filter, in case if you want delete filter from list later, for not-lambda filters ID is <key> of array
     */
    public function addFilter($filter, $id = null)
    {
        if (is_callable($filter)) {
            if ($id === null) {
                $id = '#callable#' . count($this->filters);
            }
            $this->filters[$id] = $filter;
            return $id;
        } elseif (is_array($filter) && !empty($filter)) {
            foreach ($filter as $key => $value) {
                $this->filters[$key] = $value;
            }
            return array_keys($filter);
        }
        return null;
    }

    /**
     * @param string|string[] $id
     */
    public function deleteFilter($id)
    {
        if (is_scalar($id)) {
            $id = array($id);
        }
        if (!is_array($id)) {
            return;
        }
        $ids = $id;
        foreach ($ids as $id) {
            if (isset($this->filters[$id])) {
                unset($this->filters[$id]);
            }
        }
    }

    /**
     *
     */
    public function deleteAllFilters()
    {
        $this->filters = array();
    }

    /**
     * Get all storefronts
     * Not filter implied
     * Data is runtime cached (for all instances via static cache)
     * @param bool $verbose
     * @return array
     */
    public function getAll($verbose = false)
    {
        if (self::$all_storefronts === null) {
            self::$all_storefronts = $this->obtainAll();
        }
        if ($verbose) {
            return self::$all_storefronts;
        } else {
            return waUtils::getFieldValues(self::$all_storefronts, 'url');
        }
    }

    public static function clearCache()
    {
        self::$all_storefronts = null;
    }

    /**
     * Static helper for getting all storefronts
     * Get all storefronts
     * Not filter implied
     * Data is runtime cached (for all instances via static cache)
     * @param bool $verbose
     * @return array
     */
    public static function getAllStorefronts($verbose = false)
    {
        $list = new self();
        return $list->getAll($verbose);
    }

    /**
     * @return array
     */
    protected function obtainAll()
    {
        $storefronts = array();
        $idna = new waIdna();
        $routing = new waRouting(wa());
        foreach ($routing->getByApp('shop') as $domain => $domain_routes) {
            foreach ($domain_routes as $route) {
                $url = rtrim($domain.'/'.$route['url'], '/*');
                if (strpos($url, '/') !== false) {
                    $url .= '/';
                }
                $storefronts[] = array(
                    'domain'      => $domain,
                    'route'       => $route,
                    'url'         => $url,
                    'url_decoded' => $idna->decode($url),
                );
            }
        }
        return $storefronts;
    }

    /**
     * @param array $storefronts
     * @return array
     */
    protected function applyFilters($storefronts)
    {
        $filters = $this->filters;

        if (isset($filters['contact_type'])) {
            $storefronts = $this->filterOffByContactType($storefronts, $filters['contact_type']);
            unset($filters['contact_type']);
        }

        if (!$storefronts) {
            return array();
        }

        if (isset($filters['url'])) {
            $storefronts = $this->filterOffByUrl($storefronts, $filters['url']);
            unset($filters['url']);
        }

        if (!$storefronts) {
            return array();
        }

        foreach ($filters as $filter) {
            if (is_callable($filter)) {
                $storefronts = array_filter($storefronts, $filter);
                if (!$storefronts) {
                    return array();
                }
            }
        }

        return $storefronts;
    }

    /**
     * Return result array of storefronts
     * Apply all filters before
     * @param callable|null|string $map
     *   If null       - just array of filtered results (default)
     *   If callable   - call array_map to array of filtered results
     *   If callable[] - call one be one array_map - make in chain. Be careful - output of one callback must be compatible with input of other
     *   If string     - call waUtils::getFieldValues to array of filtered results
     *   If array      - list of extra fields to add or extra callbacks to call (see callable[] case)
     *     supported fields:
     *       - 'contact_type' - add info about contact type for this storefront
     *       - 'checkout_config' - instance of shopCheckoutConfig | null (for old checkout)
     * @return array
     */
    public function fetchAll($map = null)
    {
        $storefronts = $this->getAll(true);
        $storefronts = $this->applyFilters($storefronts);

        if (is_callable($map)) {
            return array_map($map, $storefronts);
        } elseif (is_array($map)) {
            $result = $storefronts;
            foreach ($map as $m) {
                if (is_callable($m)) {
                    $result = array_map($m, $result);
                } elseif ($m === 'checkout_config') {
                    $result = $this->addCheckoutConfig($result);
                } elseif ($m === 'contact_type') {
                    $result = $this->addContactTypeInfo($result);
                }
            }
            return $result;
        } elseif (is_scalar($map)) {
            return waUtils::getFieldValues($storefronts, $map);
        } else {
            return $storefronts;
        }
    }

    /**
     * @return int
     */
    public function count()
    {
        $storefronts = $this->getAll(true);
        $storefronts = $this->applyFilters($storefronts);
        return count($storefronts);
    }

    /**
     * See about $map in fetchAll - here the same format
     * @param null $map
     * @return mixed|null
     */
    public function fetchFirst($map = null)
    {
        $storefronts = $this->fetchAll($map);
        if (!$storefronts) {
            return null;
        }
        return reset($storefronts);
    }

    /**
     * Call for each storefront (in filtered list) callback
     *
     * You can use $map to modify result array before call each callback
     *
     * @param callable $callable function($storefront) {}
     * @param callable|null|string $map - passed to toArray
     * @see fetchAll
     */
    public function each($callable, $map = null)
    {
        if (is_callable($callable)) {
            foreach ($this->fetchAll($map) as $storefront) {
                call_user_func($callable, $storefront);
            }
        }
    }

    /**
     * Reduce filtered array to one value (call array_reduce)
     * @param $callable
     * @param null $initial
     * @see fetchAll
     * @return mixed
     */
    public function reduce($callable, $initial = null)
    {
        return array_reduce($this->fetchAll(null), $callable, $initial);
    }

    /**
     * Reduce filtered map array result to one value (call array_reduce after toArray($map))
     * @param callable|null|string $map - passed to toArray
     * @param $callable
     * @param null $initial
     * @return mixed
     */
    public function mapReduce($map, $callable, $initial = null)
    {
        return array_reduce($this->fetchAll($map), $callable, $initial);
    }

    /**
     * @param $storefronts
     * @param string $contact_type
     * @return array
     */
    protected function filterOffByContactType($storefronts, $contact_type = shopCustomer::TYPE_PERSON)
    {
        $storefronts = $this->addContactTypeInfo($storefronts);
        foreach ($storefronts as $index => $storefront) {
            if (!$storefront['contact_type'][$contact_type]['enabled']) {
                unset($storefronts[$index]);
            }
        }
        return $storefronts;
    }


    /**
     * @param $storefronts
     * @return array - list of storefronts with added key 'contact_info', values of that is array indexed by contact type (shopCustomer::TYPE)
     *
     * 'contact_info' => array(
     *      <contact_type> => array(
     *          'enabled' => TRUE|FALSE
     *      )
     * )
     */
    protected function addContactTypeInfo($storefronts)
    {
        foreach ($storefronts as &$storefront) {
            $checkout_version = ifset($storefront, 'route', 'checkout_version', false);

            $storefront['contact_type'] = array(
                shopCustomer::TYPE_PERSON => array(
                    'enabled' => false
                ),
                shopCustomer::TYPE_COMPANY => array(
                    'enabled' => false
                )
            );

            if ($checkout_version < 2) {
                $storefront['contact_type'][shopCustomer::TYPE_PERSON]['enabled'] = true;
                continue;
            }

            /**
             * @var shopCheckoutConfig $config
             */
            if (isset($storefront['checkout_config']) && $storefront['checkout_config'] instanceof shopCheckoutConfig) {
                $config = $storefront['checkout_config'];
            } else {
                $storefront_id = $storefront['route']['checkout_storefront_id'];
                $config = $this->newCheckoutConfig($storefront_id);
                $storefront['checkout_config'] = $config;
            }

            $type = ifset($config, 'customer', 'type', shopCheckoutConfig::CUSTOMER_TYPE_PERSON);

            $storefront['contact_type'][shopCustomer::TYPE_PERSON]['enabled'] = $type === shopCheckoutConfig::CUSTOMER_TYPE_PERSON ||
                $type === shopCheckoutConfig::CUSTOMER_TYPE_PERSON_AND_COMPANY;

            $storefront['contact_type'][shopCustomer::TYPE_COMPANY]['enabled'] = $type === shopCheckoutConfig::CUSTOMER_TYPE_COMPANY ||
                $type === shopCheckoutConfig::CUSTOMER_TYPE_PERSON_AND_COMPANY;

        }
        unset($storefront);
        return $storefronts;
    }

    /**
     * @param $storefronts
     * @return array list of storefronts with added key 'checkout_config', that is shopCheckoutConfig or NULL (for old checkout)
     *     'checkout_config' => shopCheckoutConfig|NULL
     *
     * Why for OLD checkout is NULL - cause for OLD checkout storefronts there is ONE shared array of settings - shopConfig->getCheckoutSettings()
     *  so just extract it by yourself
     */
    protected function addCheckoutConfig($storefronts)
    {
        foreach ($storefronts as &$storefront) {

            $checkout_version = ifset($storefront, 'route', 'checkout_version', false);

            $config = null;

            if ($checkout_version >= 2) {
                /**
                 * @var shopCheckoutConfig $config
                 */
                if (isset($storefront['checkout_config']) && $storefront['checkout_config'] instanceof shopCheckoutConfig) {
                    $config = $storefront['checkout_config'];
                } else {
                    $storefront_id = $storefront['route']['checkout_storefront_id'];
                    $config = $this->newCheckoutConfig($storefront_id);
                }
            }

            $storefront['checkout_config'] = $config;
        }
        unset($storefront);

        return $storefronts;
    }


    /**
     * @param array $storefronts
     * @param string|string[] $url
     * @return array
     */
    protected function filterOffByUrl($storefronts, $url)
    {
        $urls = waUtils::toStrArray($url);
        $urls = array_map(function ($url) {
            return trim($url, '/');
        }, $urls);
        $urls = array_fill_keys($urls, true);

        $result = array();
        foreach ($storefronts as $storefront) {
            $storefront_url = trim($storefront['url'], '/');
            if (isset($urls[$storefront_url])) {
                $result[] = $storefront;
            }
        }

        return $result;
    }

    /**
     * @param $storefront_id
     * @return shopCheckoutConfig|null
     */
    protected function newCheckoutConfig($storefront_id)
    {
        if (array_key_exists($storefront_id, self::$checkout_configs)) {
            return self::$checkout_configs[$storefront_id];
        }

        try {
            self::$checkout_configs[$storefront_id] = new shopCheckoutConfig($storefront_id);
        } catch (waException $e) {
            self::$checkout_configs[$storefront_id] = null;
        }

        return self::$checkout_configs[$storefront_id];
    }
}
