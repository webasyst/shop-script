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
        $class_icon = "icon16 {$this->getOption('icon')}";
        $class_button = $this->getOption('button_class');

        if (wa()->whichUI() >= '2.0') {
            $class_icon = "icon text-green fas fa-plus-circle";
            $class_button = "button light-gray rounded";
        }

        if ($this->getOption('position') || $this->getOption('top')) {
            return <<<HTML
<a href="#" class="wf-action {$class_button} actions-link" data-action-id="{$this->getId()}">
    <i class="$class_icon"></i> {$this->getName()}
</a>
HTML;
        } else {
            return parent::getButton();
        }
    }
}
