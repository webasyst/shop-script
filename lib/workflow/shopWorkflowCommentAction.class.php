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
        $this->waLog('order_comment', $params);
        return array(
            'text' => nl2br(htmlspecialchars(waRequest::post('text'), ENT_QUOTES, 'utf-8')),
        );
    }

    public function getButton()
    {
        if ($this->getOption('position') || $this->getOption('top')) {
            return <<<HTML
<a href="#" class="wf-action {$this->getOption('button_class')}" data-action-id="{$this->getId()}">
    <i class="icon16 {$this->getOption('icon')}"></i><b><i>{$this->getName()}</i></b>
</a>
HTML;
        } else {
            return parent::getButton();
        }
    }
}
