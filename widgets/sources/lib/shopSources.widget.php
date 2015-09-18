<?php

class shopSourcesWidget extends waWidget
{
    public function defaultAction()
    {
        $this->display(array(
            'message' => 'Hello world!',
            'info' => $this->getInfo()
        ));
    }
}