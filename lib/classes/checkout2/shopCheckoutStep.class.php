<?php
/**
 * Base class for new (after 8.0) frontend checkout steps.
 *
 * Also see shopConfig->getCheckoutSteps()
 */
abstract class shopCheckoutStep
{
    // Cache for getId(), can be overriden in subclasses to force a certain id
    protected $id;
    /** @var shopCheckoutConfig $checkout_config */
    protected $checkout_config;

    public function __construct(shopCheckoutConfig $checkout_config)
    {
        $this->checkout_config = $checkout_config;
    }

    public function getId()
    {
        if ($this->id) {
            return $this->id;
        }
        if (preg_match('~^shopCheckout(.*)Step$~', get_class($this), $m) && $m) {
            $this->id = strtolower($m[1]);
            return $this->id;
        }
        throw new waException('Unable to determine checkout step id');
    }

    public function prepare($data)
    {
        $result = [];

        // Render template in case process() won't have a chance to do that
        if (!empty($data['error_step_id'])) {
            $result = $this->addRenderedHtml([], $data, []);
        }

        return array(
            'result' => $result,
            'errors' => [],
            'can_continue' => true,
        );
    }

    public function process($data, $prepare_result)
    {
        return array(
            'data' => $data,
            'result' => array(),
            'errors' => array(),
            'can_continue' => true,
        );
    }

    /** @return string absolute path to render template using addRenderedHtml() */
    public function getTemplatePath()
    {
        // override in subclasses
    }

    /** @return shopCheckoutConfig */
    public function getCheckoutConfig()
    {
        return $this->checkout_config;
    }

    /**
     * Helper to prepare waHtmlControl for template.
     * It renders control itself into HTML without wrappers
     * while providing description, title, etc. in machine-readable form.
     */
    protected function renderWaHtmlControl($field_id, $row, $namespace)
    {
        $css_class = null;
        switch($row['control_type']) {
            case waHtmlControl::INPUT:
                $css_class = 'wa-input';
                break;
            case waHtmlControl::SELECT:
                $css_class = 'wa-select';
                break;
            case waHtmlControl::TEXTAREA:
                $css_class = 'wa-textarea';
                break;
            case waHtmlControl::RADIOGROUP:
                $css_class = 'wa-radio';
                break;
            case waHtmlControl::CHECKBOX:
                $css_class = 'wa-checkbox';
                break;
        }

        return [
            'label' => ifset($row, 'title', ''),
            'description' => ifset($row, 'description', ''),
            'control_type' => $row['control_type'],
            'wa_css_class_added' => !!$css_class,
            'value' => ifset($row, 'value', null),
            'html' => !$namespace ? '' : waHtmlControl::getControl($row['control_type'], $field_id, array_merge($row, [
                'namespace' => $namespace,
                'title_wrapper' => false,
                'description_wrapper' => false,
                'control_wrapper' => "%s%s%s",
                'control_separator' => '',
                'class' => trim($css_class.' '.ifempty($row, 'class', '')),
            ])),
        ];
    }

    /**
     * This static method is used to call all existing checkout steps
     * in predetermined order, gathering data
     * 1) vars for template rendered by $wa->shop->checkout()->form()
     * 2) JSON controller frontendOrder/calculate
     * 3) JSON controller frontendOrder/create
     *
     * All three use same basic execution flow with slightly different parameters.
     * @param string $origin identifies where this is being called from
     * @param waOrder $order
     * @param array $input data from POST, session, or another input source
     * @param waStorage $storage
     * @param shopCheckoutConfig $config
     * @return array
     */
    public static function processAll($origin, $order, $input=[], $storage=null, $checkout_config=null)
    {
        if (!$order || !($order instanceof shopOrder)) {
            throw new waException('compatible order is required');
        }

        // Default storage
        if (!$storage) {
            $storage = new waPrefixStorage(['namespace'=>'shop_checkout2']);
        }
        if (!($storage instanceof waStorage)) {
            throw new waException('incompatible storage');
        }

        // Default checkout config
        if (!$checkout_config) {
            if (wa()->getEnv() !== 'frontend') {
                throw new waException('Checkout config is required');
            }
            $route = wa()->getRouting()->getRoute();
            $checkout_config = new shopCheckoutConfig(ifset($route, 'checkout_storefront_id', null));
        }
        if (!($checkout_config instanceof shopCheckoutConfig)) {
            throw new waException('incompatible checkout config');
        }

        // All data we know about upcoming order is gathered here.
        // This array is passed through several objects, each representing a checkout step.
        // Each step modifies this array.
        //
        // Steps know nothing about cart. Steps operate on unsaved shopOrder and items within.
        // Items may or may not have been added from current user's shopCart.
        //
        // Steps should not use anything from global objects,
        // even wa()->getUser() and wa()->getStorage().
        // For better testability steps only operate on data that came in parameters.
        $data = [
            'origin'  => $origin,
            'order'   => $order,
            'input'   => $input,
            'storage' => $storage,
            'result'  => [],
        ];

        /**
         * @var $checkout_steps array[shopCheckoutStep]
         *
         * This contains objects that together form a checkout process.
         * Order of checkout steps is fixed and can not be changed by shop settings.
         *
         * Something like:
         * -> process cart from session and/or post data
         * -> select shipping region/city
         * -> select shipping
         * -> select payment
         * -> fill in customer details, including shipping address
         * -> create order
         *
         * Each step uses data prepared by previous steps and saved to $data.
         * Each step can return an array of human-readable errors to return to JS,
         * or even stop processing without returning any errors.
         */
        $checkout_steps = $checkout_config->getCheckoutSteps();

        /**
         * @var $result array
         * Each checkout step can return certain value to JS.
         * They get gathered here. More than one step can return data at the same time.
         */
        $result = array();

        /**
         * @var $errors array|null
         * Contains list of human-readable messages from the first step that errs.
         * If step errs, no other steps attempt to process any further.
         *
         * @var $error_step_id string|null
         */
        $error_step_id = null;
        $errors = null;

        //
        // Checkout step objects process one after another in a fixed order.
        //
        foreach($checkout_steps as $step) {
            /** @var $step shopCheckoutStep */
            $step_id = $step->getId();

            // Plugins are allowed to do pretty much anything they want
            wa('shop')->event('checkout_before_'.$step_id, ref(array(
                'step_id'       => $step_id,

                'result'        => &$result,
                'data'          => &$data,

                'error_step_id' => &$error_step_id,
                'errors'        => &$errors,
            )));

            //
            // ->prepare() is always called for all steps, even if
            // previous step returned an error. This can not change $data,
            // but may err, may return something to JS and may pass data to process() below.
            //
            $data['result'] = $result;
            $data['errors'] = $errors;
            $data['error_step_id'] = $error_step_id;
            $prepare_result = $step->prepare($data);

            // More love for plugins
            wa('shop')->event('checkout_prepared_'.$step_id, ref(array(
                'step_id'        => $step_id,

                'prepare_result' => &$prepare_result,
                'result'         => &$result,
                'data'           => &$data,

                'error_step_id'  => &$error_step_id,
                'errors'         => &$errors,
            )));

            $result[$step_id] = ifset($prepare_result, 'result', array());
            if (empty($error_step_id)) {
                $errors = ifset($prepare_result, 'errors', null);
                $can_continue = empty($errors) && ifset($prepare_result, 'can_continue', true);
                if (!$can_continue) {
                    $error_step_id = $step_id;
                }
            }

            //
            // $step->process() is only called if no previous step returned an error.
            // It is allowed to write anywhere, but should only write to $data[$step_id] if at all possible.
            //
            if (empty($error_step_id)) {
                $data['result'] = $result;
                $process_result = $step->process($data, $prepare_result);
            } else {
                $process_result = null;
            }

            // checkout_after_* is called even when step is not processed due to earlier error
            wa('shop')->event('checkout_after_'.$step_id, ref(array(
                'step_id' => $step_id,

                'is_processed' => !!$process_result,
                'prepare_result' => &$prepare_result,
                'process_result' => &$process_result,
                'result' => &$result,
                'data' => &$data,

                'error_step_id' => &$error_step_id,
                'errors' => &$errors,
            )));

            if ($process_result) {
                $data = ifset($process_result, 'data', $data); // required
                $result = ifset($data, 'result', $result);
                $result[$step_id] = ifset($process_result, 'result', array()) + $result[$step_id];
                $errors = ifset($process_result, 'errors', null);
                $can_continue = empty($errors) && ifset($process_result, 'can_continue', true);
                if (!$can_continue) {
                    $error_step_id = $step_id;
                }
            }

        }

        // pass errors to JS
        if ($error_step_id) {
            $data['error_step_id'] = $result['error_step_id'] = $error_step_id;
            $data['errors'] = $result['errors'] = $errors;
        }

        // Final chance for plugins to modify stuff
        wa('shop')->event('checkout_result', ref(array(
            'data' => &$data,
            'result' => &$result,
        )));

        // Finally, we're done
        $data['result'] = $result;
        return $data;
    }

    protected function addRenderedHtml($result, $data, $errors, $template=null)
    {
        if (!empty($data['input'][$this->getId()]['html']) && $data['origin'] != 'create') {
            if (empty($template)) {
                $template = $this->getTemplatePath();
            }
            if (empty($template) || !is_readable($template)) {
                return $result;
            }
            $vars = $data['result'];
            $vars[$this->getId()] = $result;
            $vars['contact'] = ifset($data, 'contact', null);
            if (!empty($data['error_step_id'])) {
                $vars['error_step_id'] = $data['error_step_id'];
                $vars['errors'] = $data['errors'];
            } else if($errors) {
                $vars['error_step_id'] = $this->getId();
                $vars['errors'] = $errors;
            }
            $result['html'] = $this->renderTemplate($template, $vars);
        }
        return $result;
    }

    protected function renderTemplate($template_path, $assign = array())
    {
        $view = wa('shop')->getView();
        $old_vars = $view->getVars();
        $view->assign($assign + array(
            'config' => $this->getCheckoutConfig(),
        ));
        $html = $view->fetch($template_path);
        $view->clearAllAssign();
        $view->assign($old_vars);
        return $html;
    }
}
