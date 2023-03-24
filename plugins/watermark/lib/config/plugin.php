<?php

return array(
    'name'        => 'Watermark',
    'description' => 'Applies watermark text or image on uploaded photos',
    'img'         => 'img/watermark.png',
    'vendor'      => 'webasyst',
    'version'     => '2.0.1', // developer preview
    'rights'      => false,
    'handlers'    => array(
        'image_upload' => 'imageUpload',
        'image_thumb' => 'imageThumb',
    )
);
