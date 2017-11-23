<?php

/**
 * Cron job to
 * php cli.php shop yandexmarketPluginCache 18
 */
class shopYandexmarketPluginCacheCli extends waCliController
{

    public function execute()
    {
        $campaign_id = max(0, waRequest::param(0, 0, waRequest::TYPE_INT));
        if ($campaign_id) {
            $plugin = wa('shop')->getPlugin('yandexmarket');
            /**
             * @var shopYandexmarketPlugin $plugin
             */
            try {
                $outlets = $plugin->getOutlets($campaign_id);
                print sprintf("Fetched %d outlets\n", count($outlets));
            } catch (waException $ex) {
                print "Error: ".$ex->getMessage()."\n";
            }

            try {
                $campaign = $plugin->apiRequest(sprintf('campaigns/%d/settings', $campaign_id));
                if (!empty($campaign['settings']['localRegion']['delivery']['schedule'])) {
                    $model = new shopYandexmarketCampaignsModel();
                    $model->set($campaign_id, 'schedule', $campaign['settings']['localRegion']['delivery']['schedule']);
                    print "Update delivery schedule\n";
                }
            } catch (waException $ex) {
                print "Error: ".$ex->getMessage()."\n";
            }
        } else {
            throw new waException('Missed campaign id param');
        }
    }
}
