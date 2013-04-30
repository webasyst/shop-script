<?php
class shopCml1cPluginBackendSaveController extends waJsonController
{
    /**
     *
     * @return shopCml1cPlugin
     */
    private function plugin()
    {
        static $plugin;
        if (!$plugin) {
            $plugin = wa()->getPlugin('cml1c');
        }
        return $plugin;
    }

    public function execute()
    {
        $this->plugin()->uuid(waRequest::post('enabled', false));
        $this->response['url'] = $this->plugin()->getCallbackUrl();
    }
}
