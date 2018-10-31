<?php

class shopSettingsScheduleAction extends waViewAction
{
    public function execute()
    {
        /**
         * @var shopConfig $shop_config
         */
        $shop_config = wa()->getConfig();
        $schedule = $shop_config->getSchedule();

        $this->view->assign(array(
            'timezones'       => wa()->getDateTime()->getTimezones(),
            'timezone'        => $schedule['timezone'],
            'week'            => $schedule['week'],
            'processing_time' => $schedule['processing_time'],
            'extra_workdays'  => $schedule['extra_workdays'],
            'extra_weekends'  => $schedule['extra_weekends'],
        ));
    }
}