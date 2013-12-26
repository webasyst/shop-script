<?php
/**
 * Class shopYandexmarketPlugin
 * @see http://help.yandex.ru/partnermarket/yml/about-yml.xml
 */
class shopYandexmarketPlugin extends shopPlugin
{
    private $types;

    private function initTypes()
    {
        $config_path = $this->path.'/lib/config/map.php';
        if (file_exists($config_path)) {
            $data = include($config_path);
            /**
             * @var $data array
             */

            $missed = array();
            $sort = array_flip(array_keys($data['fields']));
            foreach ($data['types'] as &$type) {
                foreach ($type['fields'] as $field => $required) {
                    if (empty($data['fields'][$field])) {
                        $missed[] = $field;
                    }
                    $type['fields'][$field] = array(
                        'required' => $required,
                        'sort'     => ifset($sort[$field], 0),
                    );
                    $type['fields'][$field] += ifempty($data['fields'][$field], array());
                }
                unset($type);
            }
            $this->types = $data['types'];
        } else {
            $this->types = array();
        }
    }


    public function map($post = array(), $types = null, $sort = false)
    {
        $this->initTypes();
        $map = $this->types;

        if (is_array($types)) {
            $types['simple'] = true;

            foreach ((array)$map as $type => $info) {
                if (empty($types[$type])) {
                    unset($map[$type]);
                }
            }
        }
        $pattern = '@^(.+):%s$@';
        if (!empty($post)) {
            foreach ($post as $type => $info) {
                if (isset($map[$type])) {
                    foreach ($info as $field => $post_data) {
                        if (isset($map[$type]['fields'][$field]) && !empty($post_data['source'])) {
                            if (preg_match($pattern, $post_data['source'], $matches)) {
                                switch ($source = $matches[1]) {
                                    case 'feature':
                                    case 'text':
                                        if (isset($post_data[$source]) && ($post_data[$source] !== '')) {
                                            $post_data['source'] = $source.':'.$post_data[$source];
                                        } else {
                                            $post_data['source'] = null;
                                        }
                                        break;
                                }
                            }
                            $map[$type]['fields'][$field]['source'] = $post_data['source'];
                        }
                    }
                }
            }
        }

        if ($sort) {
            foreach ($map as &$info) {
                uasort($info['fields'], array(__CLASS__, 'sort'));
                unset($info);
            }
        }

        return $map;
    }

    public function verifyMap()
    {

    }

    private static function sort($a, $b)
    {
        $a = ifset($a['sort'], 0);
        $b = ifset($b['sort'], 0);
        return max(-1, min(1, $a - $b));
    }

    /**
     * UI event handler
     * @return array
     */
    public function backendProductsEvent()
    {
        $icon = shopHelper::getIcon($this->getPluginStaticUrl().'img/yandexmarket.png');
        $html = <<<HTML
 <li data-action="export" data-plugin="yandexmarket" title="Экспорт выбранных товаров в Яндекс.Маркет">
    <a href="#">{$icon}Яндекс.Маркет</a>
 </li>
HTML;

        return array(
            'toolbar_export_li' => $html,
        );
    }

    public function categories()
    {
        $available = array(
            'simple'       => 'Упрощенное описание',
            'book'         => 'Книги (book)',
            'audiobook'    => 'Аудиокниги (audiobook)',
            'artist.title' => 'Музыкальная и видео продукция (artist.title)',
            'tours'        => 'Туры (tour)',
            'event-ticket' => 'Билеты на мероприятие (event-ticket)',
            'vendor.model' => 'Произвольный товар (vendor.model)',
        );

        $types = $this->getSettings('types');
        $categories = array();
        $categories['simple'] = $available['simple'];
        $categories['vendor.model'] = $available['vendor.model'];
        foreach ((array)$types as $type => $enabled) {
            if (!empty($enabled) && isset($available[$type])) {
                $categories[$type] = $available[$type];
            }
        }
        return $categories;
    }

    public static function path($file = 'market.xml')
    {
        $path = wa()->getDataPath('plugins/yandexmarket/'.$file, false, 'shop', true);
        if ($file == 'shops.dtd') {
            if (!file_exists($path)) {
                waFiles::copy(dirname(__FILE__).'/config/'.$file, $path);
            }
        }
        return $path;
    }

    public function getInfoByHash($hash)
    {
        $path = null;
        $uuid = $this->getSettings('uuid');
        $profile_id = null;
        if (!is_array($uuid)) {
            if ($uuid == $hash) {
                $path = self::path();
            }
        } else {
            if ((count($uuid) > 1) && isset($uuid[0])) {
                unset($uuid[0]);
            }
            $profile_id = array_search($hash, $uuid);
            if ($profile_id !== false) {
                $path = self::path($profile_id.'.xml');
            }
        }
        return array($path, $profile_id);
    }

    public function getHash($profile = 0)
    {
        $uuid = $this->getSettings('uuid');
        if (!is_array($uuid)) {
            if ($uuid) {
                $uuid = array(
                    0 => $uuid,
                );
            } else {
                $uuid = array();
            }
        }

        if ($profile) {
            $updated = false;
            if ((count($uuid) == 1) && isset($uuid[0])) {
                $uuid[$profile] = $uuid[0];
                $updated = true;
            } elseif (!isset($uuid[$profile])) {
                $uuid[$profile] = self::uuid();
                $updated = true;
            }
            if ($updated) {
                $this->saveSettings(array('uuid' => $uuid));
            }
        }
        return ifset($uuid[$profile]);
    }

    private static function uuid()
    {
        $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', // 32 bits for "time_low"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), // 16 bits for "time_mid"
            mt_rand(0, 0xffff), // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000, // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000, // 48 bits for "node"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
        return $uuid;
    }
}
