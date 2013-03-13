<?php

class shopPagesActions extends waPageActions
{

    protected $url = '#/pages/';
    protected $add_url = '#/pages/add';

    public function __construct()
    {
        if (!$this->getRights('pages')) {
            throw new waRightsException("Access denued");
        }
        $this->options['is_ajax'] = true;
        $this->options['container'] = false;
    }

}