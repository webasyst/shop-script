<?php

try {
    waFiles::delete($this->getAppPath('js/customers.js'), true);
    waFiles::delete($this->getAppPath('lib/actions/settings/shipping/shopSettingsShippingClone.controller.php'), true);
} catch (waException $e) {

}
