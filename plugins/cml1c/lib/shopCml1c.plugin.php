<?php
class shopCml1cPlugin extends shopPlugin
{
    public function getCallbackUrl($absolute = true)
    {
        $routing = wa()->getRouting();

        $route_params = array(
            'plugin' => $this->id,
            'hash'   => $this->uuid(),
        );
        return $routing->getUrl('shop/frontend/', $route_params, $absolute);
    }

    public function path($file = '1c.xml')
    {
        switch (wa()->getEnv()) {
            case 'frontend':
                $path = wa()->getDataPath('plugins/'.$this->id.'/'.$file, false, 'shop', true);
                break;
            case 'backend':
                $path = wa()->getTempPath('plugins/'.$this->id.'/'.$file, 'shop');
                break;
        }
        return $path;
    }

    public function validate($xml)
    {
        $dom = new DOMDocument("1.0", "UTF-8");

        $dom->encoding = 'UTF-8';
        $dom->loadXML($xml);
        libxml_use_internal_errors(true);
        $valid = $dom->schemaValidate($this->path.'/xml/CML205.xsd');
        if (!$valid) {
            $r = libxml_get_errors();
            $error = array();
            foreach ($r as $er) {
                $error[] = "Error #{$er->code}[{$er->level}] at [{$er->line}:{$er->column}]: {$er->message}";

            }
            $this->error(implode($error));
        }
        return $valid;
    }

    private function error($message)
    {
        $path = wa()->getConfig()->getPath('log');
        waFiles::create($path.'/shop/plugins/'.$this->id.'.log');
        waLog::log($message, 'shop/plugins/'.$this->id.'.log');
    }

    public static function makeUuid()
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

    public function uuid($enabled = null)
    {
        $refresh = false;
        $uuid = null;

        if ($enabled !== null) {
            if ($enabled && !($this->getSettings('enabled'))) {
                $refresh = true;
            } elseif (!$enabled && $this->getSettings('enabled')) {
                $refresh = true;
            }
        }

        if ($refresh) {
            if ($enabled) {
                $uuid = self::makeUuid();
            }
            $this->saveSettings(array(
                'uuid'    => $uuid,
                'enabled' => $enabled,
            ));
        } elseif ($this->getSettings('enabled')) {
            $uuid = $this->getSettings('uuid');
        }
        return $uuid;
    }

    public function exportTime($update = false)
    {
        $datetime = $this->getSettings('export_datetime');
        if (!is_array($datetime)) {
            $datetime = array();
        }

        $env = wa()->getEnv();
        if ($update) {
            $datetime[$env] = time();
            $this->saveSettings(array('export_datetime' => $datetime));
        }
        return ifset($datetime[$env]);
    }

}
