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
            'SKUs: %d', // _w('SKUs: %d')
            'Parameters: %s', // _w('Parameters: %s')
            'Customize', // _w('Customize')
            'Plugins', // _w('Plugins')
            'Profile name', // _w('Profile name')
            'New profile', // _w('New profile')
            'This will reset all changes you applied to the image after upload, and will restore the image to its original. Are you sure?', // _w('This will reset all changes you applied to the image after upload, and will restore the image to its original. Are you sure?')
            'draft', // _w('draft')
            'New page', // _w('New page')
            'This will delete entire page. Are you sure?', // _w('This will delete entire page. Are you sure?')
            'clear', // _w('clear')
            'Click “Save” button below to apply this change.', // _w('Click “Save” button below to apply this change.')
            'Are you sure?', // _w('Are you sure?')
            'Configure', // _w('Configure')
            'Turn on', // _w('Turn on')
            'Disabled', // _w('Disabled')
            'On', // _w('On')
            'Off', // _w('Off')
            'Close', // _w('Close')
            'Cancel', // _w('Cancel')
            'Delete', // _w('Delete')
            'Saved', // _w('Saved')
            'Empty result', // _w('Empty result')
            'Please select at least one product', // _w('Please select at least one product')
            'Files with extensions *.gif, *.jpg, *.jpeg, *.png, *.webp are allowed only.', // _w('Files with extensions *.gif, *.jpg, *.jpeg, *.png, *.webp are allowed only.')
            'New customer', // _w('New customer')
            'contain', // _w('contain')
            'is the same', // _w('is the same')
            'matches base product value', // _w('matches base product value')
            'differs from base product value', // _w('differs from base product value')
            'any of selected values (OR)', // _w('any of selected values (OR)')
            'all of selected values (AND)', // _w('all of selected values (AND)')
            'Save', // _w('Save')
            'is not the same', // _w('is not the same')
            'all', // _w('all')
            'any', // _w('any')
            'is', // _w('is')
            'Stop upload', // _w('Stop upload')
            'Please select a category', // _w('Please select a category')
            'Processing', // _w('Processing')
            'All orders', // _w('All orders')
            'Tag', // _w('Tag')
            'Price', // _w('Price')
            'Upselling products will be offered for a particular base product according to the following criteria:', // _w('Upselling products will be offered for a particular base product according to the following criteria:')
            'or', // _w('or')
            'Click “Save” button below to commit the delete.', // _w('Click “Save” button below to commit the delete.')
            'Loading', // _w('Loading')
            'Products added:', // _w('Products added:')
            'Images uploaded:', // _w('Images uploaded:')
            'New product', // _w('New product')
            'This will delete this order state. Are you sure?', // _w('This will delete this order state. Are you sure?')
            'Perform action to all selected orders?', // _w('Perform action to all selected orders?')
            'There are no orders in this view.', // _w('There are no orders in this view.')
            'Please save changes to be able to send tests.', // _w('Please save changes to be able to send tests.')
            'A product must have at least one SKU.', // _w('A product must have at least one SKU.')
            'Drag products here', // _w('Drag products here')
            'Order action will be deleted. Are you sure?', // _w('Order action will be deleted. Are you sure?')
            'Sales', // _w('Sales')
            'Profit', // _w('Profit')
            '%s will be sent to customer by email. Are you sure?', // _w('%s will be sent to customer by email. Are you sure?')
            'This is a preview of actions available for orders in this state', // _w('This is a preview of actions available for orders in this state')
            'Maximum of %d orders is allowed for bulk form printing.', // _w('Maximum of %d orders is allowed for bulk form printing.')
            'Show %d more', // _w('Show %d more')
            'From', // _w('From')
            'Sorting in the common list is disabled. Drag-and-drop features into product types.', // _w('Sorting in the common list is disabled. Drag-and-drop features into product types.')
            'Select parameters to be available to customers for ordering this product in the storefront.', // _w('Select parameters to be available to customers for ordering this product in the storefront.')
            'Nothing selected', // _w('Nothing selected')
            'Matching features were not found or are already selected.', // _w('Matching features were not found or are already selected.')
            'Confirmed', // _w('Confirmed')
            'Product type adding error.', // _w('Product type adding error.')
            'Cannot save the value of the “GTIN” feature for this product.', // _w('Cannot save the value of the “GTIN” feature for this product.')
            'Cannot save the value of the “GTIN” feature for an SKU of this product.', // _w('Cannot save the value of the “GTIN” feature for an SKU of this product.')
            'An error occurred', // _w('An error occurred')
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
