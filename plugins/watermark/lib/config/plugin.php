<?php

return array(
    'name'        => 'Watermark',
    'description' => 'Applies watermark text or image on uploaded photos',
    'img'         => 'img/watermark.png',
    'vendor'      => 'webasyst',
    'version'     => '2.1.0',
    'rights'      => false,
    'handlers'    => array(
        'image_upload' => 'imageUpload',
        'image_thumb' => 'imageThumb',
    )
);
