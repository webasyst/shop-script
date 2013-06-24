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
            default:
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

        $fp = @fopen('/dev/urandom', 'rb');
        if ($fp !== false) {
            $pr_bits = @fread($fp, 16);
            @fclose($fp);
        } else {
            // If /dev/urandom isn't available (eg: in non-unix systems), use mt_rand().
            $pr_bits = "";
            for ($cnt = 0; $cnt < 16; $cnt++) {
                $pr_bits .= chr(mt_rand(0, 255));
            }
        }
        $time_low = bin2hex(substr($pr_bits, 0, 4));
        $time_mid = bin2hex(substr($pr_bits, 4, 2));
        $time_hi_and_version = bin2hex(substr($pr_bits, 6, 2));
        $clock_seq_hi_and_reserved = bin2hex(substr($pr_bits, 8, 2));
        $node = bin2hex(substr($pr_bits, 10, 6));

        /**
         * Set the four most significant bits (bits 12 through 15) of the
         * time_hi_and_version field to the 4-bit version number from
         * Section 4.1.3.
         * @see http://tools.ietf.org/html/rfc4122#section-4.1.3
         */
        $time_hi_and_version = hexdec($time_hi_and_version);
        $time_hi_and_version = $time_hi_and_version >> 4;
        $time_hi_and_version = $time_hi_and_version | 0x4000;

        /**
         * Set the two most significant bits (bits 6 and 7) of the
         * clock_seq_hi_and_reserved to zero and one, respectively.
         */
        $clock_seq_hi_and_reserved = hexdec($clock_seq_hi_and_reserved);
        $clock_seq_hi_and_reserved = $clock_seq_hi_and_reserved >> 2;
        $clock_seq_hi_and_reserved = $clock_seq_hi_and_reserved | 0x8000;

        $uuid = sprintf('%08s-%04s-%04x-%04x-%012s', $time_low, $time_mid, $time_hi_and_version, $clock_seq_hi_and_reserved, $node);
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
