<?php
/**
 * Same as shopFrontendLayout but uses different template file.
 * Instead of `index.html` in theme this uses templates/layouts/FrontendCheckout.html
 *
 * Used when SS8 checkout is set up to use styles from theme Default.
 */
class shopFrontendCheckoutLayout extends shopFrontendLayout
{
    public function execute()
    {
        parent::execute();

        // Assign checkout settings to template
        $route = wa()->getRouting()->getRoute();
        $checkout_config = new shopCheckoutConfig(ifset($route, 'checkout_storefront_id', null));
        $this->view->assign('design_settings', $checkout_config['design']);
    }

    protected function setThemeTemplate($template)
    {
        // Override and skip
    }

    protected function getTemplate()
    {
        // Use full path because relative will go from theme folder, not app folder
        return wa()->getAppPath(parent::getTemplate(), 'shop');
    }
}
