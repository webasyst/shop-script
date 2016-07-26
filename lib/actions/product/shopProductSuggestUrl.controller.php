<?php

class shopProductSuggestUrlController extends waJsonController
{
    public function execute()
    {
        // transliterate name
        $url = shopHelper::transliterate((string) $this->getRequest()->get('name'));

        // if url in use this var has detail message, if not, this var is empty string
        $in_use = '';

        // suggest url from that list: {$url}, {$url}_1, {$url}_2, {$url}_3, ... $url_{$max_num_of_tries}
        $max_num_of_tries = 20;
        for ($try = 0; $try <= $max_num_of_tries; $try += 1) {
            $try_url = $url . ($try > 0 ? ('_' . $try) : '');
            $in_use = shopHelper::isProductUrlInUse(array('url' => $try_url, 'id' => 0));
            if (!$in_use) {
                break;
            }
        }

        // check how end loop, and form response
        if ($try <= $max_num_of_tries) {
            $this->response = array(
                'url' => $try_url, 'in_use' => ''
            );
        } else {
            $this->response = array(
                'url' => $url, 'in_use' => $in_use
            );
        }
    }
}