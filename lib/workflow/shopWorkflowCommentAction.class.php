<?php

class shopWorkflowCommentAction extends shopWorkflowAction
{

    public function getDefaultOptions()
    {
        $options = parent::getDefaultOptions();
        $options['html'] = true;
        return $options;
    }

    public function execute($params = null)
    {
        return array(
            'text' => waRequest::post('text')
        );
    }

    public function getButton()
    {
        if ($this->getOption('position') || $this->getOption('top')) {
            return '<a href="#" class="wf-action '.$this->getOption('button_class').'" data-action-id="'.$this->getId().'"><i class="icon16 '.$this->getOption('icon').'"></i><b><i>'.$this->getName().'</i></b></a>';
        } else {
            return parent::getButton();
        }
    }

}
