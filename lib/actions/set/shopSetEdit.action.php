<?php

class shopSetEditAction extends waViewAction
{
    protected $template = 'DialogProductSet'; // NEW MODE

    /**
     * @throws waException
     */
    public function execute()
    {
        $set_id = waRequest::get('set_id', null, waRequest::TYPE_STRING_TRIM);

        $config = wa('shop')->getConfig();
        $set_model = new shopSetModel();
        $settings = $set_model->getById($set_id);

        if (!$settings) {
            throw new waException(_w('Set not found.'), 404);
        }

        $settings['json_params'] = json_decode((string)$settings['json_params'], true);


        /**
         * @event backend_set_dialog
         * @param array $settings
         * @return array[string][string] $return[%plugin_id%] html output for dialog
         */
        $this->view->assign('event_dialog', wa()->event('backend_set_dialog', $settings));

        $this->view->assign(array(
            'hash'     => ['set', $set_id],
            'currency' => $config->getCurrency(),
            'settings' => $settings,
            'lang'     => substr(wa()->getLocale(), 0, 2),
            'routes'   => wa()->getRouting()->getByApp('shop')
        ));

        $this->view->assign($this->getDates($settings));
    }

    protected function getDates($settings)
    {
        $result = $settings['json_params'];

        if (empty($result['date_start'])) {
            $result['date_start'] = null;
        }

        if (empty($result['date_end'])) {
            $result['date_end'] = null;
        }

        return $result;
    }
}
