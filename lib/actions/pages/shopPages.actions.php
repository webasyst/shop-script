<?php

class shopPagesActions extends waPageActions
{

    protected $url = '#/pages/';
    protected $add_url = '#/pages/add';

    public function __construct()
    {
        $can_upload_photos = false;
        if (waRequest::get('action') == 'uploadimage') {
            foreach ($this->getRights() as $type => $value) {
                if (strpos($type, 'type.') === 0 && $value >= 1) {
                    $can_upload_photos = true;
                    break;
                }
            }
        }
        if (!$this->getRights('pages') && !$can_upload_photos) {
            throw new waRightsException("Access denied");
        }
        $this->options['is_ajax'] = true;
        $this->options['container'] = false;
    }

    protected function getBlacklistUrl()
    {
        return array(
            '',
            'cart/',
            'order/',
            'my/',
            'my/profile/',
            'my/orders/',
        );
    }

}