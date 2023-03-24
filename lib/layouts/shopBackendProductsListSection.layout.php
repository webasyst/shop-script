<?php
/**
 * This layout wraps list of products and sidebar.
 * Then it becomes included in shopBackendProductsLayout
 *
 * - When requested via XHR, with NO ?section=1 parameter:
 *   layout is empty, only main page content is rendered. No sidebar.
 * - When requested via XHR, WITH ?section=1 parameter present:
 *   layout contains section sidebar, but no outer <html> or <head> tags,
 *   no apps menu, etc.
 * - When requested in a regular way (no XHR), full layout is rendered
 *   with <!DOCTYPE> and stuff.
 */
class shopBackendProductsListSectionLayout extends shopBackendProductsLayout
{
    public function execute()
    {
        // Page content only? No sidebar, no inner wrapper.
        if (waRequest::isXMLHttpRequest() && !waRequest::request('section')) {
            $this->template = 'string:{$content}';
            return waLayout::execute();
        }

        // This is actual execute() assigning vars etc.
        $this->assignWrapperVars();

        // Page content + sidebar mode with no <html> outer layout?
        if (waRequest::isXMLHttpRequest()) {
            return waLayout::execute();
        }

        // Otherwise, it's full layout render, with <!DOCTYPE> and <head>
        // Render current layout in a separate view, change template
        // and make parent class render its part.
        $view = wa('shop')->getView();
        $view->assign($this->blocks);
        $this->blocks = [
            'content' => $view->fetch($this->getTemplate()),
        ];
        if (wa()->whichUI() == '1.3') {
            $this->template = wa()->getAppPath('templates/layouts-legacy/BackendProducts.html', 'shop');
        } else {
            $this->template = wa()->getAppPath('templates/layouts/BackendProducts.html', 'shop');
        }
        parent::execute();
    }

    /** Part of execute() that assigns vars for wrapper template (including sidebar) */
    public function assignWrapperVars()
    {
        $this->executeAction('sidebar', new shopProdListSidebarAction());
    }
}
