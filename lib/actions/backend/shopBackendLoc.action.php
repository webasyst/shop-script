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
            'SKUs: %d',
            'Parameters: %s',
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
            'This is a preview of actions available for orders in this state',
            'Maximum of %d orders is allowed for bulk form printing.',
            'Show %d more',
            'From',
            'Sorting in the common list is disabled. Drag-and-drop features into product types.',
            'Select parameters to be available to customers for ordering this product in the storefront.',
            'Nothing selected',
            'Matching features were not found or are already selected.',
            'Confirmed',
            'Product type adding error.',
            'Cannot save the value of the “GTIN” feature for this product.',
            'Cannot save the value of the “GTIN” feature for an SKU of this product.'
        ) as $s) {
            $strings[$s] = _w($s);
        }

        // plural forms hack
        foreach ($this->getPlurals() as $pair) {
            $strings[$pair[0]] = array(
                _w($pair[0]),
                str_replace(2, '%d', _w($pair[0], $pair[1], 2)),
                str_replace(5, '%d', _w($pair[0], $pair[1], 5))
            );
        }

        $this->view->assign('strings', $strings ? $strings : new stdClass()); // stdClass is used to show {} instead of [] when there's no strings

        $this->getResponse()->addHeader('Content-Type', 'text/javascript; charset=utf-8');
    }

    public function getPlurals()
    {
        return array(
            array/*_w*/('%d column will be processed', '%d columns will be processed'),
            array/*_w*/('%d column will be ignored', '%d columns will be ignored'),
            array/*_w*/("You’ve added %d product to this transfer already. Are you sure you want to change the source stock?", "You’ve added %d products to this transfer already. Are you sure you want to change the source stock?")
        );
    }
}
