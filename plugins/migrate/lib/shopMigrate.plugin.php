<?php
class shopMigratePlugin extends shopPlugin
{
    public function getTransports()
    {
        return array(
            'webasystsame'   => array(
                'name'        => _wp('WebAsyst Shop-Script (old version) on the same server'),
                'description' => 'Migrate aux pages, categories, products with params, features, images and eproduct files',
                'platform'    => 'Webasyst',
            ),
            'webasystremote' => array(
                'name'        => _wp('WebAsyst Shop-Script (old version) on a remote server'),
                'description' => '',
                'platform'    => 'Webasyst',
            ),
        );
    }

}
