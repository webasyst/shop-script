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

    /**
     * shopBackendProductsEditSectionLayout constructor.
     * @param array $options
     *      shopProduct $options['product']
     *      string      $options['content_id'] - ID of page 'media', 'general', 'sku', etc
     */
    public function __construct($options)
    {
        $options = is_array($options) ? $options : [];
        $options['content_id'] = isset($options['content_id']) && is_scalar($options['content_id']) ? strval($options['content_id']) : '';
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
        if (wa()->whichUI() == '1.3') {
            $this->template = wa()->getAppPath('templates/layouts-legacy/BackendProducts.html', 'shop');
        } else {
            $this->template = wa()->getAppPath('templates/layouts/BackendProducts.html', 'shop');
        }
        parent::execute();
    }

    /** Part of execute() that assigns vars for wrapper template (including sidebar)
     * @throws waException
     */
    public function assignWrapperVars()
    {
        $backend_prod_event = $this->throwEvent();
        $product_id = $this->getProduct()->id;
        $this->executeAction('sidebar', new shopProdSidebarAction([
            'product_id' => $product_id,
            'backend_prod_event' => $backend_prod_event
        ]));

        $this->executeAction('products_menu', new shopProdListSidebarAction());

        $context = [
            'presentation' => waRequest::request('presentation', null, waRequest::TYPE_INT),
            'another_section_url' => rawurldecode(waRequest::get('another_section_url', '', waRequest::TYPE_STRING_TRIM)),
        ];
        $product_list_data = null;
        if ($context['presentation'] && $product_id) {
            $wa_app_url = wa()->getAppUrl('shop') . 'products/';
            $near_products = shopPresentation::getNearestProducts($product_id, $context['presentation'], true);
            $context['page'] = $near_products['page'];
            if ($near_products['count'] > 1) {
                $product_list_data = [
                    'prev' => null,
                    'next' => null,
                    'position' => $near_products['position'],
                    'count' => $near_products['count']
                ];
                $pre_link = $this->options['content_id'] . '/?presentation=' . $context['presentation'];
                if ($near_products['prev']['id'] > 0) {
                    $product_list_data['prev'] = [
                        'url' => $wa_app_url . $near_products['prev']['id'] . '/',
                        'pre_url' => $wa_app_url . $near_products['prev']['id'] . '/' . $pre_link,
                        'name' => $near_products['prev']['name'],
                    ];
                }
                if ($near_products['next']['id'] > 0) {
                    $product_list_data['next'] = [
                        'url' => $wa_app_url . $near_products['next']['id'] . '/',
                        'pre_url' => $wa_app_url . $near_products['next']['id'] . '/' . $pre_link,
                        'name' => $near_products['next']['name'],
                    ];
                }
            }
        }

        $this->view->assign([
            'product'            => $this->getProduct(),
            'product_list_data'  => $product_list_data,
            'context'            => $context,
            'backend_prod_event' => $backend_prod_event
        ] );
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


    /**
     * Throw 'backend_prod' event
     * @return array
     * @throws waException
     */
    protected function throwEvent()
    {
        $product = $this->getProduct();

        /**
         * @event backend_prod
         *
         * @param shopProduct $product
         * @param string $section_id
         */
        $params = [
            'product' => $product,
            'section_id' => 'product',

            // Even though we know content_id here, it is misleading in this place.
            // backend_prod event is not called when user switches between tabs in a single section.
            // If a plugin tries to show different content in backend_prod depending on content_id,
            // it will work part of the time (just after full refresh F5) but will leave stale content
            // if user clicks on a link in sidebar.
            //'content_id' => $this->options['content_id'],
        ];
        return wa('shop')->event('backend_prod', $params);
    }
}
