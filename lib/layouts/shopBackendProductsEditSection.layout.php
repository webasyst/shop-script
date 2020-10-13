<?php
/**
 * This layout wraps Edit page content and its sidebar.
 * Then it becomes included in shopBackendProductsLayout
 *
 * - When requested via XHR, with NO ?section=1 parameter:
 *   layout is empty, only main page content is rendered.
 * - When requested via XHR, WITH ?section=1 parameter present:
 *   layout contains section sidebar, but no outer <html> or <head> tags,
 *   no apps menu, etc.
 * - When requested in a regular way (no XHR), full layout is rendered
 *   with <!DOCTYPE> and stuff.
 */
class shopBackendProductsEditSectionLayout extends shopBackendProductsLayout
{
    public $options;

    /** @var shopProduct */
    public $product;

    public function __construct($options)
    {
        $this->options = $options;
        parent::__construct();
    }

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

        $this->template = wa()->getAppPath('templates/layouts/BackendProducts.html', 'shop');
        parent::execute();
    }

    /** Part of execute() that assigns vars for wrapper template (including sidebar) */
    public function assignWrapperVars()
    {
        $this->executeAction('sidebar', new shopProdSidebarAction($this->getProduct()->id));
        $this->view->assign([
            'product' => $this->getProduct(),
        ]);
    }

    public function getProduct()
    {
        if (!$this->product) {
            $this->product = ifset($this->options, 'product', null);
            if (!$this->product || !($this->product instanceof shopProduct)) {
                $product_id = waRequest::param('id', '', 'int');
                if (!$product_id) {
                    throw new waException('Not found', 404);
                }
                $this->product = new shopProduct($product_id);
            }
        }
        return $this->product;
    }
}
