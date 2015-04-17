<?php

return array(
    'template' => array(
        'value'            => array(),
        'title'            => /*_wp*/('Platforms'),
        'options_callback' => array('shopRedirectPlugin', 'settingsTemplates'),
        'control_type'     => waHtmlControl::GROUPBOX,
        'options_wrapper'  => array(
            'control_separator' => '</div><div class="value">',
        ),
    ),
    'custom'   => array(
        'value'        => array(),
        'title'        => /*_wp*/('Custom redirect patterns'),
        'description'  => '',
        'control_type' => 'RedirectControl',
        'options'      => array(),
    ),
);
