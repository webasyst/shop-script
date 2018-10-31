<?php
/**
 * /order/ page in frontend: new single-page cart and checkout
 */
class shopFrontendOrderAction extends shopFrontendAction
{
    const THEME_FILE = 'order.html';

    public function __construct($params = null)
    {
        parent::__construct($params);

        // Render page in styles of default theme
        // depending on checkout settings
        if (!waRequest::isXMLHttpRequest()) {
            $route = wa()->getRouting()->getRoute();
            $checkout_config = new shopCheckoutConfig(ifset($route, 'checkout_storefront_id', null));
            if (ifempty($checkout_config, 'design', 'custom', null) === true) {
                $this->setLayout(new shopFrontendCheckoutLayout());
            }
        }
    }

    public function execute()
    {

        // Does current theme support new checkout?
        $theme_template_path = $this->getTheme()->path.'/'.self::THEME_FILE;
        if (!file_exists($theme_template_path)) {
            // Does "try with default styles" mode enabled in settlement settings?
            if (false) {
                // !!! TODO not implemented
            } else {
                throw new waException(_ws('Page not found'), 404);
            }
        }

        // Is new checkout enabled in settlement settings?
        // !!! TODO

        // Make sure this is never cached by browser
        $this->getResponse()->addHeader("Cache-Control", "no-store, no-cache, must-revalidate");
        $this->getResponse()->addHeader("Expires", date("r"));

        $this->view->assign($this->getVars());
        $this->setThemeTemplate(self::THEME_FILE);
        $this->getResponse()->setTitle(_w('Cart'));
    }

    // Vars for order.html template
    protected function getVars()
    {
        $result = [];
        $result['contact'] = wa()->getUser();
        $route = wa()->getRouting()->getRoute();
        $result['config'] = new shopCheckoutConfig(ifset($route, 'checkout_storefront_id', null));

        // !!! TODO plugin hook

        return $result;
    }
}
