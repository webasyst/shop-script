<?php
return array(
    'app.installer' => array(
        'version' => '>=3.2.0', //don't use 'latest' for the framework as it may block installer.product.install API flow
        'strict' => true,
    ),
    'app.site' => array(
        'version' => '>=2.5.0',
        'strict' => true,
    ),
    'php' => array(
        'version' => '>=7.4.24',
        'strict'  => true,
    ),
);
