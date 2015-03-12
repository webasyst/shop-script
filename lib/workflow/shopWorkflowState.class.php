<?php

/**
 * @property shopWorkflow $workflow
 */
class shopWorkflowState extends waWorkflowState
{
    protected $style_html;
    protected $frontend_style_html;
    protected $available_actions = array();

    /**
     * @param string $id id as stored in database
     * @param waWorkflow $workflow
     * @param array $options option => value
     */
    public function __construct($id, waWorkflow $workflow, $options = array())
    {
        parent::__construct($id, $workflow, $options['options']);
        if (isset($options['name'])) {
            $this->name = waLocale::fromArray($options['name']);
        }
        if (isset($options['available_actions'])) {
            $this->available_actions = $options['available_actions'];
        }
    }

    public function getActions($params = null)
    {
        $actions = parent::getActions($params);
        foreach ($actions as $a_id => $a) {
            if ($a instanceof shopWorkflowAction) {
                if (!$a->isAvailable($params)) {
                    unset($actions[$a_id]);
                }
            }
        }
        return $actions;
    }

    protected function getAvailableActionIds($params = null)
    {
        return $this->available_actions;
    }

    public function getStyle($frontend=false)
    {
        if ($frontend) {
            if ($this->frontend_style_html === null) {
                $style_html = '';
                $style = $this->getOption('style');
                if ($style && !empty($style['color'])) {
                    $style_html = 'background-color:'.$style['color'].';';
                }
                $this->frontend_style_html = $style_html;
            }
            return $this->frontend_style_html;
        }

        if ($this->style_html === null) {
            $style_html = '';
            $style = $this->getOption('style');
            if ($style) {
                foreach ($style as $k => $v) {
                    $style_html .= $k.':'.$v.';';
                }
            }
            $this->style_html = $style_html;
        }
        return $this->style_html;
    }
}

