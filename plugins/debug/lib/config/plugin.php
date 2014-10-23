<?php

return array(
    'name'        => 'debug',
    'title'       => ('Debug'),
    'description' => ('Debug plugins interaction'),
    'vendor'      => 'webasyst',
    'version'     => '0.0.1',
    'handlers'    => array(
        /* frontend UI hooks */
        /* backend UI hooks */
        /* data hooks */
        'product_delete' => 'productDeleteHandler',
        'product_save'   => 'productSaveHandler',
        /* frontend UI hooks */

    ),

    /**
     * dummy settings
     */
    'settings'    => array(
        'input' => array(
            'title' => /*_wp*/('input'),
            'description' => /*_wp*/('Input field with description'),
            'value' => '',
            'settings_html_function' => waHtmlControl::INPUT,
        ),
        'checkbox' => array(
            'title' => /*_wp*/('checkbox'),
            'description' => /*_wp*/('Checkbox description'),
            'label' => /*_wp*/('Checkbox label'),
            'settings_html_function' => waHtmlControl::CHECKBOX,
        ),
        'radio' => array(
            'title'                  => 'Radio',
            'description'            => 'Radio group settings description',
            'value'                  => 'opt3',
            'options'                => array(
                array(
                    'value'       => 'opt1',
                    'title'       => 'Option #1',
                    'description' => 'First option'
                ),
                array(
                    'value' => 'opt2',
                    'title' => 'Option #2',
                ),
                array(
                    'value'       => 'opt3',
                    'title'       => 'Option #3',
                    'description' => 'Default option'
                ),
            ),
            'settings_html_function' => waHtmlControl::RADIOGROUP,
        ),
    ),
);

//EOF