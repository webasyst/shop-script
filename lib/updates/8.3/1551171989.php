<?php

$_files_to_delete = array(
    $this->getAppPath('lib/classes/shopCustomer.class.php'),
    $this->getAppPath('lib/classes/shopCustomers.class.php'),
    $this->getAppPath('lib/classes/shopCustomersCollection.class.php'),
    $this->getAppPath('lib/classes/shopCustomersCollectionPreparator.class.php'),
);

foreach ($_files_to_delete as $_file_path) {

    try {
        waFiles::delete($_file_path, true);
    } catch (waException $e) {

    }

}
