<?php

class shopWorkflowShipAction extends shopWorkflowAction
{
    public function getDefaultOptions()
    {
        $options = parent::getDefaultOptions();
        $options['html'] = true;
        return $options;
    }

    public function execute($params = null)
    {
        if ($tracking = waRequest::post('tracking_number')) {
            return array(
                'text' => 'Tracking Number: '.$tracking,
                'params' => array(
                    'tracking_number' => $tracking
                ),
                'update' => array(
                    'params' => array(
                        'tracking_number' => $tracking
                    )
                )
            );
        } else {
            return true;
        }
    }
}