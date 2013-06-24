<?php
class shopYandexmarketPlugin extends shopPlugin
{
    private $map;
    public function map($post = array(), $sort = false)
    {
        $config_path = $this->path.'/lib/config/map.php';
        if (file_exists($config_path)) {
            $this->map = include($config_path);
        }
        if (!empty($post)) {
            foreach ($post as $field => $info) {
                if (isset($this->map[$field]) && !empty($info['source'])) {
                    $this->map[$field]['source'] = $info['source'];
                }
            }
        }
        $map = $this->map;
        if ($sort) {
            uasort($map, array($this, 'sort'));
        }
        return $map;
    }

    private function sort($a, $b)
    {
        return min(1, max(-1, (ifset($a['sort'], 1000) - ifset($b['sort'], 1000))));
    }

    public function categories()
    {
        return array();

        /**
         *
         * Use full settings
         * @todo
         */
        $available = array(
            'book'         => 'Книги',
            'audiobook'    => 'Аудиокниги',
            'music'        => 'Музыка',
            'film'         => 'Фильмы',
            'tours'        => 'Туры',
            'tickets'      => 'Билеты',
            'media'        => 'Медиапродукция',
            'vendor.model' => 'Бренды',
        );

        $types = $this->getSettings('types');
        $categories = array();
        $categories['vendor.model'] = $available['vendor.model'];
        foreach ($types as $type) {
            if (isset($available[$type])) {
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

    public static function uuid()
    {
        /**
         * @var shopYandexmarketPlugin $instance
         */
        $instance = wa()->getPlugin('yandexmarket');
        $uuid = $instance->getSettings('uuid');
        if (empty($uuid)) {
            $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', // 32 bits for "time_low"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), // 16 bits for "time_mid"
            mt_rand(0, 0xffff), // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000, // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000, // 48 bits for "node"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
            $instance->saveSettings(array('uuid' => $uuid));
        }
        return $uuid;
    }
}
