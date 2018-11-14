<?php

class shopSetEditAction extends waViewAction
{
    protected $template = 'DialogProductSet'; // NEW MODE

    public function execute()
    {
        $set_id = waRequest::get('set_id', null, waRequest::TYPE_STRING_TRIM);

        $config = wa('shop')->getConfig();
        $set_model = new shopSetModel();
        $settings = $set_model->getById($set_id);

        if (!$settings) {
            throw new waException('Set not found', 404);
        }

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
    }
}
