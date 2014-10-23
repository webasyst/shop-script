<?php

/**
 * General settings form, and submit controller for it.
 */
class shopSettingsGeneralAction extends waViewAction
{
    public function execute()
    {
        if (waRequest::post()) {
            $app_settings = new waAppSettingsModel();
            foreach ($this->getData() as $name => $value) {
                $app_settings->set('shop', $name, $value);
            }

            $sms = waRequest::post('sms', array());
            $path = $this->getConfig()->getPath('config', 'sms');
            $save = array();
            foreach ($sms as $s) {
                $from = $s['from'];
                $adapter = $s['adapter'];
                unset($s['from']);
                unset($s['adapter']);
                $empty = true;
                foreach ($s as $v) {
                    if ($v) {
                        $empty = false;
                        break;
                    }
                }
                if (!$empty) {
                    if (!$from) {
                        $from = '*';
                    }
                    foreach (explode("\n", $from) as $from) {
                        $from = trim($from);
                        $save[$from] = $s;
                        $save[$from]['adapter'] = $adapter;
                    }
                }
            }
            waUtils::varExportToFile($save, $path);

        }
        
        $cm = new waCountryModel();
        $this->view->assign('countries', $cm->all());
        $this->view->assign($this->getConfig()->getGeneralSettings());
        $workhours = wa()->getSetting('workhours', null);
        if ($workhours) {
            $workhours = json_decode($workhours, true);
        }
        $this->view->assign('workhours', $workhours);
        
        $sms_adapters = $this->getSMSAdapters();
        $this->view->assign('sms_adapters', $sms_adapters);
        
        $this->view->assign('saved', waRequest::post());
    }

    public function getData()
    {
        $data = array(
            'name'         => waRequest::post('name', '', waRequest::TYPE_STRING_TRIM),
            'email'        => waRequest::post('email', '', waRequest::TYPE_STRING_TRIM),
            'phone'        => waRequest::post('phone', '', waRequest::TYPE_STRING_TRIM),
            'country'      => waRequest::post('country', '', waRequest::TYPE_STRING_TRIM),
            'order_format' => waRequest::post('order_format', '', waRequest::TYPE_STRING_TRIM),
            'use_gravatar' => waRequest::post('use_gravatar', '', waRequest::TYPE_INT),
            'gravatar_default' => waRequest::post('gravatar_default', '', waRequest::TYPE_STRING_TRIM),
            'require_captcha'  => waRequest::post('require_captcha', 0, waRequest::TYPE_INT),
            'require_authorization' => waRequest::post('require_authorization', 0, waRequest::TYPE_INT)
        );
        if (waRequest::post('workhours_type') !== null) {
            if (waRequest::post('workhours_type')) {
                $data['workhours'] = array();
                $data['workhours']['days'] = waRequest::post('workhours_days');
                $data['workhours']['from'] = waRequest::post('workhours_from');
                $data['workhours']['to'] = waRequest::post('workhours_to');
                $data['workhours'] = json_encode($data['workhours']);
            } else {
                $data['workhours'] = '';
            }
        }
        return $data;
    }
    
    protected function getSMSAdapters()
    {
        $path = $this->getConfig()->getPath('plugins').'/sms/';
        if (!file_exists($path)) {
            return array();
        }
        $dh = opendir($path);
        $adapters = array();
        while (($f = readdir($dh)) !== false) {
            if ($f === '.' || $f === '..' || !is_dir($path.$f)) {
                continue;
            } elseif (file_exists($path.$f.'/lib/'.$f.'SMS.class.php')) {
                require_once($path.$f.'/lib/'.$f.'SMS.class.php');
                $class_name = $f.'SMS';
                $adapters[$f] = new $class_name(array());
            }
        }
        closedir($dh);
        $result = array();


        $config = wa()->getConfig()->getConfigFile('sms');

        $used = array();
        foreach ($config as $c_from => $c) {
            if (isset($adapters[$c['adapter']])) {
                $used[$c['adapter']] = 1;
                if (!isset($result[$c['adapter']])) {
                    $temp = $this->getSMSAdapaterInfo($adapters[$c['adapter']]);
                    $temp['config'] = $c;
                    $temp['config']['from'] = array($c_from);
                    $result[$c['adapter']] = $temp;
                } else {
                    $result[$c['adapter']]['config']['from'][] = $c_from;
                }
            }
        }
        $result = array_values($result);
        
        foreach ($adapters as $a) {
            /**
             * @var waSMSAdapter $a
             */
            if (!empty($used[$a->getId()])) {
                continue;
            }
            $result[] = $this->getSMSAdapaterInfo($a);
        }
        return $result;

    }

    protected function getSMSAdapaterInfo(waSMSAdapter $a)
    {
        $temp = $a->getInfo();
        $temp['id'] = $a->getId();
        $temp['controls'] = $a->getControls();
        return $temp;
    }
}
