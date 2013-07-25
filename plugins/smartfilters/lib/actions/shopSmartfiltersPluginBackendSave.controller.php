<?php
/**
 * Created by Hardman.com.ua.
 * Author: Eugen Nichikov <eugen@hardman.com.ua>
 * Date: 25.07.13
 * Time: 2:32
 */

class shopSmartfiltersPluginBackendSaveController extends waJsonController {

    public function execute()
    {
        try {
            $enabled = (int) waRequest::post('enabled');
            $app_settings_model = new waAppSettingsModel();
            $app_settings_model->set(array('shop', 'smartfilters'), 'enabled', $enabled);

            $template = waRequest::post('template');
            if(!$template) throw new waException('Не определён шаблон');

            $f = fopen(dirname(__FILE__).'/../../templates/actions/show/Show.html', 'w');
            if(!$f) throw new waException('Не удаётся сохранить шаблон. Проверьте права на запись wa-apps/shop/plugins/smartfilters/templates/actions/show/Show.html');
            if(!fwrite($f, $template)) throw new waException('Не удаётся сохранить шаблон. Проверьте права на запись wa-apps/shop/plugins/smartfilters/templates/actions/show/Show.html');
            fclose($f);

            $this->response['message'] = "Сохранено";
        } catch (Exception $e) {
            $this->setError($e->getMessage());
        }
    }
}