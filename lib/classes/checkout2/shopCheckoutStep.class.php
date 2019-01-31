<?php
/**
 * Base class for new (after 8.0) frontend checkout steps.
 *
 * Also see shopConfig->getCheckoutSteps()
 */
abstract class shopCheckoutStep
{
    // Cache for getId(), can be overridden in subclasses to force a certain id
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
            'data'         => $data,
            'result'       => array(),
            'errors'       => array(),
            'can_continue' => true,
        );
    }

    /** @return string absolute path to render template using addRenderedHtml() */
    abstract public function getTemplatePath();

    /** @return shopCheckoutConfig */
    public function getCheckoutConfig()
    {
        return $this->checkout_config;
    }

    /**
     * Helper to prepare waHtmlControl for template.
     * It renders control itself into HTML without wrappers
     * while providing description, title, etc. in machine-readable form.
     * @param string $field_id
     * @param array $row
     * @param $namespace
     * @return array
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

        if (!$namespace) {
            // Do not render unless asked for
            $html = '';
        } elseif ($row['control_type'] == waHtmlControl::DATETIME) {

            // For 'datetime' type we want a specific HTML structure
            $html = waHtmlControl::getControl($row['control_type'], $field_id, array_merge($row, [
                'namespace' => $namespace,
                'title_wrapper' => false,
                'description_wrapper' => false,
                'class' => '',

                // This is overall wrapper around date input and time selector
                'control_wrapper' => '
<div class="wa-desired-date-wrapper">
    <div class="wa-fields-group">
        <div class="wa-field-wrapper wa-field-date">
            %s%s%s
        </div>
    </div>
</div>',

                // This gets inserted between date input and time selector
                'control_separator' => '
    </div><div class="wa-field-wrapper wa-field-time">
',
            ]));
        } else {
            // For everything else we only want the input element itself, without any wrappers
            $html = waHtmlControl::getControl($row['control_type'], $field_id, array_merge($row, [
                'namespace' => $namespace,
                'title_wrapper' => false,
                'description_wrapper' => false,
                'class' => trim($css_class.' '.ifempty($row, 'class', '')),
                'control_wrapper' => "%s%s%s",
                'control_separator' => '',
            ]));
        }

        return [
            'label' => ifset($row, 'title', ''),
            'description' => ifset($row, 'description', ''),
            'control_type' => $row['control_type'],
            'wa_css_class_added' => !!$css_class,
            'value' => ifset($row, 'value', null),
            'html' => $html,
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
     * @param shopOrder $order
     * @param array $input data from POST, session, or another input source
     * @param null $checkout_config
     * @return array
     * @throws waException
     * @internal param shopCheckoutConfig $config
     */
    public static function processAll($origin, $order, $input = [], $checkout_config = null)
    {
        if (!$order || !($order instanceof shopOrder)) {
            throw new waException('compatible order is required');
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
            'result'  => [],
        ];

        /**
         * @var $checkout_steps array[shopCheckoutStep]
         *
         * This contains objects that together form a checkout process.
         * Order of checkout steps is fixed and cannot be changed by shop settings.
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
        foreach ($checkout_steps as $step) {
            $time_start = microtime(true);

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
            // previous step returned an error. This cannot change $data,
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

            $time_delta = microtime(true) - $time_start;
            if ($time_delta >= 1 && defined('SHOP_CHECKOUT2_PROFILING')) {
                waLog::log($step_id.' -> '.round($time_delta, 3), 'checkout2-time.log');
            }

            // Short-cut if asked to. In order to build certain dialogs
            // we don't need data from later steps.
            if (ifset($input, 'abort_after_step', null) === $step_id) {
                break;
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

    protected function addRenderedHtml($result, $data, $errors)
    {
        if (!empty($data['input'][$this->getId()]['html']) && $data['origin'] != 'create' && $data['origin'] != 'form') {
            $template_file = $this->getTemplatePath();
            if (empty($template_file)) {
                return $result;
            }

            // Custom template in theme exists?
            $theme = new waTheme(waRequest::getTheme());
            $theme_template_path = $theme->path.'/order.'.$template_file;
            if (file_exists($theme_template_path)) {
                $template = 'order.'.$template_file;
            }

            // Default template from app folder
            if (empty($template)) {
                $theme = null;
                $template = wa()->getAppPath('templates/actions/frontend/order/form/'.$template_file, 'shop');
                if (!is_readable($template)) {
                    return $result;
                }
            }

            // Vars for template
            $vars = $data['result'];
            $vars[$this->getId()] = $result;
            $vars['contact'] = ifset($data, 'contact', null);
            if (!empty($data['error_step_id'])) {
                $vars['error_step_id'] = $data['error_step_id'];
                $vars['errors'] = $data['errors'];
            } elseif ($errors) {
                $vars['error_step_id'] = $this->getId();
                $vars['errors'] = $errors;
            } else {
                $vars['error_step_id'] = null;
                $vars['errors'] = null;
            }

            // checkout_render_* allows to inject HTML into template of each checkout step
            $vars['event_hook'][$this->getId()] = wa('shop')->event('checkout_render_'.$this->getId(), ref([
                'step_id' => $this->getId(),
                'data' => $data,
                'error_step_id' => &$vars['error_step_id'],
                'errors' => &$vars['errors'],
                'vars' => &$vars,
            ]));

            // Render the template
            $time_start = microtime(true);
            $result['html'] = $this->renderTemplate($template, $vars, $theme);
            $time_delta = microtime(true) - $time_start;
            if ($time_delta >= 0.5 && defined('SHOP_CHECKOUT2_PROFILING')) {
                waLog::log($this->getId().' render -> '.round($time_delta, 3), 'checkout2-time.log');
            }
        }
        return $result;
    }

    protected function renderTemplate($template_path, $assign = array(), $theme = null)
    {
        $view = wa('shop')->getView();
        $old_vars = $view->getVars();
        if ($theme) {
            $view->setThemeTemplate($theme, $template_path);
        }
        $view->assign($assign + array(
            'config' => $this->getCheckoutConfig(),
            'shop_checkout_include_path' => wa()->getAppPath('templates/actions/frontend/order/', 'shop'),
        ));
        $html = $view->fetch($template_path);
        $view->clearAllAssign();
        $view->assign($old_vars);
        return $html;
    }
}
