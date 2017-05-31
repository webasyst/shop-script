<?php

/**
 * Cron job to
 * php cli.php shop yandexmarketPluginExport 18
 */
class shopYandexmarketPluginExportCli extends waCliController
{
    public function execute()
    {
        $profile_id = waRequest::param(0, 0, waRequest::TYPE_INT);
        if ($profile_id) {
            $params = array(
                'profile_id' => $profile_id,
            );
            waRequest::setParam($params);

            $runner = new shopYandexmarketPluginRunController();
            $result = $runner->fastExecute($profile_id);
            if (!empty($result['success'])) {
                print($result['success']);
            } elseif (!empty($result['error'])) {
                print($result['error']);
            } elseif (!empty($result['warning'])) {
                print($result['warning']);
            }
            print "\n";
        } else {
            throw new waException('Missed profile id');
        }
    }
}
