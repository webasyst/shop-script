<?php

/**
 * @property shopWorkflow $workflow
 */
class shopWorkflowState extends waWorkflowState
{
    protected $style_html;
    protected $frontend_style_html;
    protected $available_actions = array();
    protected $payment_allowed = true;

    /**
     * @var string|null
     */
    protected $payment_not_allowed_text = null;

    public $original = false;

    /**
     * @param string     $id      id as stored in database
     * @param waWorkflow $workflow
     * @param array      $options option => value
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
        if (isset($options['payment_allowed'])) {
            $this->payment_allowed = !!$options['payment_allowed'];
        }
        if (isset($options['payment_not_allowed_text']) && is_scalar($options['payment_not_allowed_text'])) {
            $this->payment_not_allowed_text = (string)$options['payment_not_allowed_text'];
        }
    }

    /**
     * @param array $params array with order data
     * @param bool  $name_only
     * @return array
     */
    public function getActions($params = null, $name_only = false)
    {
        if (wa()->getEnv() === 'backend') {
            // get user rights
            $user = wa()->getUser();

            if ($user->isAdmin('shop')) {
                $rights = true;
            } else {
                $rights = $user->getRights('shop', 'workflow_actions.%');
                if (!empty($rights['all'])) {
                    $rights = true;
                }
            }

            if (empty($rights)) {
                return array();
            }
        } else {
            $rights = true;
        }

        $actions = parent::getActions($params, false);

        // add internal actions related to merging unsettled orders
        if (!empty($params['unsettled'])) {
            if (($action = $this->workflow->getActionById('settle'))) {
                $actions[$action->getId()] = $action;
            }
        }

        // add internal actions related to authorized orders
        $action_ids = array('cancel', 'capture');
        foreach ($action_ids as $action_id) {
            if (empty($actions[$action_id]) && ($action = $this->workflow->getActionById($action_id))) {
                $actions[$action->getId()] = $action;
            }
        }

        // Filter out unavailable actions
        foreach ($actions as $a_id => $a) {
            if (is_array($rights) && empty($rights[$a_id])) {
                unset($actions[$a_id]);
            } elseif ($a instanceof shopWorkflowAction) {
                if (!$a->isAvailable($params)) {
                    unset($actions[$a_id]);
                }
            }
        }

        // Format actions
        if ($name_only) {
            foreach ($actions as $a_id => $a) {
                $actions[$a_id] = $a->getName();
            }
        }

        return $actions;
    }

    protected function getAvailableActionIds($params = null)
    {
        return $this->available_actions;
    }

    public function getStyle($frontend = false)
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

    public function paymentAllowed()
    {
        $disabled_states = array(
            'auth',
            'deleted',
            'refunded',
            'completed',
            'paid',
        );
        if (in_array($this->id, $disabled_states, true)) {
            return null;
        }
        return $this->payment_allowed;
    }

    /**
     * @return string
     */
    public function paymentNotAllowedText()
    {
        if ($this->payment_not_allowed_text !== null) {
            return $this->payment_not_allowed_text;
        } else {
            return self::paymentNotAllowedDefaultText();
        }
    }

    public static function paymentNotAllowedDefaultText()
    {
        return _w('Payment option will be available in your customer account after your order has been verified.');
    }
}
