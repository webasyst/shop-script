<?php

/**
 * A list of localized strings to use in JS.
 */
class shopBackendLocAction extends waViewAction
{
    public function execute()
    {
        $strings = array();

        // Application locale strings
        foreach(array(
            'Configure',
            'Turn on',
            'Disabled',
            'On',
            'Off',
            'Close',
            'Saved',
            'Empty result',
            'Files with extensions *.gif, *.jpg, *.jpeg, *.png are allowed only.',
            'New customer',
            'contain',
            'is the same',
            'is not the same',
            'all',
            'any',
            'is',
            'Please select a category'
        ) as $s) {
            $strings[$s] = _w($s);
        }

        $this->view->assign('strings', $strings ? $strings : new stdClass()); // stdClass is used to show {} instead of [] when there's no strings

        $this->getResponse()->addHeader('Content-Type', 'text/javascript; charset=utf-8');
    }
}
