<?php

/**
 * @description "shop/?action=settings#/plugins/"
 */
class shopSettingsPluginsAction extends waViewAction {
    public function execute() {
        if (!$this->getUser()->getRights('shop', 'settings')) {
            throw new waRightsException(_w('Access denied'));
        }

    }
}
