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
            'Customize',
            'Plugins',
            'Profile name',
            'New importexport profile',
            'This will reset all changes you applied to the image after upload, and will restore the image to its original. Are you sure?',
            'draft',
            'New page',
            'This will delete entire page. Are you sure?',
            'clear',
            'Click “Save” button below to apply this change.',
            'Are you sure?',
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
            'This will delete this order state. Are you sure?',
            'Perform action to all selected orders?',
            "There are no orders in this view.",
            "Please save changes to be able to send tests.",
            "A product must have at least one SKU.",
            "Drag products here",
            "Order action will be deleted. Are you sure?",
            'Sales',
            'Profit',
            '%s will be sent to customer by email. Are you sure?',
        ) as $s) {
            $strings[$s] = _w($s);
        }

        $n = 5;
        // plural forms hack
        $strings['options'] = _w('option', 'options', $n);
        $strings['%d SKUs in total'] = str_replace($n, '%d', _w('%d SKU in total', '%d SKUs in total', $n));
        $strings['%d column will be processed'] = str_replace($n, '%d', _w('%d column will be processed', '%d columns will be processed', $n));
        $strings['%d column will be ignored'] = str_replace($n, '%d', _w('%d column will be ignored', '%d columns will be ignored', $n));

        $this->view->assign('strings', $strings ? $strings : new stdClass()); // stdClass is used to show {} instead of [] when there's no strings

        $this->getResponse()->addHeader('Content-Type', 'text/javascript; charset=utf-8');
    }
}
