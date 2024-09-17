<?php
/**
 * @since 10.0.0
 */
class shopSalesChannels
{
    public static function getDefaultChannel()
    {
        return [
            'id' => 'backend:',
            'type' => 'manager',
            'name' => _w('Backend'),
            'storefront' => null,
            'icon_url' => wa()->getConfig()->getRootUrl(true).'wa-content/img/userpic.svg', // !!!
        ];
    }

    public static function describeChannels(array $ensure_ids=[])
    {
        return self::getInstance()->getChannels($ensure_ids);
    }

    public static function canonicId($channel_id)
    {
        if ($channel_id === '' || $channel_id === null) {
            return 'backend:';
        }
        if (substr($channel_id, 0, 11) == 'storefront:') {
            return rtrim($channel_id, '/');
        }
        return $channel_id;
    }

    /* * * * * * * */

    protected static $instance;

    protected $known_channels;

    protected static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    protected static function getStubChannels(array $ensure_ids)
    {
        $result = [];
        foreach($ensure_ids as $id) {
            if (is_array($id)) {
                $id = ifset($id, 'id', null);
            }
            if ($id && is_scalar($id)) {
                $id = self::canonicId($id);
                $is_old_storefront = substr($id, 0, 11) == 'storefront:';
                $result[$id] = [
                    'id' => $id,
                    'type' => $is_old_storefront ? 'storefront' : 'unknown',
                    'name' => $id == 'other:' ? _w('Unknown channel') : $id,
                    'storefront' => null,
                    'icon_url' => wa()->getConfig()->getRootUrl(true).'wa-content/img/userpic.svg', // !!! разный в зависимости от $is_old_storefront
                ];
            }
        }
        return $result;
    }

    protected function getBuiltinChannels()
    {
        $result = [
            'backend:' => self::getDefaultChannel(),
        ];

        $idna = new waIdna();
        foreach (shopHelper::getStorefronts() as $s) {
            $id = self::canonicId('storefront:'.$s);
            $result[$id] = array(
                'id' => $id,
                'type' => 'storefront',
                'name' => $idna->decode($s),
                'storefront' => 'http://'.$s,
                'icon_url' => wa()->getConfig()->getRootUrl(true).'wa-content/img/userpic.svg', // !!!
            );
        }

        $result['pos:'] = [
            'id' => 'pos:',
            'type' => 'manager',
            'name' => _w('Mobile point of sale'),
            'storefront' => null,
            'icon_url' => wa()->getConfig()->getRootUrl(true).'wa-content/img/userpic.svg', // !!!
        ];

        $result['backend:ios'] = [
            'id' => 'backend:ios',
            'type' => 'manager',
            'name' => _w('Backend (iOS)'),
            'storefront' => null,
            'icon_url' => wa()->getConfig()->getRootUrl(true).'wa-content/img/userpic.svg', // !!!
        ];

        $result['backend:android'] = [
            'id' => 'backend:android',
            'type' => 'manager',
            'name' => _w('Backend (Android)'),
            'storefront' => null,
            'icon_url' => wa()->getConfig()->getRootUrl(true).'wa-content/img/userpic.svg', // !!!
        ];

        $result['buy_button:'] = [
            'id' => 'buy_button:',
            'type' => 'widget',
            'name' => _w('Buy button'),
            'storefront' => null,
            'icon_url' => wa()->getConfig()->getRootUrl(true).'wa-content/img/userpic.svg', // !!!
        ];

        return $result;
    }

    protected function getChannels(array $ensure_ids = [])
    {
        if (empty($this->known_channels)) {
            $this->known_channels = self::getBuiltinChannels();
            $this->sortKnownChannels();
        }

        $stub_channels = self::getStubChannels($ensure_ids);
        $missing_ids = array_keys(array_diff_key($stub_channels, $this->known_channels));
        if ($missing_ids || !$ensure_ids) {

            /**
             * @event sales_channels
             * Provide info about order sales channels plugin is responsible for.
             *
             * @param array $params['missing_channel_ids']
             * @param array $params['known_channels']
             * @return array
             * @since 10.0.0
             */
            $event_result = wa('shop')->event('sales_channels', ref([
                'missing_channel_ids' => $missing_ids,
                'known_channels' => $this->known_channels,
            ]));
            foreach($event_result as $plugin_result) {
                if (isset($plugin_result['id'])) {
                    $plugin_result = [$plugin_result];
                }
                foreach($plugin_result as $channel) {
                    try {
                        $channel = $this->prepareChannelFromPlugin($channel);
                        if (!isset($this->known_channels[$channel['id']])) {
                            $this->known_channels[$channel['id']] = $channel;
                        }
                    } catch (waException $e) {
                        continue;
                    }
                }
            }

            $missing_ids = array_keys(array_diff_key($stub_channels, $this->known_channels));
            if ($missing_ids || !$ensure_ids) {
                $event_params = array_column($this->known_channels, 'name', 'id');

                /**
                 * @event backend_reports_channels
                 * @deprecated
                 * @see sales_channels event instead
                 *
                 * Hook allows to set human-readable sales channel names for custom channels.
                 *
                 * Event $params is an array with keys being channel identifiers as specified
                 * in `sales_channel` order param.
                 *
                 * Plugins are expected to modify values in $params, setting human readable names
                 * to show in channel selector.
                 *
                 * @param array [string]string
                 * @return null
                 */
                wa('shop')->event('backend_reports_channels', $event_params);
                foreach($event_params as $channel_id => $channel_name) {
                    try {
                        $channel = $this->prepareChannelFromPlugin([
                            'id' => self::canonicId($channel_id),
                            'name' => ifempty($channel_name, $channel_id),
                        ]);
                        if (!isset($this->known_channels[$channel['id']])) {
                            $this->known_channels[$channel['id']] = $channel;
                        }
                    } catch (waException $e) {
                        continue;
                    }
                }

            }

            $this->known_channels += $stub_channels;
            $this->sortKnownChannels();
        }

        return $this->known_channels;
    }

    protected function sortKnownChannels()
    {
        $last = [];
        foreach(['buy_button:', 'backend:', 'other:'] as $k) {
            if (isset($this->known_channels[$k])) {
                $last[$k] = $this->known_channels[$k];
                unset($this->known_channels[$k]);
            }
        }

        $other = [];
        $old_storefronts = [];
        $existing_storefronts = [];
        foreach($this->known_channels as $k => $c) {
            if ($c['type'] == 'storefront') {
                if ($c['storefront']) {
                    $existing_storefronts[$k] = $c;
                } else {
                    $old_storefronts[$k] = $c;
                }
            } else {
                $other[$k] = $c;
            }
        }

        uasort($other, [$this, 'sortChannelsByName']);
        uasort($old_storefronts, [$this, 'sortChannelsByName']);
        uasort($existing_storefronts, [$this, 'sortChannelsByName']);

        $this->known_channels = $existing_storefronts + $old_storefronts + $other + $last;
    }

    public function sortChannelsByName($a, $b)
    {
        return strcmp($a['name'], $b['name']);
    }

    protected function prepareChannelFromPlugin($channel)
    {
        if (!is_array($channel) || !isset($channel['id']) || !is_scalar($channel['id'])) {
            throw new waException('bad channel');
        }

        $channel += [
            'name' => $channel['id'],
            'storefront' => null,
            'icon_url' => wa()->getConfig()->getRootUrl(true).'wa-content/img/userpic.svg', // !!!
        ];
        $channel['id'] = self::canonicId($channel['id']);
        if (!isset($channel['type']) || !in_array($channel['type'], ['manager', 'storefront', 'widget', 'marketplace', 'unknown'])) {
            $channel['type'] = substr($channel['id'], 0, 11) == 'storefront:' ? 'storefront' : 'unknown';
        }

        return $channel;
    }
}
