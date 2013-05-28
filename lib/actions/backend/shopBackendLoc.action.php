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
            'Please select at least one product',
            'Files with extensions *.gif, *.jpg, *.jpeg, *.png are allowed only.',
            'New customer',
            'contain',
            'is the same',
            'matches base product value',
            'differs from base product value',
            'any of selected values (OR)',
            'all of selected values (AND)',
            'Save',
            'is not the same',
            'all',
            'any',
            'is',
            'Stop upload',
            'Please select a category',
            'Processing',
            'All orders',
            'Tag',
            'Price',
            'Upsell products will be offered for a particular base product according to the following criteria:',
            'or',
            'Click “Save” button below to commit the delete.',
            'Loading',
            'Products added:',
            'Images uploaded:',
            'New product',
            'This will delete this order state. Are you sure?'
        ) as $s) {
            $strings[$s] = _w($s);
        }

        // plural forms hack
        $strings['options'] = _w('option', 'options', 5);
        $strings['%d SKUs in total'] = str_replace(5, '%d', _w('%d SKU in total', '%d SKUs in total', 5));

        $this->view->assign('strings', $strings ? $strings : new stdClass()); // stdClass is used to show {} instead of [] when there's no strings

        $this->getResponse()->addHeader('Content-Type', 'text/javascript; charset=utf-8');
    }
}
