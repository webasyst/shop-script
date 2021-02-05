<?php
/**
 * /order/ page in frontend: new single-page cart and checkout
 *
 * Note that the page itself does not do much work.
 * Controller loads the order.html template from theme which uses
 * $wa->shop->checkout()->cart() and ->form().
 * Those do the heavy lifting.
 */
class shopFrontendOrderAction extends shopFrontendAction
{
    const THEME_FILE = 'order.html';

    protected $checkout_config;
    protected $render_default_theme;

    /**
     * Action constructor should not throw exceptions because
     * they can not be shown using design theme template.
     * Yet, we still want to check in constructor for certain things
     * because layouts should be set in constructor.
     */
    protected $constructor_exception = null;

    public function __construct($params = null)
    {
        parent::__construct($params);

        // Is new checkout enabled in settlement settings?
        $route = wa()->getRouting()->getRoute();
        if (2 != ifset($route, 'checkout_version', null)) {
            $this->constructor_exception = new waException(_ws('Page not found'), 404);
            return;
        }

        // Render page in styles of default theme depending on checkout settings
        $this->checkout_config = new shopCheckoutConfig(ifset($route, 'checkout_storefront_id', null));
        if (ifempty($this->checkout_config, 'design', 'custom', null) === true) {
            // Set layout that uses templates/layouts/FrontendCheckout.html instead of file from current theme
            if (!waRequest::isXMLHttpRequest()) {
                $this->setLayout(new shopFrontendCheckoutLayout());
            }

            // Use order.html template from unmodified default theme
            $this->theme = new waTheme('default', 'shop', waTheme::ORIGINAL);
            $this->setThemeTemplate(self::THEME_FILE);
        } else {
            // Make sure current theme supports new checkout
            $theme_template_path = $this->getTheme()->path.'/'.self::THEME_FILE;
            if (!file_exists($theme_template_path)) {
                $this->constructor_exception = new waException(_w('Page not supported by selected design theme.'), 500);
                return;
            }
            $this->setThemeTemplate(self::THEME_FILE);
        }
    }

    public function preExecute()
    {
        if ($this->constructor_exception) {
            throw new waException($this->constructor_exception);
        }
    }

    public function execute()
    {
        // Make sure this page is never cached by browser
        $this->getResponse()->addHeader("Cache-Control", "no-store, no-cache, must-revalidate");
        $this->getResponse()->addHeader("Expires", date("r"));

        $this->getResponse()->setTitle(_w('Cart'));
        $this->view->assign($this->getVars());

        /**
         * @event frontend_order
         * Allows to append HTML to single-page checkout in frontend - /order/
         * @param shopCheckoutConfig $params['config']
         * @return array[string]string $return[%plugin_id%] html output
         */
        $this->view->assign('frontend_order', wa('shop')->event('frontend_order', ref([
            'config' => $this->checkout_config,
        ])));
    }

    // Vars for order.html template
    protected function getVars()
    {
        return [
            'config' => $this->checkout_config,
            'contact' => wa()->getUser(),
            'root_path' => wa()->getConfig()->getRootPath()
        ];
    }
}
