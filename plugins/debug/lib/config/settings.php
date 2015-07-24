<?php
return array(
    'input'         => array(
        'title'        => /*_wp*/
            ('input'),
        'description'  => /*_wp*/
            ('Input field with description'),
        'value'        => '',
        'control_type' => waHtmlControl::INPUT,
    ),
    'checkbox'      => array(
        'title'        => /*_wp*/
            ('checkbox'),
        'description'  => /*_wp*/
            ('Checkbox description'),
        'label'        => /*_wp*/
            ('Checkbox label'),
        'control_type' => waHtmlControl::CHECKBOX,
    ),
    'radio'         => array(
        'title'        => 'Radio',
        'description'  => 'Radio group settings description',
        'value'        => 'opt3',
        'options'      => array(
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
        'control_type' => waHtmlControl::RADIOGROUP,
    ),
    'fields'        => array(
        'title'        => 'Contact fields',
        'control_type' => waHtmlControl::GROUPBOX,
        'options'      => shopDebugPlugin::getContactFields(),
    ),
    'more_checkbox' => array(
        'value'        => 1,
        'title'        => 'True is default',
        'control_type' => waHtmlControl::CHECKBOX,

    ),
);
